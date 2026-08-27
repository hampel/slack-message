<?php

/**
 * Exercise: list the channels a bot token can post in.
 *
 * For a config UI that lets someone choose a destination, rather than typing a channel name that
 * breaks when the channel is renamed. Stores well as an ID, displays well as a name.
 *
 * The scopes decide the answer, so little filtering is needed here: channels:read returns every
 * public channel, which chat:write.public makes postable without joining, and groups:read returns
 * only the private channels the app has been added to - which is exactly the private set it can
 * post in.
 *
 * Needs SLACK_BOT_TOKEN, with channels:read and groups:read on top of the sending scopes.
 *
 * @var Hampel\Rig\Io $io
 */

use GuzzleHttp\Client;
use Hampel\SlackMessage\SlackWebhook;

$io->title('slack-message · channels');

$token = getenv('SLACK_BOT_TOKEN');

if ($token === false || $token === '') {
    $io->error('SLACK_BOT_TOKEN is not set. Copy .env.example to .env beside the package.');
    $io->info('  The README covers how to obtain one, under Setting up Slack credentials.');

    exit(1);
}

$client = new Client();

// Only for accepted() and error(): they read the ok field, which every Web API method returns,
// so a read gets the same error handling as a send.
$slack = new SlackWebhook($client);

$channels = [];
$cursor = '';
$pages = 0;

do {
    $pages++;

    $response = $client->request('GET', 'https://slack.com/api/conversations.list', [
        'headers' => ['Authorization' => 'Bearer ' . $token],
        'query' => array_filter([
            'types' => 'public_channel,private_channel',
            'exclude_archived' => 'true',
            'limit' => 200,
            'cursor' => $cursor,
        ]),
        'http_errors' => false,
    ]);

    if (! $slack->accepted($response)) {
        $error = $slack->error($response);

        $io->error('Slack refused the request: ' . $error);

        if ($error === 'missing_scope') {
            $io->info('  Add channels:read and groups:read to the app, then reinstall it.');
        }

        exit(1);
    }

    $body = json_decode((string) $response->getBody(), true);

    foreach ($body['channels'] ?? [] as $channel) {
        $channels[] = $channel;
    }

    $cursor = $body['response_metadata']['next_cursor'] ?? '';

    // Slack recommends no more than 200 at a time, so a large workspace really does paginate.
    // The cap is here so that a cursor that never empties cannot loop forever.
} while ($cursor !== '' && $pages < 20);

$io->values([
    'pages' => $pages,
    'channels' => count($channels),
]);

if ($channels === []) {
    $io->line();
    $io->warn('No channels came back. The token is valid but sees nothing.');

    exit(0);
}

usort($channels, function ($a, $b) {
    return strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
});

$io->line();
$io->info('  ' . str_pad('ID', 14) . str_pad('NAME', 34) . 'VISIBILITY');

$public = 0;
$unreachable = [];

foreach ($channels as $channel) {
    $id = (string) ($channel['id'] ?? '?');
    $name = '#' . ($channel['name'] ?? '?');
    $private = ! empty($channel['is_private']);
    $member = ! empty($channel['is_member']);

    if (! $private) {
        $public++;
    }

    $visibility = $private ? 'private' : 'public';

    if ($private && ! $member) {
        $visibility .= ', not a member';
        $unreachable[] = $name;
    }

    $io->line('  ' . str_pad($id, 14) . str_pad($name, 34) . $visibility);
}

$io->line();
$io->values([
    'public' => $public,
    'private' => count($channels) - $public,
]);

$io->line();
$io->info('  Store the ID, not the name: a channel keeps its ID when it is renamed.');

if ($unreachable !== []) {
    $io->line();
    $io->warn('Listed but not postable without an invite: ' . implode(', ', $unreachable));
}
