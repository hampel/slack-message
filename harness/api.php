<?php

/**
 * Exercise: post through the Web API instead of a webhook.
 *
 * An incoming webhook discards username, icon and channel. chat.postMessage honours all three,
 * given a bot token with the chat:write.customize scope - and it reports failure as "ok": false
 * inside a 200, which is the case accepted() exists for. Both are shown here.
 *
 * Needs SLACK_BOT_TOKEN, and SLACK_CHANNEL for somewhere to post.
 *
 * @var Hampel\Rig\Io $io
 */

use GuzzleHttp\Client;
use Hampel\SlackMessage\SlackWebhook;
use Psr\Http\Message\RequestInterface;

require_once __DIR__ . '/lib/harness.php';

$io->title('slack-message · api');

[$deliver, $mode] = Harness::mayDeliver();

Harness::announce($io, $mode);

if (! $deliver) {
    $io->info('  The app needs the chat:write, chat:write.public and chat:write.customize scopes.');
    $io->line();
}

$token = Harness::credential($io, $deliver, 'SLACK_BOT_TOKEN', 'bot-token-not-set');
$channel = Harness::credential($io, $deliver, 'SLACK_CHANNEL', '#general');

$url = 'https://slack.com/api/chat.postMessage';

// Slack answers chat.postMessage with a 200 whatever happens, so the sink has to as well -
// a canned 200 carrying ok, and a refusal for the channel the second attempt invents. That
// keeps accepted() and error() on the same code path they take for real, and it is the one
// thing in a sink run that is imitation rather than observation.
$sink = $deliver ? null : new HarnessSink(function (RequestInterface $request) {
    $body = json_decode((string) $request->getBody(), true);
    $to = is_array($body) ? ($body['channel'] ?? '') : '';

    return str_starts_with((string) $to, '#no-such-channel-')
        ? [200, '{"ok":false,"error":"channel_not_found"}']
        : [200, '{"ok":true}'];
});

$slack = new SlackWebhook($deliver ? new Client() : $sink);

$authorised = ['headers' => ['Authorization' => 'Bearer ' . $token]];

$message = $slack->message(function ($message) use ($channel, $authorised) {
    $message
        ->from('Slack Message', ':label:')
        ->to($channel)
        ->success()
        ->content('Sent by `vendor/bin/rig api`, through chat.postMessage.')
        ->attachment(function ($attachment) {
            $attachment
                ->title('Sender and channel')
                ->content('A webhook would have discarded all three of these.');
        })
        ->http($authorised);
});

$io->info('  the fields a webhook throws away');
$payload = $slack->buildPayload($message);
$io->value('username', $payload['username'] ?? '(not set)');
$io->value('icon_emoji', $payload['icon_emoji'] ?? '(not set)');
$io->value('channel', $payload['channel'] ?? '(not set)');

$io->line();

$io->attempt(($deliver ? 'post to ' : 'build a request for ') . $channel, function () use ($slack, $url, $message, $io) {
    $response = $slack->send($url, $message);

    $io->value('status', $response->getStatusCode());
    $io->value('accepted', $slack->accepted($response));
    $io->value('error', $slack->error($response));

    return $slack->accepted($response) ? 'delivered' : 'refused';
});

$io->line();
$io->info('  and the same thing to a channel that does not exist, which the Web API answers');
$io->info('  with a 200. Read the status alone and this looks like a delivery.');
$io->line();

$refused = $slack->message(function ($message) use ($authorised) {
    $message->to('#no-such-channel-' . bin2hex(random_bytes(4)))
        ->content('This one should not arrive.')
        ->http($authorised);
});

$io->attempt(
    $deliver ? 'post to a channel that does not exist' : 'build a request for a channel that does not exist',
    function () use ($slack, $url, $refused, $io) {
        $response = $slack->send($url, $refused);

        $io->value('status', $response->getStatusCode());
        $io->value('accepted', $slack->accepted($response));
        $io->value('error', $slack->error($response));

        return $slack->accepted($response) ? 'delivered' : 'refused';
    }
);

if ($sink !== null) {
    Harness::showRequests($io, $sink);

    $io->line();
    $io->warn('  Nothing was posted. The requests above are what Slack would have received, but');
    $io->warn('  the two responses were written here, not by Slack - so this run shows that');
    $io->warn('  accepted() reads ok, and not that chat.postMessage still answers that way.');
}
