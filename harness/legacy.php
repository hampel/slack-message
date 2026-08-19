<?php

/**
 * Exercise: send a payload built by version 1.
 *
 * Version 1's buildJsonPayload() returned Guzzle request options, with the message under a
 * json key. A payload in that shape may still be sitting in a queue written before the
 * upgrade, so sendPayload() unwraps it. This is the upgrade path, against real Slack.
 *
 * Needs SLACK_WEBHOOK_URL.
 *
 * @var Hampel\Rig\Io $io
 */

use GuzzleHttp\Client;
use Hampel\SlackMessage\SlackWebhook;

$io->title('slack-message · legacy');

$url = getenv('SLACK_WEBHOOK_URL');

if ($url === false || $url === '') {
    $io->error('SLACK_WEBHOOK_URL is not set. Put it in a .env file beside the package.');

    exit(1);
}

$slack = new SlackWebhook(new Client());

$message = $slack->message(function ($message) {
    $message
        ->from('Rig', ':wrench:')
        ->warning()
        ->content('Sent by `vendor/bin/rig legacy`, from a version 1 shaped payload.')
        ->attachment(function ($attachment) {
            $attachment->title('Unwrapped')->content('The json key was stripped before sending.');
        });
});

// Exactly what version 1's buildJsonPayload() would have put in the queue: the payload
// under a json key, alongside Guzzle request options that no longer mean anything.
$legacy = [
    'json' => $slack->buildPayload($message),
    'timeout' => 5,
    'headers' => ['X-Sent-By' => 'rig'],
];

$io->value('queued', array_keys($legacy));
$io->value('sent', array_keys($slack->buildPayload($message)));

$io->line();
$io->info('  everything alongside the json key is discarded, headers included: there is no');
$io->info('  PSR-18 equivalent of a Guzzle request option. Pass headers to sendPayload() as');
$io->info('  its third argument instead.');
$io->line();

$io->attempt('send the version 1 payload', function () use ($slack, $url, $legacy) {
    $response = $slack->sendPayload($url, $legacy);

    return $response->getStatusCode() . ' ' . trim((string) $response->getBody());
});

$io->attempt('send it again, with the headers where they now belong', function () use ($slack, $url, $legacy) {
    $response = $slack->sendPayload($url, $legacy, ['headers' => ['X-Sent-By' => 'rig']]);

    return $response->getStatusCode() . ' ' . trim((string) $response->getBody());
});
