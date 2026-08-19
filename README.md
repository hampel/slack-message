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
with the [PSR-17](https://www.php-fig.org/psr/psr-17/) factories used to build the request. Guzzle (^7.0) provides
both, and is discovered automatically when the factories are not supplied:

```php
$slack = new SlackWebhook(new GuzzleHttp\Client());
```

Any other PSR-18 client works if you pass the factories yourself:

```php
$slack = new SlackWebhook($client, $requestFactory, $streamFactory);
```

Installation
------------

To install using composer, run the following command:

`composer require hampel/slack-message`

Usage
-----

Refer to Laravel's [Slack Notifications](https://laravel.com/docs/6.x/notifications#slack-notifications) documentation 
for information on generating Slack messages. The syntax is largely the same as that used by Laravel, but we do not 
need to use Notifiable classes - we can generate and send our Slack Messages directly.

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
message Slack will receive.

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
