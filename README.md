Slack Message Builder
=====================

[![Tests](https://github.com/hampel/slack-message/actions/workflows/tests.yml/badge.svg)](https://github.com/hampel/slack-message/actions/workflows/tests.yml)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/hampel/slack-message.svg?style=flat-square)](https://packagist.org/packages/hampel/slack-message)
[![Total Downloads](https://img.shields.io/packagist/dt/hampel/slack-message.svg?style=flat-square)](https://packagist.org/packages/hampel/slack-message)
[![Open Issues](https://img.shields.io/github/issues-raw/hampel/slack-message.svg?style=flat-square)](https://github.com/hampel/slack-message/issues)
[![License](https://img.shields.io/packagist/l/hampel/slack-message.svg?style=flat-square)](https://packagist.org/packages/hampel/slack-message)

Standalone implementation of Laravel's SlackMessage classes from 
[illuminate/notifications](https://github.com/illuminate/notifications).

This package provides a mechanism for generating correctly formatted Slack messages and sending them over any 
PSR-18 HTTP client. Ideal for use with simple Slack [inbound webhooks](https://api.slack.com/incoming-webhooks), but 
can also be used with API calls.

It implements Slack's attachment-based message format, which Slack now describes as
[outmoded messaging](https://api.slack.com/legacy/outmoded-messaging) and Laravel has frozen. Attachments still work,
and for a one-way notification firehose they remain the simplest thing that does. If you need threading, editing a
message after posting, or interactive buttons, you want Slack's Block Kit and this is not the package for it.

By [Simon Hampel](mailto:simon@hampelgroup.com) based on code by [Taylor Otwell](mailto:taylor@laravel.com) and licensed 
under the [MIT license](https://opensource.org/licenses/MIT).

Prerequisites
-------------

PHP 8.3 or later.

You will need to supply a [PSR-18](https://www.php-fig.org/psr/psr-18/) HTTP client to send the Slack messages, along
with the [PSR-17](https://www.php-fig.org/psr/psr-17/) factories used to build the request. Guzzle provides
both, on either 7 or 8, and is discovered automatically when the factories are not supplied:

```php
$slack = new SlackWebhook(new GuzzleHttp\Client());
```

Any other PSR-18 client works if you pass the factories yourself:

```php
$slack = new SlackWebhook($client, $requestFactory, $streamFactory);
```

You will also need somewhere to send to — an incoming webhook URL, or a bot token for the Web API.
[Setting up Slack credentials](#setting-up-slack-credentials), at the bottom, walks through both.

Installation
------------

To install using composer, run the following command:

`composer require hampel/slack-message`

Usage
-----

Refer to Laravel's [Slack Notifications](https://laravel.com/docs/6.x/notifications#slack-notifications) documentation 
for information on generating Slack messages. The syntax is largely the same as that used by Laravel, but we do not 
need to use Notifiable classes - we can generate and send our Slack Messages directly.

### Sending to an incoming webhook

```php
use GuzzleHttp\Client;
use Hampel\SlackMessage\SlackMessage;
use Hampel\SlackMessage\SlackWebhook;

$url = 'https://hooks.slack.com/services/<Slack incoming webhook url>';
$slack = new SlackWebhook(new Client());

$message = $slack->message(function ($message) {
    $message
        ->content('Content')
        ->attachment(function ($attachment) {
            $attachment
                ->title('Laravel', 'https://laravel.com')
                ->content('Attachment Content')
                ->fallback('Attachment Fallback')
                ->fields([
                    'Project' => 'Laravel',
                ])
                ->footer('Laravel')
                ->footerIcon('https://laravel.com/fake.png')
                ->markdown(['text'])
                ->author('Author', 'https://laravel.com/fake_author', 'https://laravel.com/fake_author.png')
                ->timestamp(new DateTimeImmutable());
        });
});

$slack->send($url, $message);
```

### What an incoming webhook ignores

`from()`, `image()` and `to()` set Slack's `username`, `icon_emoji`/`icon_url` and `channel`
fields. A modern incoming webhook ignores all three:

> You cannot override the default channel (chosen by the user who installed your app), username,
> or icon when you're using incoming webhooks to post messages. Instead, these values will always
> inherit from the associated Slack app configuration.
>
> — [Sending messages using incoming webhooks](https://docs.slack.dev/messaging/sending-messages-using-incoming-webhooks)

The message posts as the Slack app the webhook belongs to, in the channel chosen when the app was
installed. Older custom-integration webhooks honoured these fields, which is why the builders
carry them. There is no configuration that restores the behaviour.

Slack's Web API does honour all three.

### Sending through the Web API

The same builders and the same payload work against Slack's Web API. Post to `chat.postMessage`
rather than to a webhook URL, and supply a bot token as an `Authorization` header:

```php
use GuzzleHttp\Client;
use Hampel\SlackMessage\SlackWebhook;

$slack = new SlackWebhook(new Client());

$message = $slack->message(function ($message) use ($token) {
    $message
        ->from('Deploy Bot', ':rocket:')
        ->to('#alerts')
        ->success()
        ->content('Deployed successfully.')
        ->http(['headers' => ['Authorization' => 'Bearer ' . $token]]);
});

$response = $slack->send('https://slack.com/api/chat.postMessage', $message);

if (! $slack->accepted($response)) {
    throw new RuntimeException('Slack refused the message: ' . $slack->error($response));
}
```

Two things this buys over a webhook:

* `to()` chooses the channel per message, so one credential reaches every channel — rather than
  one webhook URL per channel, each configured separately.
* `from()` and `image()` set the sender name and icon per message, so each kind of message can
  arrive under its own identity. Both need the `chat:write.customize` scope.

Checking the response matters more here than with a webhook, because `chat.postMessage` answers
`200` whether or not it accepted the message.

`http()` contributes request headers only. Timeouts, proxies and retries are the HTTP client's
business — configure them on the client you pass to the constructor.

### Did Slack accept it?

The two transports report failure differently. A webhook answers with an HTTP status; the Web API
answers `200` whatever happens and puts the outcome in an `ok` field — so a rejected message and a
delivered one look identical from the status alone. `accepted()` reads a response both ways, and
`error()` names the reason:

```php
$response = $slack->send($url, $message);

if (! $slack->accepted($response)) {
    // channel_not_found, invalid_auth, not_in_channel, invalid_payload ...
    throw new RuntimeException('Slack refused the message: ' . $slack->error($response));
}
```

Neither consumes the response: the body is left where the caller can still read it.

Building now, sending later
---------------------------

`send()` is `buildPayload()` followed by `sendPayload()`, and the two halves are public so that
they can run in different processes. `buildPayload()` returns the Slack payload as a plain array,
which survives being JSON encoded into a queue and read back out:

```php
$payload = $slack->buildPayload($message);

// ... store $payload, hand it to a job queue, come back later ...

$slack->sendPayload($url, $payload);
```

The payload itself carries nothing client-specific — no headers, no request options, just the
message Slack will receive. Headers, where they are needed, are `sendPayload()`'s third argument:

```php
$slack->sendPayload($url, $payload, ['headers' => ['Authorization' => 'Bearer ' . $token]]);
```

Version 1 built a payload in the shape of Guzzle request options, wrapped in a `json` key.
`sendPayload()` still accepts that shape, so a payload queued before an upgrade is not stranded.

References
----------

* [Slack API documentation](https://api.slack.com/)
* Slack API: [An introduction to messages](https://api.slack.com/docs/messages)
* Laravel: [Slack Notifications](https://laravel.com/docs/6.x/notifications#slack-notifications)
* Laravel Package: [laravel/slack-notification-channel](https://github.com/laravel/slack-notification-channel)

The Laravel documentation is linked at 6.x deliberately. It is the last version that documents the
attachment API this package implements; current versions document Block Kit instead and do not
mention attachments at all.

Setting up Slack credentials
----------------------------

The two routes into Slack are configured differently. Both start with a Slack app.

| | Incoming webhook | Bot token |
|---|---|---|
| Credential | a URL | a token, `xoxb-…` |
| Posts to | one channel, fixed at setup | any channel, chosen per message |
| Posts as | the app, always | the app, or whatever `from()` says |
| `from()`, `image()`, `to()` | ignored | honoured |
| Reports failure as | an HTTP status | `"ok": false` inside a `200` |
| Setup | a few clicks | scopes, an install, a secret to store |

A webhook is the right default. Reach for a token when you need to route messages to different
channels from one credential, or want each kind of message to post under its own name.

### Creating the app

Both routes need an app. Go to [api.slack.com/apps/new](https://api.slack.com/apps/new).

**Do not add bot scopes to an app whose incoming webhook is already in production.** Adding scopes
requires reinstalling the app, and Slack's documentation does not say whether an existing webhook
URL survives that. If a webhook URL is already configured somewhere and working, create a second
app rather than finding out.

### Route 1 — an incoming webhook

1. Create the app **From scratch**. Name it and pick the workspace.
2. In the left sidebar, select **Incoming Webhooks**.
3. Toggle **Activate Incoming Webhooks** on.
4. Click **Add New Webhook to Workspace**.
5. Choose the channel the webhook posts to, and **Authorize**.
6. Copy the URL. It looks like
   `https://hooks.slack.com/services/<team>/<webhook>/<token>`.

The URL is *"specific to a single user and a single channel"*. To post to another channel, repeat
steps 4 and 5 — one URL per channel.

### Route 2 — a bot token

1. Create the app **From an app manifest**, pick the workspace, and paste:

    ```json
    {
        "display_information": {
            "name": "Slack Message"
        },
        "features": {
            "bot_user": {
                "display_name": "Slack Message",
                "always_online": false
            }
        },
        "oauth_config": {
            "scopes": {
                "bot": [
                    "chat:write",
                    "chat:write.public",
                    "chat:write.customize"
                ]
            }
        },
        "settings": {
            "org_deploy_enabled": false,
            "socket_mode_enabled": false,
            "token_rotation_enabled": false
        }
    }
    ```

    A manifest sets the scopes for you. **From scratch** works too — then add the three scopes by
    hand under **OAuth & Permissions** → **Bot Token Scopes**.

2. **OAuth & Permissions** in the sidebar → **Install to Workspace** → authorise.
3. Back on that page, copy the **Bot User OAuth Token**, under **OAuth Tokens for Your Workspace**.
   It starts `xoxb-`.

#### What the scopes are for

| scope | |
|---|---|
| `chat:write` | the baseline; required by the other two |
| `chat:write.public` | *"Send messages to channels your Slack app isn't a member of"* — without it the bot must be invited to every channel |
| `chat:write.customize` | makes `from()` and `image()` work. Slack: *"a powerful ability… only available when an app has requested and been granted an additional scope"* |

### Keeping the credentials

Both are secrets. Slack *"actively searches out and revokes leaked secrets"*, so neither belongs in
a repository, and a webhook URL deserves the same care as a token.

### When something is refused

`error()` returns Slack's own name for the problem:

| | |
|---|---|
| `channel_not_found` | no such channel, or the name is wrong. A channel ID (`C01234567`) is more robust than a name, which changes when the channel is renamed |
| `not_in_channel` | a private channel the bot has not been invited to. `chat:write.public` covers public channels only — for a private one, `/invite @Your App` in it first |
| `invalid_auth` | the token is wrong, revoked, or missing its `Bearer ` prefix |
| `missing_scope` | the app was installed before the scope was added. Reinstall it |
| `invalid_payload` | webhook only: the JSON was malformed or the message empty |
| `no_service` | webhook only: the webhook has been deleted or the app uninstalled |

Silence with no error is usually the wrong channel rather than a failure — check which channel the
webhook was bound to at step 5.
