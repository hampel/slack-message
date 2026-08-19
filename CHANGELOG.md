CHANGELOG
=========

2.2.0 (unreleased)
------------------

### Fixed

* the constructor no longer emits a deprecation on PHP 8.4 and later. Its two optional PSR-17
  factory parameters were implicitly nullable, which PHP 8.4 deprecated in favour of an explicit
  `?Type`. Affects 2.0.0 and 2.1.0: on 8.4 or 8.5 every construction raised two deprecations, and a
  consumer whose test suite fails on deprecations would have failed on them

### Changed

* Guzzle 8 is supported and tested. A fresh install has resolved Guzzle 8 since it was released,
  because Guzzle is a suggestion rather than a requirement and nothing here constrains it — but the
  development requirement was pinned to `^7.8`, so the version most consumers actually get was the
  one never exercised. Widened to `^7.8 || ^8.0`, which puts Guzzle 8 in the test matrix
* CI covers both Guzzle lines: the matrix resolves Guzzle 8, and a named job holds Guzzle 7, which
  is what XenForo 2.x installs
* the `guzzlehttp/guzzle` suggestion and the README name both major versions
* PHPStan runs twice: once over the declared 8.3 to 8.5 range, and once pinned at 8.5. A range
  reports only what is an error across the whole of it, so a deprecation introduced above the floor
  does not appear — which is how the 8.4 deprecation above reached a release. The pinned pass
  catches that class before the test matrix, and in code no test executes

2.1.0 (2026-08-19)
------------------

Says whether Slack accepted the message, and documents the route that needs it.

### Added

* `accepted()` reports whether Slack took the message, and `error()` names the reason it did not.
  An incoming webhook reports failure as an HTTP status; the Web API answers `200` whatever happens
  and puts the outcome in an `ok` field, so a refusal and a delivery are indistinguishable from the
  status alone. Neither method consumes the response

### Changed

* `harness/` holds exercises for [hampel/rig](https://github.com/hampel/rig), which post to a real
  webhook and show the result rather than asserting against a fixture. Not shipped, and not run by CI

### Documentation

* the README covers sending through Slack's Web API as well as to an incoming webhook: the same
  builders and the same payload, posted to `chat.postMessage` with a bot token in a header
* a modern incoming webhook ignores `from()`, `image()` and `to()` — it posts as the Slack app the
  webhook belongs to, in the channel chosen when the app was installed. The README says so, and says
  what to use instead
* `Setting up Slack credentials` covers obtaining a webhook URL and a bot token, what each scope is
  for, and what Slack's refusal messages mean
* the README states that this package implements the attachment format, which Slack calls outmoded,
  so that nobody adopts it expecting Block Kit

2.0.0 (2026-08-19)
------------------

Sends over any PSR-18 HTTP client, and no longer carries Laravel to test itself.

### Breaking changes

* requires PHP 8.3 or later
* `SlackWebhook::__construct()` takes a PSR-18 `Psr\Http\Client\ClientInterface`, in place of a concrete
  `GuzzleHttp\Client`. PSR-17 request and stream factories may be passed as second and third arguments;
  Guzzle's factory is used when they are omitted, and a `RuntimeException` is thrown when there is none
  to find
* `buildJsonPayload()` is now `buildPayload()`, and returns the Slack payload. It previously returned
  Guzzle request options, with the payload under a `json` key. `sendPayload()` accepts either shape, so a
  payload queued before the upgrade still sends
* `SlackMessage::http()` contributes request headers only. PSR-18 has no per-request options — configure
  timeouts and proxies on the client
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
* the payload fixtures are built with this package's own builders. Each was verified byte for byte
  against the payload Laravel builds, at `laravel/framework` 13.26.1 and
  `laravel/slack-notification-channel` 3.10.0
* direct test coverage for message levels and colour, `link_names`, the unfurl flags, attachment
  pretext, image and thumbnail, request headers, factory discovery, and a payload carried through a
  queue
* `laravel/slack-notification-channel` is no longer a development dependency
* raised the `mockery/mockery` development requirement to `^1.6`. `^1.0` could not run the test suite
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
