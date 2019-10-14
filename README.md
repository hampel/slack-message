Slack Message Builder
=====================

Standalone implementation of Laravel's SlackMessage classes from 
[illuminate/notifications](https://github.com/illuminate/notifications).

This package provides a mechanism for generating correctly formatted Slack messages and sending them via Guzzle. Ideal 
for use with simple Slack [inbound webhooks](https://api.slack.com/incoming-webhooks), but can also be used with API 
calls.

By [Simon Hampel](https://twitter.com/SimonHampel) based on code by [Taylor Otwell](mailto:taylor@laravel.com) and licensed 
under the [MIT license](https://opensource.org/licenses/MIT).

Prerequisites
-------------

You will need to supply a Guzzle client (v6.x) to send the Slack messages.

Installation
------------

The recommended way of installing the Slack Message Builder package is through [Composer](http://getcomposer.org):

Require the package via Composer in your `composer.json`

    :::json
    {
        "require": {
            "hampel/slack-message": "~1.0"
        }
    }

Run Composer to update the new requirement.

    :::bash
    $ composer update

Usage
-----

Refer to Laravel's [Slack Notifications](https://laravel.com/docs/6.x/notifications#slack-notifications) documentation 
for information on generating Slack messages. The syntax is largely the same as that used by Laravel, but we do not 
need to use Notifiable classes - we can generate and send our Slack Messages directly.

	:::php
	use Carbon\Carbon;
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
					->timestamp(Carbon::now());
			});
	});
	
	$slack->send($url, $message);

References
----------

* [Slack API documentation](https://api.slack.com/)
* Slack API: [An introduction to messages](https://api.slack.com/docs/messages)
* Laravel: [Slack Notifications](https://laravel.com/docs/6.x/notifications#slack-notifications)
* Laravel Package: [laravel/slack-notification-channel](https://github.com/laravel/slack-notification-channel) 
