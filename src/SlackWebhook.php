<?php

declare(strict_types=1);

namespace Hampel\SlackMessage;

/**
 * @see https://github.com/illuminate/notifications
 * @see https://github.com/illuminate/notifications/blob/master/Channels/SlackWebhookChannel.php
 * @see https://laravel.com/docs/5.6/notifications#slack-notifications
 */

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

class SlackWebhook
{
    /**
     * The HTTP client instance.
     *
     * @var \Psr\Http\Client\ClientInterface
     */
    protected $http;

    /**
     * The factory used to create the request.
     *
     * @var \Psr\Http\Message\RequestFactoryInterface
     */
    protected $requestFactory;

    /**
     * The factory used to create the request body.
     *
     * @var \Psr\Http\Message\StreamFactoryInterface
     */
    protected $streamFactory;

    /**
     * Create a new Slack channel instance.
     *
     * Any PSR-18 client will do. The PSR-17 factories are optional: when they are omitted
     * and Guzzle is installed, its factory is used.
     *
     * @param  \Psr\Http\Client\ClientInterface  $http
     * @param  \Psr\Http\Message\RequestFactoryInterface|null  $requestFactory
     * @param  \Psr\Http\Message\StreamFactoryInterface|null  $streamFactory
     * @return void
     */
    public function __construct(
        ClientInterface $http,
        RequestFactoryInterface $requestFactory = null,
        StreamFactoryInterface $streamFactory = null
    ) {
        $this->http = $http;

        if ($requestFactory !== null && $streamFactory !== null) {
            $this->requestFactory = $requestFactory;
            $this->streamFactory = $streamFactory;

            return;
        }

        $factory = static::discoverFactory();

        $this->requestFactory = $requestFactory ?: $factory;
        $this->streamFactory = $streamFactory ?: $factory;
    }

    /**
     * Find a PSR-17 factory to build requests with.
     *
     * @return \Psr\Http\Message\RequestFactoryInterface&\Psr\Http\Message\StreamFactoryInterface
     *
     * @throws \RuntimeException
     */
    protected static function discoverFactory()
    {
        if (class_exists('GuzzleHttp\Psr7\HttpFactory')) {
            return new \GuzzleHttp\Psr7\HttpFactory();
        }

        throw new \RuntimeException(
            'No PSR-17 factory was supplied and none could be found. Pass a '
            . 'Psr\Http\Message\RequestFactoryInterface and a '
            . 'Psr\Http\Message\StreamFactoryInterface to the constructor.'
        );
    }

    /**
     * Build a message with a callback.
     *
     * @param \Closure $callback
     *
     * @return SlackMessage
     */
    public function message(\Closure $callback)
    {
        $message = new SlackMessage();
        $callback($message);

        return $message;
    }

    /**
     * Send the given notification.
     *
     * @param  string $url
     * @param  SlackMessage $message
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function send($url, SlackMessage $message)
    {
        return $this->sendPayload($url, $this->buildPayload($message), $message->http);
    }

    /**
     * Send a pre-built payload to the Slack webhook.
     *
     * Only the "headers" key of the options is used; anything else a transport needs is
     * configured on the client itself.
     *
     * @param  string  $url
     * @param  array<array-key, mixed>  $payload
     * @param  array<string, mixed>  $options
     * @return \Psr\Http\Message\ResponseInterface
     *
     * @throws \InvalidArgumentException
     */
    public function sendPayload($url, array $payload, array $options = [])
    {
        $payload = static::unwrapPayload($payload);

        $body = json_encode($payload);

        if ($body === false) {
            throw new \InvalidArgumentException('json_encode error: ' . json_last_error_msg());
        }

        $request = $this->requestFactory->createRequest('POST', $url)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->streamFactory->createStream($body));

        if (isset($options['headers']) && is_array($options['headers'])) {
            foreach ($options['headers'] as $name => $value) {
                if (!is_string($name) || !is_string($value)) {
                    throw new \InvalidArgumentException('Header names and values must be strings.');
                }

                $request = $request->withHeader($name, $value);
            }
        }

        return $this->http->sendRequest($request);
    }

    /**
     * Did Slack accept the message?
     *
     * An incoming webhook reports failure as an HTTP status. The Web API answers 200 whatever
     * happens and puts the outcome in an "ok" field, so a response has to be read both ways.
     *
     * @param  \Psr\Http\Message\ResponseInterface  $response
     * @return bool
     */
    public function accepted(ResponseInterface $response)
    {
        return $this->error($response) === null;
    }

    /**
     * Why Slack rejected the message, or null when it did not.
     *
     * The Web API names its errors - channel_not_found, invalid_auth, not_in_channel. A webhook
     * puts a short reason in the body of a non-2xx response, and the status stands in when it
     * sends none.
     *
     * @param  \Psr\Http\Message\ResponseInterface  $response
     * @return string|null
     */
    public function error(ResponseInterface $response)
    {
        $body = static::readBody($response);

        $decoded = json_decode($body, true);

        if (is_array($decoded) && array_key_exists('ok', $decoded)) {
            if ($decoded['ok'] === true) {
                return null;
            }

            $error = $decoded['error'] ?? null;

            return is_string($error) && $error !== '' ? $error : 'unknown_error';
        }

        $status = $response->getStatusCode();

        if ($status >= 200 && $status < 300) {
            return null;
        }

        $reason = trim(substr($body, 0, 200));

        return $reason !== '' ? $reason : (string) $status;
    }

    /**
     * Read a response body without consuming it for the caller.
     *
     * Casting a stream to string seeks to the beginning first, so the read itself is safe
     * wherever the caller left the pointer. It ends at EOF though, and getContents() does not
     * rewind - so put it back, or a caller that reads the body after us gets nothing.
     *
     * @param  \Psr\Http\Message\ResponseInterface  $response
     * @return string
     */
    protected static function readBody(ResponseInterface $response)
    {
        $stream = $response->getBody();

        $body = (string) $stream;

        if ($stream->isSeekable()) {
            $stream->rewind();
        }

        return $body;
    }

    /**
     * Unwrap a payload built by version 1, which returned Guzzle request options.
     *
     * A payload built before the upgrade may still be waiting in a queue.
     *
     * @param  array<array-key, mixed>  $payload
     * @return array<array-key, mixed>
     */
    protected static function unwrapPayload(array $payload)
    {
        if (isset($payload['json']) && is_array($payload['json'])) {
            return $payload['json'];
        }

        return $payload;
    }

    /**
     * Build up the payload for the Slack webhook.
     *
     * @param  SlackMessage  $message
     * @return array<string, mixed>
     */
    public function buildPayload(SlackMessage $message)
    {
        $optionalFields = array_filter([
            'channel' => $message->channel,
            'icon_emoji' => $message->icon,
            'icon_url' => $message->image,
            'link_names' => $message->linkNames,
            'unfurl_links' => $message->unfurlLinks,
            'unfurl_media' => $message->unfurlMedia,
            'username' => $message->username,
        ]);

        return array_merge([
            'text' => $message->content,
            'attachments' => $this->attachments($message),
        ], $optionalFields);
    }

    /**
     * Format the message's attachments.
     *
     * @param  SlackMessage  $message
     * @return array<int, array<string, mixed>>
     */
    protected function attachments(SlackMessage $message)
    {
        return $this->map($message->attachments, function ($attachment) use ($message) {
            return array_filter([
                'author_icon' => $attachment->authorIcon,
                'author_link' => $attachment->authorLink,
                'author_name' => $attachment->authorName,
                'color' => $attachment->color ?: $message->color(),
                'fallback' => $attachment->fallback,
                'fields' => $this->fields($attachment),
                'footer' => $attachment->footer,
                'footer_icon' => $attachment->footerIcon,
                'image_url' => $attachment->imageUrl,
                'mrkdwn_in' => $attachment->markdown,
                'pretext' => $attachment->pretext,
                'text' => $attachment->content,
                'thumb_url' => $attachment->thumbUrl,
                'title' => $attachment->title,
                'title_link' => $attachment->url,
                'ts' => $attachment->timestamp,
            ]);
        });
    }

    /**
     * Format the attachment's fields.
     *
     * @param  SlackAttachment  $attachment
     * @return array<int, mixed>|null
     */
    protected function fields(SlackAttachment $attachment)
    {
        if (!is_array($attachment->fields)) {
            return null;
        }

        return array_values($this->map($attachment->fields, function ($value, $key) {
            if ($value instanceof SlackAttachmentField) {
                return $value->toArray();
            }

            return ['title' => $key, 'value' => $value, 'short' => true];
        }));
    }

    /**
     * Run a map over each of the items.
     *
     * @template TKey of array-key
     * @template TValue
     * @template TResult
     *
     * @param  array<TKey, TValue>  $fields
     * @param  callable(TValue, TKey): TResult  $callback
     * @return array<TKey, TResult>
     */
    public function map(array $fields, callable $callback)
    {
        $keys = array_keys($fields);

        $items = array_map($callback, $fields, $keys);

        return array_combine($keys, $items);
    }
}
