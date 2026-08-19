Setting up Slack credentials
============================

This package can talk to Slack two ways, and they are configured differently. Both start with a
Slack app.

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

Creating the app
----------------

Both routes need an app. Go to [api.slack.com/apps/new](https://api.slack.com/apps/new).

**Do not add bot scopes to an app whose incoming webhook is already in production.** Adding scopes
requires reinstalling the app, and Slack's documentation does not say whether an existing webhook
URL survives that. If a webhook URL is already configured somewhere and working, create a second
app rather than finding out.

Route 1 — an incoming webhook
-----------------------------

1. Create the app **From scratch**. Name it and pick the workspace.
2. In the left sidebar, select **Incoming Webhooks**.
3. Toggle **Activate Incoming Webhooks** on.
4. Click **Add New Webhook to Workspace**.
5. Choose the channel the webhook posts to, and **Authorize**.
6. Copy the URL. It looks like
   `https://hooks.slack.com/services/<team>/<webhook>/<token>`.

The URL is *"specific to a single user and a single channel"*. To post to another channel, repeat
steps 4 and 5 — one URL per channel.

```
SLACK_WEBHOOK_URL=https://hooks.slack.com/services/<team>/<webhook>/<token>
```

Route 2 — a bot token
---------------------

1. Create the app **From an app manifest**, pick the workspace, and paste:

    ```json
    {
        "display_information": {
            "name": "Slack Message Rig"
        },
        "features": {
            "bot_user": {
                "display_name": "Slack Message Rig",
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

```
SLACK_BOT_TOKEN=xoxb-your-token-here
SLACK_CHANNEL=#your-channel
```

### What the scopes are for

| scope | |
|---|---|
| `chat:write` | the baseline; required by the other two |
| `chat:write.public` | *"Send messages to channels your Slack app isn't a member of"* — without it the bot must be invited to every channel |
| `chat:write.customize` | makes `from()` and `image()` work. Slack: *"a powerful ability… only available when an app has requested and been granted an additional scope"* |

### Sending with a token

The token goes in a header, and the payload goes to the Web API rather than a webhook URL:

```php
$message->http(['headers' => ['Authorization' => 'Bearer ' . $token]]);

$response = $slack->send('https://slack.com/api/chat.postMessage', $message);

if (! $slack->accepted($response)) {
    throw new RuntimeException($slack->error($response));
}
```

The check is not optional here. `chat.postMessage` answers `200` whether it accepted the message
or not, so without it a refusal is indistinguishable from a delivery.

Keeping the credentials
-----------------------

Both are secrets. Slack *"actively searches out and revokes leaked secrets"*, so neither belongs in
a repository. For the exercises in `harness/`, put them in a `.env` beside the package — it is
gitignored. `.env.example` lists what each exercise needs.

When something is refused
-------------------------

`error()` returns Slack's own name for the problem:

| | |
|---|---|
| `channel_not_found` | no such channel, or the name is wrong. A channel ID is more robust than a name |
| `not_in_channel` | a private channel the bot has not been invited to. `chat:write.public` covers public channels only — for a private one, `/invite @Your App` in it first |
| `invalid_auth` | the token is wrong, revoked, or missing its `Bearer ` prefix |
| `missing_scope` | the app was installed before the scope was added. Reinstall it |
| `invalid_payload` | webhook only: the JSON was malformed or the message empty |
| `no_service` | webhook only: the webhook has been deleted or the app uninstalled |

Silence with no error is usually the wrong channel rather than a failure — check what the webhook
was bound to at step 5.
