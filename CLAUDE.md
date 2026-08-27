# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this package is

`hampel/slack-message` is a standalone port of Laravel's Slack message classes from
`illuminate/notifications` — the builders plus the webhook channel, extracted so a plain PHP
application can format and send Slack messages without pulling in the framework or using
Notifiable classes.

**Upstream parity is the design constraint.** The builder API deliberately mirrors Laravel's
(`->content()`, `->attachment(fn)`, `->fields()`, `->from()`, `->to()`) so that Laravel's Slack
notification documentation applies unchanged. Do not "improve" a method signature or rename a
property without a reason that outweighs breaking that parity.

## Commands

```bash
composer install
composer test                                         # phpunit
composer analyse                                      # phpstan, level 9 on src, twice
composer lint                                         # pint --test
composer format                                       # pint, applying the fixes
composer check                                        # all three
vendor/bin/phpunit --filter testTheMessageLevelColoursTheAttachment
vendor/bin/phpunit --filter 'payloadWithIcon'         # one data-provider case
```

Style is PSR-12, enforced by Pint. The test run fails on deprecations, notices, risky tests
and warnings.

`tests/production-install.php` is not part of the PHPUnit suite. It runs against
`composer install --no-dev`, where Guzzle is absent, and is the only place the missing-factory
path can be reached — CI runs it as its own job.

## The harness

`harness/` holds exercises for [hampel/rig](https://github.com/hampel/rig), which does the
opposite of the test suite: it posts to a real webhook and shows you what happened. Nothing in it
asserts, and CI never runs it.

```bash
vendor/bin/rig                  # list the exercises
vendor/bin/rig payload          # needs no credentials, sends nothing
vendor/bin/rig send             # posts a message with one attachment per level
vendor/bin/rig queued           # buildPayload -> JSON -> sendPayload, the add-on's path
vendor/bin/rig legacy           # a version 1 shaped payload, the upgrade path
vendor/bin/rig api              # chat.postMessage, and a refusal behind a 200
vendor/bin/rig channels         # conversations.list, for a config UI to choose from
```

`api` needs `SLACK_BOT_TOKEN` and `SLACK_CHANNEL`, `channels` needs the token plus `channels:read`
and `groups:read` on the app, and the other sending exercises need `SLACK_WEBHOOK_URL`. All of them go in a `.env` beside the package — copy `.env.example`.
The README covers obtaining each of them, and what Slack's refusal messages mean. `.env` is
gitignored and `harness/` is `export-ignore`d.

### Nothing is posted unless you say so

`send`, `queued`, `legacy` and `api` default to a **sink**: the transport is swapped for a PSR-18
client that records the request and answers with a canned response, so the exercise runs every
line it normally would and only the wire is missing. Two switches open it, and every branch that
is not exactly `1` falls back to the sink:

```bash
SLACK_DELIVER=1                 # the ordinary opt-in; belongs in .env
SLACK_AGENT_MAY_DELIVER=1       # an agent, asked to send for real, this once; never in .env
```

`SLACK_DELIVER` alone is ignored when `CLAUDECODE` is set, because a `.env` written months ago
cannot say whether a person or an agent is at the keyboard. `channels` is unguarded — it is a
read-only GET that delivers nothing.

The mode is printed above the work, and a sink run says which questions it did not answer. **Read
that line.** `Harness::mayDeliver()` and `HarnessSink` live in `harness/lib/harness.php`, which is
not itself an exercise — rig discovers `harness/*.php` at the top level only.

A sink run is worth having on its own: it prints the outgoing PSR-7 request, which is the only
view that shows the URI, the content type and whether a version 1 payload arrived unwrapped.

Reach for a *delivering* exercise when the question is "does Slack accept this" or "does this read
well in a channel" — neither of which a test can answer, and neither of which a sink run answers
either. `tests/SlackWebhookTest.php` covers whether the payload is *correct*; the harness covers
whether it is *right*.

### Two things about the output

**`Io::attempt()` prints its verdict after the callback returns**, so anything printed from
inside the closure lands *above* the line saying what was attempted. Carry the response back out
by reference and read it after the call, guarded on it still being null so a throw prints
`attempt()`'s own diagnosis and nothing else. `send` and `api` do this; `queued` and `legacy`
print nothing from inside theirs.

**Print a group of figures with `Io::values()`, not a run of `Io::value()` calls.** `value()`
pads to a fixed column of 14 characters, so a longer label keeps its line but loses the column
and leaves the values around it at ragged indents. `values()` aligns the group to its own widest
label and never narrower, so short labels read identically. It is what `showRequests()` uses,
where a header name is not ours to choose, and what every pair that exists to be compared uses —
`queued` against `sent`, `public` against `private`, `status`/`accepted`/`error`. Needs
`hampel/rig` at `^1.1`.

## Architecture

Four classes in `src/`, in two distinct roles:

**Builders — `SlackMessage`, `SlackAttachment`, `SlackAttachmentField`.** Fluent setters over
public properties. They hold state and validate nothing; they never serialise. `SlackMessage`
carries a `level` (info/success/warning/error) that `color()` maps to Slack's `good`/`warning`/
`danger` — note `info` maps to *no* colour (`color()` returns null).

**Serialiser/transport — `SlackWebhook`.** All knowledge of Slack's wire format lives here, in
`buildPayload()` → `attachments()` → `fields()`. Property-name to Slack-field-name mapping
(`$attachment->url` → `title_link`, `$attachment->content` → `text`, …) is in `attachments()`
and nowhere else. Every level uses `array_filter`, so null/empty/`0` values are dropped from the
payload rather than sent — this is why optional fields simply vanish, and why `linkNames`
defaults to `0`.

Three things about `SlackWebhook` that surprise people:

- **`send()` / `sendPayload()` are split on purpose** (since 1.0.2) so payload construction can
  happen somewhere other than the sending process — e.g. build now, queue, send later. Both
  return the `ResponseInterface`. `buildPayload()` returns the Slack payload itself, so it
  survives being JSON-encoded into a queue and read back.
- **`sendPayload()` unwraps a version 1 payload.** Version 1's `buildJsonPayload()` returned
  Guzzle request options — `['json' => [...slack payload...]]` merged with `$message->http` — and
  a payload built that way may still be sitting in a queue written before the upgrade. A
  top-level `json` key is therefore treated as that wrapper and unwrapped.
- **The client is PSR-18, the factories PSR-17.** When the factories are not supplied the
  constructor looks for `GuzzleHttp\Psr7\HttpFactory` and throws if it is absent. PSR-18 has no
  per-request options, so `SlackMessage::http()` now contributes only headers; timeouts and
  proxies belong on the client.

Per-attachment `color` falls back to the parent message's level colour, so the message level
tints all attachments unless one overrides it.

`accepted()` and `error()` read a response. They are separate from `send()` because the two
transports fail differently: a webhook uses the HTTP status, while `chat.postMessage` answers `200`
whatever happens and puts the outcome in an `ok` field. `readBody()` casts the stream to string —
which seeks to the beginning itself, per PSR-7 — and rewinds afterwards, because `getContents()`
does not, and a caller reading the body after us would otherwise get nothing.

## How parity is held

The payload fixtures in `tests/SlackMessageTest.php` **are** the spec. When changing anything in
`buildPayload()`, that test is what you are changing it against.

Those fixtures were verified byte for byte against the payloads Laravel's own
`SlackWebhookChannel` builds, at `laravel/framework` 13.26.1 and
`laravel/slack-notification-channel` 3.10.0. Laravel is no longer a dependency: upstream froze
these three classes years ago — the v2.5.0 to v3.10.0 diff is docblocks plus a note marking them
legacy — and Slack deprecated the attachment format itself, so there is nothing left to track.
Carrying the framework to watch for a change that cannot come cost 72 packages and exposed the
package's CI to framework advisories that had nothing to do with sending a webhook.

If you ever do need to re-check parity, add `laravel/slack-notification-channel` back as a dev
dependency, convert the fixtures with a throwaway `fromLaravel()`, and compare. Version 1 shipped
such a method on each builder; it was removed in 2.0 because nothing outside the test suite ever
called it and its `\Illuminate\...` type hints would fatal in a production install.

Guzzle is a `suggest`, not a `require` — the caller constructs and injects the client. Only the
PSR interface packages are required.

## Constraints worth knowing before editing

- `composer.json` declares `"php": "^8.3"`, but the source predates it — no scalar type hints,
  no return types, docblocks carry the types. That is about type declarations, not formatting:
  PSR-12 has nothing to say about it and Pint will not add them. Match the surrounding style;
  changing the supported versions is a policy decision, not a drive-by change.
- PHPStan runs at level 9 over `src` with no baseline, twice. `phpstan.neon` covers the declared
  8.3 to 8.5 range; `phpstan-ceiling.neon` pins it at 8.5. A range reports only what is an error
  across the whole of it, so anything deprecated above the floor is invisible to the first pass —
  PHP 8.4's implicitly-nullable parameter deprecation shipped in 2.0.0 and 2.1.0 that way. Keep
  both clean.
- `SlackAttachment::timestamp()` accepts any `\DateTimeInterface`, so Carbon is not needed.
