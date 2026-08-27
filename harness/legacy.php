<?php

/**
 * Exercise: post a payload built by version 1 to a real webhook.
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

require_once __DIR__ . '/lib/harness.php';

$io->title('slack-message · legacy');

[$deliver, $mode] = Harness::mayDeliver();

Harness::announce($io, $mode);

$url = Harness::credential($io, $deliver, 'SLACK_WEBHOOK_URL', 'https://hooks.slack.example/webhook-url-not-set');

$sink = $deliver ? null : new HarnessSink();

$slack = new SlackWebhook($deliver ? new Client() : $sink);

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

$io->values([
    'queued' => array_keys($legacy),
    'sent' => array_keys($slack->buildPayload($message)),
]);

$io->line();
$io->info('  everything alongside the json key is discarded, headers included: there is no');
$io->info('  PSR-18 equivalent of a Guzzle request option. Pass headers to sendPayload() as');
$io->info('  its third argument instead.');
$io->line();

$io->attempt($deliver ? 'send the version 1 payload' : 'build the request for it, and stop', function () use ($slack, $url, $legacy) {
    $response = $slack->sendPayload($url, $legacy);

    return $response->getStatusCode() . ' ' . trim((string) $response->getBody());
});

$io->attempt(
    $deliver ? 'send it again, with the headers where they now belong' : 'build it again, with the headers where they now belong',
    function () use ($slack, $url, $legacy) {
        $response = $slack->sendPayload($url, $legacy, ['headers' => ['X-Sent-By' => 'rig']]);

        return $response->getStatusCode() . ' ' . trim((string) $response->getBody());
    }
);

if ($sink !== null) {
    Harness::showRequests($io, $sink);

    $io->line();
    $io->warn('  Nothing was posted. This is the run where the sink earns its place: the first');
    $io->warn('  request carries no X-Sent-By and no json key, the second carries the header.');
    $io->warn('  Only Slack can say whether it accepts either.');
}
