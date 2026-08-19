<?php

/**
 * Exercise: post a message to a real Slack webhook and look at it.
 *
 * Whether Slack accepts the payload, and whether the result reads well in a channel, are
 * the two things no test can tell you. This message carries one attachment per level, so
 * the colours can be compared side by side.
 *
 * Needs SLACK_WEBHOOK_URL.
 *
 * @var Hampel\Rig\Io $io
 */

use GuzzleHttp\Client;
use Hampel\SlackMessage\SlackWebhook;

$io->title('slack-message · send');

$url = getenv('SLACK_WEBHOOK_URL');

if ($url === false || $url === '') {
    $io->error('SLACK_WEBHOOK_URL is not set. Put it in a .env file beside the package.');

    exit(1);
}

$slack = new SlackWebhook(new Client());

$message = $slack->message(function ($message) {
    $message
        ->from('Rig', ':wrench:')
        ->content('Sent by `vendor/bin/rig send`. One attachment per level.')
        ->attachment(function ($attachment) {
            $attachment->title('info')->content('info maps to no colour at all')->markdown(['text']);
        })
        ->attachment(function ($attachment) {
            $attachment->title('success')->content('`good`')->color('good')->markdown(['text']);
        })
        ->attachment(function ($attachment) {
            $attachment->title('warning')->content('`warning`')->color('warning')->markdown(['text']);
        })
        ->attachment(function ($attachment) {
            $attachment->title('error')->content('`danger`')->color('danger')->markdown(['text']);
        });
});

$io->value('bytes', strlen((string) json_encode($slack->buildPayload($message))));

$io->attempt('post to the webhook', function () use ($slack, $url, $message) {
    $response = $slack->send($url, $message);

    return $response->getStatusCode() . ' ' . trim((string) $response->getBody());
});

$io->line();
$io->info('  now go and look at the channel');
