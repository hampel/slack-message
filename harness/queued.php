<?php

/**
 * Exercise: build a payload here, send it as if from somewhere else.
 *
 * This is the path the XenForo add-on takes: build the payload in a web request, store it
 * in a job queue, send it from the job runner. The payload is JSON encoded and decoded in
 * between, which is what the queue does to it, and the round trip is where a payload that
 * is not plain data would be lost.
 *
 * Needs SLACK_WEBHOOK_URL.
 *
 * @var Hampel\Rig\Io $io
 */

use GuzzleHttp\Client;
use Hampel\SlackMessage\SlackWebhook;

$io->title('slack-message · queued');

$url = getenv('SLACK_WEBHOOK_URL');

if ($url === false || $url === '') {
    $io->error('SLACK_WEBHOOK_URL is not set. Copy .env.example to .env beside the package.');

    $io->info('  docs/slack-setup.md covers how to obtain one.');

    exit(1);
}

$slack = new SlackWebhook(new Client());

$message = $slack->message(function ($message) {
    $message
        ->from('Rig', ':wrench:')
        ->error()
        ->content('Sent by `vendor/bin/rig queued`, after a trip through JSON.')
        ->attachment(function ($attachment) {
            $attachment
                ->title('Queued payload')
                ->content('Built in one process, sent from another.')
                ->fields(['Encoded' => 'yes', 'Decoded' => 'yes'])
                ->timestamp(new DateTimeImmutable());
        });
});

// What the building process hands to the queue.
$built = $slack->buildPayload($message);

// What the sending process gets back out of it.
$queued = json_decode((string) json_encode($built), true);

$io->value('built', strlen((string) json_encode($built)) . ' bytes');
$io->value('survived', $queued === $built ? 'identical' : 'CHANGED IN TRANSIT');

$io->attempt('send the payload that came out of the queue', function () use ($slack, $url, $queued) {
    $response = $slack->sendPayload($url, $queued);

    return $response->getStatusCode() . ' ' . trim((string) $response->getBody());
});
