<?php

/**
 * Exercise: build a payload here, post it to a real webhook as if from somewhere else.
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

require_once __DIR__ . '/lib/harness.php';

$io->title('slack-message · queued');

[$deliver, $mode] = Harness::mayDeliver();

Harness::announce($io, $mode);

$url = Harness::credential($io, $deliver, 'SLACK_WEBHOOK_URL', 'https://hooks.slack.example/webhook-url-not-set');

$sink = $deliver ? null : new HarnessSink();

$slack = new SlackWebhook($deliver ? new Client() : $sink);

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

$io->values([
    'built' => strlen((string) json_encode($built)) . ' bytes',
    'survived' => $queued === $built ? 'identical' : 'CHANGED IN TRANSIT',
]);

$io->attempt(
    $deliver ? 'send the payload that came out of the queue' : 'build the request from it, and stop',
    function () use ($slack, $url, $queued) {
        $response = $slack->sendPayload($url, $queued);

        return $response->getStatusCode() . ' ' . trim((string) $response->getBody());
    }
);

if ($sink !== null) {
    Harness::showRequests($io, $sink);

    $io->line();
    $io->warn('  Nothing was posted. The round trip above is real - it is plain JSON either way -');
    $io->warn('  but whether Slack accepts what came out of it is unanswered.');
}
