<?php

namespace Hampel\SlackMessage\Tests;

use Hampel\SlackMessage\SlackMessage;
use Hampel\SlackMessage\SlackWebhook;
use Mockery as m;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SlackMessageTest extends TestCase
{
    use \Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

    /**
     * @var \Hampel\SlackMessage\SlackWebhook
     */
    private $slackWebhook;

    protected function setUp(): void
    {
        parent::setUp();

        $this->slackWebhook = new SlackWebhook(m::mock('Psr\Http\Client\ClientInterface'));
    }

    /**
     * These payloads were verified byte for byte against the payloads Laravel's own
     * SlackWebhookChannel builds, at laravel/framework 13.26.1 and
     * laravel/slack-notification-channel 3.10.0.
     */
    #[DataProvider('payloadDataProvider')]
    public function testCorrectPayloadIsBuilt(SlackMessage $message, array $payload)
    {
        $built = $this->slackWebhook->buildPayload($message);

        self::ksortDeep($built);
        self::ksortDeep($payload);

        $this->assertSame($payload, $built);
    }

    /**
     * Sort an array by key, recursively.
     *
     * Slack does not care what order the keys of a JSON object arrive in, so the payload
     * fixtures are written in reading order rather than the order buildPayload() emits.
     * Sorting both sides lets the comparison be strict about types and about the order of
     * list elements, which do matter.
     *
     * @param  array  $array
     *
     * @return void
     */
    private static function ksortDeep(array &$array)
    {
        ksort($array);

        foreach ($array as &$value) {
            if (is_array($value)) {
                self::ksortDeep($value);
            }
        }
    }

    public static function payloadDataProvider()
    {
        return [
            'payloadWithIcon' => self::getPayloadWithIcon(),
            'payloadWithImageIcon' => self::getPayloadWithImageIcon(),
            'payloadWithoutOptionalFields' => self::getPayloadWithoutOptionalFields(),
            'payloadWithoutFields' => self::getPayloadWithoutFields(),
            'payloadWithAttachmentFieldBuilder' => self::getPayloadWithAttachmentFieldBuilder(),
        ];
    }

    private static function getPayloadWithIcon()
    {
        return [
            (new SlackMessage())
                ->from('Ghostbot', ':ghost:')
                ->to('#ghost-talk')
                ->content('Content')
                ->attachment(function ($attachment) {
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
                        ->timestamp(new \DateTimeImmutable('@1234567890'));
                }),
            [
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
        ];
    }

    private static function getPayloadWithImageIcon()
    {
        return [
            (new SlackMessage())
                ->from('Ghostbot')
                ->image('http://example.com/image.png')
                ->to('#ghost-talk')
                ->content('Content')
                ->attachment(function ($attachment) {
                    $attachment->title('Laravel', 'https://laravel.com')
                        ->content('Attachment Content')
                        ->fallback('Attachment Fallback')
                        ->fields([
                            'Project' => 'Laravel',
                        ])
                        ->footer('Laravel')
                        ->footerIcon('https://laravel.com/fake.png')
                        ->markdown(['text'])
                        ->timestamp(new \DateTimeImmutable('@1234567890'));
                }),
            [
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
        ];
    }

    private static function getPayloadWithoutOptionalFields()
    {
        return [
            (new SlackMessage())
                ->content('Content')
                ->attachment(function ($attachment) {
                    $attachment->title('Laravel', 'https://laravel.com')
                        ->content('Attachment Content')
                        ->fields([
                            'Project' => 'Laravel',
                        ]);
                }),
            [
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
        ];
    }

    private static function getPayloadWithoutFields()
    {
        return [
            (new SlackMessage())
                ->content('Content')
                ->attachment(function ($attachment) {
                    $attachment->title('Laravel', 'https://laravel.com')
                        ->content('Attachment Content');
                }),
            [
                'text' => 'Content',
                'attachments' => [
                    [
                        'title' => 'Laravel',
                        'title_link' => 'https://laravel.com',
                        'text' => 'Attachment Content',
                    ],
                ],
            ],
        ];
    }

    private static function getPayloadWithAttachmentFieldBuilder()
    {
        return [
            (new SlackMessage())
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
                }),
            [
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
        ];
    }
}
