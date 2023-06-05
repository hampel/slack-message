<?php namespace Hampel\SlackMessage;

use GuzzleHttp\Psr7\Response;
use Mockery as m;
use PHPUnit\Framework\TestCase;
use Illuminate\Notifications\Notification;

class SlackMessageTest extends TestCase
{
	use \Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

    /**
     * @var \Illuminate\Notifications\Channels\SlackWebhookChannel
     */
    private $slackChannel;

    /**
     * @var \Hampel\SlackMessage\SlackWebhook
     */
    private $slackWebhook;

    /**
     * @var MockInterface|\GuzzleHttp\Client
     */
    private $guzzleHttp;

    protected function setUp() : void
    {
        parent::setUp();
        $this->guzzleHttp = m::mock('GuzzleHttp\Client');
        $this->slackChannel = new \Illuminate\Notifications\Channels\SlackWebhookChannel($this->guzzleHttp);
        $this->slackWebhook = new \Hampel\SlackMessage\SlackWebhook($this->guzzleHttp);
    }

    /**
     * @dataProvider payloadDataProviderLaravel
     * @param Notification $notification
     * @param array $payload
     */
    public function testCorrectPayloadIsSentToSlackLaravel(Notification $notification, array $payload)
    {
        $this->guzzleHttp->shouldReceive('post')->andReturnUsing(function ($argUrl, $argPayload) use ($payload) {
            $this->assertEquals($argUrl, 'url');
            $this->assertEquals($argPayload, $payload);
            return new Response();
        });
        $this->slackChannel->send(new NotificationSlackChannelTestNotifiable, $notification);
    }

    /**
     * @dataProvider payloadDataProviderStandalone
     * @param Notification $notification
     * @param array $payload
     */
    public function testCorrectPayloadIsSentToSlackStandalone(SlackMessage $message, array $payload)
    {
        $this->guzzleHttp->shouldReceive('post')->andReturnUsing(function ($argUrl, $argPayload) use ($payload) {
        	$this->assertEquals($argUrl, 'url');
            $this->assertEquals($argPayload, $payload);
            return new Response();
        });
        $this->slackWebhook->send('url', $message);
    }

    public static function payloadDataProviderLaravel()
    {
        return [
            'payloadWithIcon' => self::getPayloadWithIcon(),
            'payloadWithImageIcon' => self::getPayloadWithImageIcon(),
            'payloadWithoutOptionalFields' => self::getPayloadWithoutOptionalFields(),
            'payloadWithoutFields' => self::getPayloadWithoutFields(),
            'payloadWithAttachmentFieldBuilder' => self::getPayloadWithAttachmentFieldBuilder(),
        ];
    }

    public static function payloadDataProviderStandalone()
    {
    	$payloadWithIcon = self::getPayloadWithIcon();
    	$payloadWithIcon[0] = SlackMessage::fromLaravel($payloadWithIcon[0]->toSlack(new NotificationSlackChannelTestNotifiable));

    	$payloadWithImageIcon = self::getPayloadWithImageIcon();
    	$payloadWithImageIcon[0] = SlackMessage::fromLaravel($payloadWithImageIcon[0]->toSlack(new NotificationSlackChannelTestNotifiable));

    	$payloadWithoutOptionalFields = self::getPayloadWithoutOptionalFields();
    	$payloadWithoutOptionalFields[0] = SlackMessage::fromLaravel($payloadWithoutOptionalFields[0]->toSlack(new NotificationSlackChannelTestNotifiable));

    	$payloadWithoutFields = self::getPayloadWithoutFields();
    	$payloadWithoutFields[0] = SlackMessage::fromLaravel($payloadWithoutFields[0]->toSlack(new NotificationSlackChannelTestNotifiable));

    	$payloadWithoutFieldsStandalone = self::getPayloadWithoutFieldsStandalone();

    	$payloadWithAttachmentFieldBuilder = self::getPayloadWithAttachmentFieldBuilder();
    	$payloadWithAttachmentFieldBuilder[0] = SlackMessage::fromLaravel($payloadWithAttachmentFieldBuilder[0]->toSlack(new NotificationSlackChannelTestNotifiable));

        return compact('payloadWithIcon', 'payloadWithImageIcon', 'payloadWithoutOptionalFields', 'payloadWithoutFields', 'payloadWithoutFieldsStandalone', 'payloadWithAttachmentFieldBuilder');
    }

    private static function getPayloadWithIcon()
    {
        return [
            new NotificationSlackChannelTestNotification,
            [
                'json' => [
                    'username' => 'Ghostbot',
                    'icon_emoji' => ':ghost:',
                    'channel' => '#ghost-talk',
                    'text' => 'Content',
                    'attachments' => [
                        [
                            'title' => 'Laravel',
                            'title_link' => 'https://laravel.com',
                            'text' => 'Attachment Content',
                            'fallback' => 'Attachment Fallback',
                            'fields' => [
                                [
                                    'title' => 'Project',
                                    'value' => 'Laravel',
                                    'short' => true,
                                ],
                            ],
                            'mrkdwn_in' => ['text'],
                            'footer' => 'Laravel',
                            'footer_icon' => 'https://laravel.com/fake.png',
                            'author_name' => 'Author',
                            'author_link' => 'https://laravel.com/fake_author',
                            'author_icon' => 'https://laravel.com/fake_author.png',
                            'ts' => 1234567890,
                        ],
                    ],
                ],
            ],
        ];
    }
    private static function getPayloadWithImageIcon()
    {
        return [
            new NotificationSlackChannelTestNotificationWithImageIcon,
            [
                'json' => [
                    'username' => 'Ghostbot',
                    'icon_url' => 'http://example.com/image.png',
                    'channel' => '#ghost-talk',
                    'text' => 'Content',
                    'attachments' => [
                        [
                            'title' => 'Laravel',
                            'title_link' => 'https://laravel.com',
                            'text' => 'Attachment Content',
                            'fallback' => 'Attachment Fallback',
                            'fields' => [
                                [
                                    'title' => 'Project',
                                    'value' => 'Laravel',
                                    'short' => true,
                                ],
                            ],
                            'mrkdwn_in' => ['text'],
                            'footer' => 'Laravel',
                            'footer_icon' => 'https://laravel.com/fake.png',
                            'ts' => 1234567890,
                        ],
                    ],
                ],
            ],
        ];
    }
    private static function getPayloadWithoutOptionalFields()
    {
        return [
            new NotificationSlackChannelWithoutOptionalFieldsTestNotification,
            [
                'json' => [
                    'text' => 'Content',
                    'attachments' => [
                        [
                            'title' => 'Laravel',
                            'title_link' => 'https://laravel.com',
                            'text' => 'Attachment Content',
                            'fields' => [
                                [
                                    'title' => 'Project',
                                    'value' => 'Laravel',
                                    'short' => true,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
    private static function getPayloadWithoutFields()
    {
        return [
            new NotificationSlackChannelWithoutFieldsTestNotification,
            [
                'json' => [
                    'text' => 'Content',
                    'attachments' => [
                        [
                            'title' => 'Laravel',
                            'title_link' => 'https://laravel.com',
                            'text' => 'Attachment Content',
                        ],
                    ],
                ],
            ],
        ];
    }
    private static function getPayloadWithoutFieldsStandalone()
    {
        return [
             (new SlackMessage())->content('Content')
                                 ->attachment(function ($attachment) {
                                 	    $attachment->title('Laravel', 'https://laravel.com')
                                                   ->content('Attachment Content');
                                 }),
            [
                'json' => [
                    'text' => 'Content',
                    'attachments' => [
                        [
                            'title' => 'Laravel',
                            'title_link' => 'https://laravel.com',
                            'text' => 'Attachment Content',
                        ],
                    ],
                ],
            ],
        ];
    }
    public static function getPayloadWithAttachmentFieldBuilder()
    {
        return [
            new NotificationSlackChannelWithAttachmentFieldBuilderTestNotification,
            [
                'json' => [
                    'text' => 'Content',
                    'attachments' => [
                        [
                            'title' => 'Laravel',
                            'text' => 'Attachment Content',
                            'title_link' => 'https://laravel.com',
                            'fields' => [
                                [
                                    'title' => 'Project',
                                    'value' => 'Laravel',
                                    'short' => true,
                                ],
                                [
                                    'title' => 'Special powers',
                                    'value' => 'Zonda',
                                    'short' => false,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}

class NotificationSlackChannelTestNotifiable
{
    use \Illuminate\Notifications\Notifiable;
    public function routeNotificationForSlack()
    {
        return 'url';
    }
}
class NotificationSlackChannelTestNotification extends Notification
{
    public function toSlack($notifiable)
    {
        return (new \Illuminate\Notifications\Messages\SlackMessage())
                    ->from('Ghostbot', ':ghost:')
                    ->to('#ghost-talk')
                    ->content('Content')
                    ->attachment(function ($attachment) {
                        $timestamp = m::mock(\Illuminate\Support\Carbon::class);
                        $timestamp->shouldReceive('getTimestamp')->andReturn(1234567890);
                        $attachment->title('Laravel', 'https://laravel.com')
                                   ->content('Attachment Content')
                                   ->fallback('Attachment Fallback')
                                   ->fields([
                                        'Project' => 'Laravel',
                                    ])
                                    ->footer('Laravel')
                                    ->footerIcon('https://laravel.com/fake.png')
                                    ->markdown(['text'])
                                    ->author('Author', 'https://laravel.com/fake_author', 'https://laravel.com/fake_author.png')
                                    ->timestamp($timestamp);
                    });
    }
}
class NotificationSlackChannelTestNotificationWithImageIcon extends Notification
{
    public function toSlack($notifiable)
    {
        return (new \Illuminate\Notifications\Messages\SlackMessage())
                    ->from('Ghostbot')
                    ->image('http://example.com/image.png')
                    ->to('#ghost-talk')
                    ->content('Content')
                    ->attachment(function ($attachment) {
                        $timestamp = m::mock(\Illuminate\Support\Carbon::class);
                        $timestamp->shouldReceive('getTimestamp')->andReturn(1234567890);
                        $attachment->title('Laravel', 'https://laravel.com')
                                   ->content('Attachment Content')
                                   ->fallback('Attachment Fallback')
                                   ->fields([
                                        'Project' => 'Laravel',
                                    ])
                                    ->footer('Laravel')
                                    ->footerIcon('https://laravel.com/fake.png')
                                    ->markdown(['text'])
                                    ->timestamp($timestamp);
                    });
    }
}
class NotificationSlackChannelWithoutOptionalFieldsTestNotification extends Notification
{
    public function toSlack($notifiable)
    {
        return (new \Illuminate\Notifications\Messages\SlackMessage())
                    ->content('Content')
                    ->attachment(function ($attachment) {
                        $attachment->title('Laravel', 'https://laravel.com')
                                   ->content('Attachment Content')
                                   ->fields([
                                        'Project' => 'Laravel',
                                    ]);
                    });
    }
}
class NotificationSlackChannelWithoutFieldsTestNotification extends Notification
{
    public function toSlack($notifiable)
    {
        return (new \Illuminate\Notifications\Messages\SlackMessage())
                    ->content('Content')
                    ->attachment(function ($attachment) {
                        $attachment->title('Laravel', 'https://laravel.com')
                                   ->content('Attachment Content');
                    });
    }
}
class NotificationSlackChannelWithAttachmentFieldBuilderTestNotification extends Notification
{
    public function toSlack($notifiable)
    {
        return (new \Illuminate\Notifications\Messages\SlackMessage())
            ->content('Content')
            ->attachment(function ($attachment) {
                $attachment->title('Laravel', 'https://laravel.com')
                    ->content('Attachment Content')
                    ->field('Project', 'Laravel')
                    ->field(function ($attachmentField) {
                        $attachmentField
                            ->title('Special powers')
                            ->content('Zonda')
                            ->long();
                    });
            });
    }
}
