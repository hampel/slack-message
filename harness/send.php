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

require_once __DIR__ . '/lib/harness.php';

$io->title('slack-message · send');

[$deliver, $mode] = Harness::mayDeliver();

Harness::announce($io, $mode);

$url = Harness::credential($io, $deliver, 'SLACK_WEBHOOK_URL', 'https://hooks.slack.example/webhook-url-not-set');

$sink = $deliver ? null : new HarnessSink();

$slack = new SlackWebhook($deliver ? new Client() : $sink);

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

// attempt() prints its verdict once the callback has returned, so anything the callback
// prints lands above the line saying what was attempted. Carry the response back out and
// read it here instead.
$response = null;

$io->attempt($deliver ? 'post to the webhook' : 'build the request, and stop', function () use ($slack, $url, $message, &$response) {
    $response = $slack->send($url, $message);

    return trim((string) $response->getBody());
});

if ($response !== null) {
    $io->values([
        'status' => $response->getStatusCode(),
        'accepted' => $slack->accepted($response),
        'error' => $slack->error($response),
    ]);
}

if ($sink !== null) {
    Harness::showRequests($io, $sink);

    $io->line();
    $io->warn('  Nothing was posted, so this run says nothing about whether Slack accepts the');
    $io->warn('  payload or how it reads in a channel, which are the two questions it exists');
    $io->warn('  to answer. The status and body above are canned.');

    return;
}

$io->line();
$io->info('  now go and look at the channel');
