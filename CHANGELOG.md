CHANGELOG
=========

2.3.0 (2026-09-13)
------------------

### Added

* PSR-17 factories are found automatically from `nyholm/psr7` and `laminas/laminas-diactoros` as well
  as `guzzlehttp/psr7`

### Changed

* `SlackWebhook::discoverFactory()` returns the request factory and the stream factory as a pair. A
  subclass overriding it must return the same
* the exception thrown when no factory is found names the packages that are found automatically
* `ext-json` is declared in `require`

2.2.0 (2026-08-19)
------------------

### Fixed

* the constructor no longer emits a deprecation on PHP 8.4 and later. Its optional PSR-17 factory
  parameters are now explicitly nullable. Affects 2.0.0 and 2.1.0

### Changed

* Guzzle 8 is supported and tested. The development requirement is now `^7.8 || ^8.0`
* CI covers both Guzzle lines
* the `guzzlehttp/guzzle` suggestion and the README name both major versions
* PHPStan runs twice: over the declared 8.3 to 8.5 range, and pinned at 8.5

2.1.0 (2026-08-19)
------------------

### Added

* `accepted()` reports whether Slack took the message, and `error()` names the reason it did not.
  Neither consumes the response

### Documentation

* the README covers sending through the Slack Web API as well as to an incoming webhook
* the README states that an incoming webhook ignores `from()`, `image()` and `to()`, and what to
  use instead
* added `Setting up Slack credentials`, covering a webhook URL, a bot token, the scopes each needs,
  and Slack's refusal messages
* the README states that this package implements Slack's attachment format

2.0.0 (2026-08-19)
------------------

### Breaking changes

* requires PHP 8.3 or later
* `SlackWebhook::__construct()` takes a PSR-18 `Psr\Http\Client\ClientInterface`, in place of a
  concrete `GuzzleHttp\Client`. PSR-17 request and stream factories may be passed as second and
  third arguments; Guzzle's factory is used when they are omitted, and a `RuntimeException` is
  thrown when there is none to find
* `buildJsonPayload()` is now `buildPayload()`, and returns the Slack payload rather than Guzzle
  request options with the payload under a `json` key. `sendPayload()` accepts either shape
* `SlackMessage::http()` contributes request headers only. Configure timeouts and proxies on the
  client
* removed `fromLaravel()` from `SlackMessage`, `SlackAttachment` and `SlackAttachmentField`
* `SlackAttachment::timestamp()` accepts any `\DateTimeInterface`, in place of `Carbon\Carbon`

### Added

* `sendPayload()` takes an optional third argument of transport options, carrying request headers
* requires `psr/http-client`, `psr/http-factory` and `psr/http-message`. Guzzle remains a suggestion

### Changed

* analysed by PHPStan at level 9 over `src`, across PHP 8.3 to 8.5, with no baseline
* formatted to PSR-12 with Pint, and declares strict types
* tested on PHP 8.3, 8.4 and 8.5, at the lowest dependencies each constraint allows, and against a
  production install with no HTTP client present
* added tests for the message levels, the payload flags, the attachment fields, the request, and a
  payload carried through a queue
* `laravel/slack-notification-channel` is no longer a development dependency
* raised the `mockery/mockery` development requirement to `^1.6`
* uses PHPUnit 12. The test run fails on deprecations, notices, risky tests and warnings
* the distribution archive carries `src`, `composer.json`, `README.md`, `CHANGELOG.md` and
  `LICENSE.md`, and nothing else
* the repository declares LF line endings
* moved to GitHub. Repository URLs, issue links and badges updated, `LICENSE.md` added, and
  `notifications` added to the package keywords

1.1.0 (2019-10-14)
------------------

* don't require illuminate/notifications - use laravel/slack-notification-channel instead
* use PHPUnit v8

1.0.3 (2018-09-13)
------------------

* added message builder function to SlackWebhook

1.0.2 (2018-08-28)
------------------

* make buildJsonPayload public so we can separate the payload generation from the sending process
* split send function into two public functions send and sendPayload - where send simply calls sendPayload with a built
  payload
* send/sendPayload now actually returns the \Psr\Http\Message\ResponseInterface we said it would
* swap to using actual Guzzle\Client rather than Guzzle\ClientInterface 

1.0.1 (2018-08-27)
------------------

* bug fix - don't fall over when we don't have any fields

1.0.0 (2018-08-27)
------------------

* initial release
