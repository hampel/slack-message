<?php

declare(strict_types=1);

namespace Hampel\SlackMessage\Tests;

use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use Hampel\SlackMessage\SlackMessage;
use Hampel\SlackMessage\SlackWebhook;
use Mockery as m;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class SlackWebhookTest extends TestCase
{
    use \Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

    /**
     * @var MockInterface|\Psr\Http\Client\ClientInterface
     */
    private $http;

    /**
     * @var \Hampel\SlackMessage\SlackWebhook
     */
    private $webhook;

    /**
     * The request the client was last asked to send.
     *
     * @var \Psr\Http\Message\RequestInterface|null
     */
    private $request;

    protected function setUp(): void
    {
        parent::setUp();

        $this->request = null;

        $this->http = m::mock('Psr\Http\Client\ClientInterface');
        $this->http->shouldReceive('sendRequest')->andReturnUsing(function (RequestInterface $request) {
            $this->request = $request;

            return new Response();
        });

        $this->webhook = new SlackWebhook($this->http);
    }

    private function message($content = 'Content')
    {
        return (new SlackMessage())->content($content);
    }

    private function attached(SlackMessage $message)
    {
        $payload = $this->webhook->buildPayload($message);

        return $payload['attachments'][0];
    }

    public function testMessageBuildsAMessageWithACallback()
    {
        $message = $this->webhook->message(function ($message) {
            $message->content('Content')->to('#ghost-talk');
        });

        $this->assertInstanceOf(SlackMessage::class, $message);
        $this->assertSame('Content', $message->content);
        $this->assertSame('#ghost-talk', $message->channel);
    }

    #[DataProvider('levels')]
    public function testTheMessageLevelColoursTheAttachment($level, $colour)
    {
        $message = $this->message()->attachment(function ($attachment) {
            $attachment->content('Attachment Content');
        });

        if ($level !== null) {
            $message->{$level}();
        }

        $attachment = $this->attached($message);

        if ($colour === null) {
            $this->assertArrayNotHasKey('color', $attachment);
        } else {
            $this->assertSame($colour, $attachment['color']);
        }
    }

    public static function levels()
    {
        return [
            'no level' => [null, null],
            'info' => ['info', null],
            'success' => ['success', 'good'],
            'warning' => ['warning', 'warning'],
            'error' => ['error', 'danger'],
        ];
    }

    public function testAnAttachmentColourOverridesTheMessageLevel()
    {
        $message = $this->message()->error()
            ->attachment(function ($attachment) {
                $attachment->content('One')->color('#439FE0');
            })
            ->attachment(function ($attachment) {
                $attachment->content('Two');
            });

        $payload = $this->webhook->buildPayload($message);

        $this->assertSame('#439FE0', $payload['attachments'][0]['color']);
        $this->assertSame('danger', $payload['attachments'][1]['color']);
    }

    public function testLinkNamesIsOmittedUntilItIsAskedFor()
    {
        $message = $this->message();

        $this->assertArrayNotHasKey('link_names', $this->webhook->buildPayload($message));

        $this->assertSame(1, $this->webhook->buildPayload($message->linkNames())['link_names']);
    }

    public function testUnfurlingIsSentWhenTurnedOn()
    {
        $payload = $this->webhook->buildPayload($this->message()->unfurlLinks(true)->unfurlMedia(true));

        $this->assertTrue($payload['unfurl_links']);
        $this->assertTrue($payload['unfurl_media']);
    }

    public function testUnfurlingIsOmittedWhenTurnedOff()
    {
        // false is dropped along with null, so turning unfurling off and never asking for it
        // reach Slack as the same message
        $payload = $this->webhook->buildPayload($this->message()->unfurlLinks(false)->unfurlMedia(false));

        $this->assertArrayNotHasKey('unfurl_links', $payload);
        $this->assertArrayNotHasKey('unfurl_media', $payload);
    }

    public function testAnAttachmentsPretextImageAndThumbnailAreMapped()
    {
        $attachment = $this->attached($this->message()->attachment(function ($attachment) {
            $attachment->content('Attachment Content')
                ->pretext('Pretext')
                ->image('https://laravel.com/image.png')
                ->thumb('https://laravel.com/thumb.png');
        }));

        $this->assertSame('Pretext', $attachment['pretext']);
        $this->assertSame('https://laravel.com/image.png', $attachment['image_url']);
        $this->assertSame('https://laravel.com/thumb.png', $attachment['thumb_url']);
    }

    public function testTheRequestIsAJsonPostToTheWebhookUrl()
    {
        $this->webhook->send('https://hooks.slack.test/webhook', $this->message());

        $this->assertSame('POST', $this->request->getMethod());
        $this->assertSame('https://hooks.slack.test/webhook', (string) $this->request->getUri());
        $this->assertSame('application/json', $this->request->getHeaderLine('Content-Type'));
        $this->assertSame('{"text":"Content","attachments":[]}', (string) $this->request->getBody());
    }

    public function testSendReturnsTheResponse()
    {
        $response = $this->webhook->send('https://hooks.slack.test/webhook', $this->message());

        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testAPayloadSurvivesBeingQueued()
    {
        $message = $this->message()->error()->to('#ops')->attachment(function ($attachment) {
            $attachment->title('Laravel', 'https://laravel.com')
                ->fields(['Project' => 'Laravel'])
                ->timestamp(new \DateTimeImmutable('@1234567890'));
        });

        $payload = $this->webhook->buildPayload($message);
        $queued = json_decode(json_encode($payload), true);

        $this->webhook->sendPayload('https://hooks.slack.test/webhook', $queued);

        $this->assertSame(json_encode($payload), (string) $this->request->getBody());
    }

    public function testAPayloadBuiltByVersionOneIsStillSent()
    {
        $payload = $this->webhook->buildPayload($this->message());

        $legacy = ['json' => $payload, 'timeout' => 5];

        $this->webhook->sendPayload('https://hooks.slack.test/webhook', $legacy);

        $this->assertSame(json_encode($payload), (string) $this->request->getBody());
    }

    public function testTransportHeadersAreAddedToTheRequest()
    {
        $message = $this->message()->http(['headers' => ['X-Trace-Id' => 'abc123']]);

        $this->webhook->send('https://hooks.slack.test/webhook', $message);

        $this->assertSame('abc123', $this->request->getHeaderLine('X-Trace-Id'));
        $this->assertSame('application/json', $this->request->getHeaderLine('Content-Type'));
    }

    public function testTransportOptionsOtherThanHeadersAreIgnored()
    {
        $message = $this->message()->http(['timeout' => 5, 'proxy' => 'tcp://localhost:8125']);

        $this->webhook->send('https://hooks.slack.test/webhook', $message);

        $this->assertSame('{"text":"Content","attachments":[]}', (string) $this->request->getBody());
    }

    public function testAHeaderThatIsNotAStringIsRejected()
    {
        $message = $this->message()->http(['headers' => ['X-Retries' => 3]]);

        $this->expectException(\InvalidArgumentException::class);

        $this->webhook->send('https://hooks.slack.test/webhook', $message);
    }

    public function testTheSuppliedFactoriesBuildTheRequest()
    {
        $guzzle = new HttpFactory();

        $requestFactory = m::mock('Psr\Http\Message\RequestFactoryInterface');
        $requestFactory->shouldReceive('createRequest')->once()
            ->with('POST', 'https://hooks.slack.test/webhook')
            ->andReturnUsing(function ($method, $uri) use ($guzzle) {
                return $guzzle->createRequest($method, $uri);
            });

        $streamFactory = m::mock('Psr\Http\Message\StreamFactoryInterface');
        $streamFactory->shouldReceive('createStream')->once()
            ->andReturnUsing(function ($body) use ($guzzle) {
                return $guzzle->createStream($body);
            });

        $webhook = new SlackWebhook($this->http, $requestFactory, $streamFactory);
        $webhook->send('https://hooks.slack.test/webhook', $this->message());

        $this->assertSame('{"text":"Content","attachments":[]}', (string) $this->request->getBody());
    }

    public function testGuzzlesFactoryIsFoundWhenNoneIsSupplied()
    {
        $webhook = new SlackWebhook($this->http);

        $webhook->send('https://hooks.slack.test/webhook', $this->message());

        $this->assertInstanceOf('GuzzleHttp\Psr7\Request', $this->request);
    }
}
