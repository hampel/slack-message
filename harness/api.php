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

$io->title('slack-message · api');

$token = getenv('SLACK_BOT_TOKEN');
$channel = getenv('SLACK_CHANNEL');

if ($token === false || $token === '') {
    $io->error('SLACK_BOT_TOKEN is not set. Put it in a .env file beside the package.');
    $io->info('  The app needs the chat:write, chat:write.public and chat:write.customize scopes.');

    exit(1);
}

if ($channel === false || $channel === '') {
    $io->error('SLACK_CHANNEL is not set. Put it in a .env file beside the package.');

    exit(1);
}

$url = 'https://slack.com/api/chat.postMessage';

$slack = new SlackWebhook(new Client());

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

$io->attempt('post to ' . $channel, function () use ($slack, $url, $message, $io) {
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

$io->attempt('post to a channel that does not exist', function () use ($slack, $url, $refused, $io) {
    $response = $slack->send($url, $refused);

    $io->value('status', $response->getStatusCode());
    $io->value('accepted', $slack->accepted($response));
    $io->value('error', $slack->error($response));

    return $slack->accepted($response) ? 'delivered' : 'refused';
});
