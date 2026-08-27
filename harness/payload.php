<?php

/**
 * Exercise: what actually goes on the wire.
 *
 * Needs no credentials and sends nothing. The unit tests compare the payload against
 * fixtures, which tells you it has not changed; this shows you what it is, in the form
 * Slack receives it.
 *
 * @var Hampel\Rig\Io $io
 */

use GuzzleHttp\Client;
use Hampel\SlackMessage\SlackMessage;
use Hampel\SlackMessage\SlackWebhook;

$slack = new SlackWebhook(new Client());

$io->title('slack-message · payload');

$message = $slack->message(function ($message) {
    $message
        ->from('Rig', ':wrench:')
        ->to('#general')
        ->content('Everything a message can carry.')
        ->attachment(function ($attachment) {
            $attachment
                ->title('hampel/slack-message', 'https://github.com/hampel/slack-message')
                ->pretext('Pretext, above the attachment')
                ->content('Attachment content')
                ->fallback('Attachment fallback, for clients that cannot render it')
                ->fields([
                    'Project' => 'slack-message',
                    'Format' => 'attachments',
                ])
                ->field(function ($field) {
                    $field->title('A field of its own')->content('built with the field builder')->long();
                })
                ->markdown(['text'])
                ->author('Simon Hampel', 'https://github.com/hampel', 'https://github.com/hampel.png')
                ->footer('sent by rig')
                ->footerIcon('https://github.com/hampel.png')
                ->timestamp(new DateTimeImmutable());
        });
});

$io->info('  the JSON body, as Slack receives it');
$io->line();
$io->line(json_encode($slack->buildPayload($message), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

$io->line();
$io->info('  the level of a message tints its attachments');

$colours = [];

foreach (['(none)', 'info', 'success', 'warning', 'error'] as $level) {
    $message = new SlackMessage();

    if ($level !== '(none)') {
        $message->{$level}();
    }

    $message->content('Content')->attachment(function ($attachment) {
        $attachment->content('Attachment content');
    });

    $attachment = $slack->buildPayload($message)['attachments'][0];

    $colours[$level] = $attachment['color'] ?? '(no colour sent)';
}

// One call, so the five colours share a column and can be read against each other.
$io->values($colours);
