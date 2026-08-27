<?php

/**
 * Shared harness plumbing: whether a run may reach Slack, and what to use when it may not.
 *
 * Not an exercise. Rig discovers harness/*.php at the top level only, so shared code lives
 * one directory down and is required by its real path.
 */

use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class Harness
{
    /**
     * Decide whether this run may post to Slack for real.
     *
     * Two switches, named so neither is typed by habit:
     *
     *   SLACK_DELIVER=1            the ordinary opt-in, and belongs in .env
     *   SLACK_AGENT_MAY_DELIVER=1  an agent, asked to send for real, this once; never in .env
     *
     * CLAUDECODE is exported by Claude Code into every shell it opens. It is a fact about who
     * is running the command, which a .env written months ago cannot fake - so it overrides
     * the opt-in rather than being overridden by it.
     *
     * Every branch falls back to the sink. If CLAUDECODE is ever renamed upstream, this loses
     * the extra layer and keeps the ordinary one, which is still sink unless asked.
     *
     * @return array{0: bool, 1: string} whether to deliver, and the mode line to print
     */
    public static function mayDeliver()
    {
        if (getenv('SLACK_DELIVER') !== '1') {
            return [false, 'sink - nothing leaves this machine. Set SLACK_DELIVER=1 to post to Slack.'];
        }

        if (getenv('CLAUDECODE') !== false && getenv('SLACK_AGENT_MAY_DELIVER') !== '1') {
            return [false, 'sink - SLACK_DELIVER ignored in an agent session'];
        }

        return [true, 'deliver - this posts to Slack for real'];
    }

    /**
     * Print the mode above the work, so nobody reads a sink run as a pass.
     *
     * @param  \Hampel\Rig\Io  $io
     * @param  string  $mode
     * @return void
     */
    public static function announce($io, $mode)
    {
        $io->value('mode', $mode);
        $io->line();
    }

    /**
     * A credential, or a stand-in when the run is not going anywhere.
     *
     * In sink mode the exercise still runs end to end, so a missing credential is not fatal -
     * the request is built and shown, it is simply never sent. Delivering without one is.
     *
     * @param  \Hampel\Rig\Io  $io
     * @param  bool  $deliver
     * @param  string  $name
     * @param  string  $placeholder  a value that is visibly not real, for the sink to show
     * @return string
     */
    public static function credential($io, $deliver, $name, $placeholder)
    {
        $value = getenv($name);

        if ($value !== false && $value !== '') {
            return $value;
        }

        if (! $deliver) {
            return $placeholder;
        }

        $io->error($name . ' is not set. Copy .env.example to .env beside the package.');
        $io->info('  The README covers how to obtain one, under Setting up Slack credentials.');

        exit(1);
    }

    /**
     * Show what would have gone out.
     *
     * This is the half of the sink that is not about safety. The unit tests compare the
     * payload; only the request says which URI it went to, what content type it carried and
     * whether a legacy payload arrived unwrapped - which is how the discarded headers were
     * found in the first place.
     *
     * @param  \Hampel\Rig\Io  $io
     * @param  HarnessSink  $sink
     * @return void
     */
    public static function showRequests($io, HarnessSink $sink)
    {
        foreach ($sink->requests as $index => $request) {
            $io->line();
            $io->info('  request ' . ($index + 1) . ' of ' . count($sink->requests) . ', which was not sent');
            $io->line();

            $io->value('method', $request->getMethod());
            $io->value('uri', (string) $request->getUri());

            foreach ($request->getHeaders() as $name => $values) {
                $io->value($name, implode(', ', $values));
            }

            $io->line();
            $io->line((string) json_encode(
                json_decode((string) $request->getBody(), true),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            ));
        }
    }
}

/**
 * A PSR-18 client that records the request and answers with a canned response.
 *
 * Swapping the transport rather than returning early means the exercise runs every line it
 * normally would - buildPayload(), send(), accepted(), error() - and only the wire is absent.
 */
class HarnessSink implements ClientInterface
{
    /** @var RequestInterface[] */
    public $requests = [];

    /** @var callable(RequestInterface): array{0: int, 1: string} */
    protected $responder;

    /**
     * @param  callable(RequestInterface): array{0: int, 1: string}|null  $responder
     */
    public function __construct(?callable $responder = null)
    {
        $this->responder = $responder ?: function () {
            return [200, 'ok'];
        };
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;

        [$status, $body] = ($this->responder)($request);

        return new Response($status, [], $body);
    }
}
