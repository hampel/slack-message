<?php namespace Hampel\SlackMessage;

/**
 * @see https://github.com/illuminate/notifications
 * @see https://github.com/illuminate/notifications/blob/master/Channels/SlackWebhookChannel.php
 * @see https://laravel.com/docs/5.6/notifications#slack-notifications
 */

use GuzzleHttp\Client;

class SlackWebhook
{
    /**
     * The HTTP client instance.
     *
     * @var \GuzzleHttp\Client
     */
    protected $http;

    /**
     * Create a new Slack channel instance.
     *
     * @param  \GuzzleHttp\Client  $http
     * @return void
     */
    public function __construct(Client $http)
    {
        $this->http = $http;
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
        return $this->sendPayload($url, $this->buildJsonPayload($message));
    }

    public function sendPayload($url, array $payload)
    {
    	return $this->http->post($url, $payload);
    }

    /**
     * Build up a JSON payload for the Slack webhook.
     *
     * @param  SlackMessage  $message
     * @return array
     */
    public function buildJsonPayload(SlackMessage $message)
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
            'json' => array_merge([
                'text' => $message->content,
                'attachments' => $this->attachments($message),
            ], $optionalFields),
        ], $message->http);
    }

    /**
     * Format the message's attachments.
     *
     * @param  SlackMessage  $message
     * @return array
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
     * @return array
     */
    protected function fields(SlackAttachment $attachment)
    {
    	if (!is_array($attachment->fields)) return;

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
     * @param  callable  $callback
     * @return static
     */
    public function map($fields, callable $callback)
    {
        $keys = array_keys($fields);

        $items = array_map($callback, $fields, $keys);

        return array_combine($keys, $items);
    }
}
