<?php

/**
 * A smoke test for what a consumer actually receives.
 *
 * The PHPUnit suite runs against the development install, where Guzzle is always present.
 * This runs against `composer install --no-dev`, where it never is, and checks the two
 * things that install alone can tell us: that src/ needs nothing beyond the requirements
 * declared in composer.json, and that a missing PSR-17 factory is reported rather than
 * discovered by a fatal error somewhere further in.
 *
 * Run it with: composer install --no-dev && php tests/production-install.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Hampel\SlackMessage\SlackMessage;
use Hampel\SlackMessage\SlackWebhook;

$failures = 0;

function check($description, $condition)
{
    global $failures;

    if ($condition) {
        echo "ok   $description\n";

        return;
    }

    echo "FAIL $description\n";
    $failures++;
}

// A payload can be built with no HTTP client anywhere in sight.
$message = (new SlackMessage())
    ->error()
    ->to('#ops')
    ->content('Content')
    ->attachment(function ($attachment) {
        $attachment->title('Laravel', 'https://laravel.com')
            ->content('Attachment Content')
            ->fields(['Project' => 'Laravel'])
            ->timestamp(new \DateTimeImmutable('@1234567890'));
    });

$client = new class () implements \Psr\Http\Client\ClientInterface {
    public function sendRequest(\Psr\Http\Message\RequestInterface $request): \Psr\Http\Message\ResponseInterface
    {
        throw new \LogicException('This client is never asked to send anything.');
    }
};

$expected = [
    'text' => 'Content',
    'attachments' => [
        [
            'color' => 'danger',
            'fields' => [
                ['title' => 'Project', 'value' => 'Laravel', 'short' => true],
            ],
            'text' => 'Attachment Content',
            'title' => 'Laravel',
            'title_link' => 'https://laravel.com',
            'ts' => 1234567890,
        ],
    ],
    'channel' => '#ops',
];

$factory = new class () implements \Psr\Http\Message\RequestFactoryInterface, \Psr\Http\Message\StreamFactoryInterface {
    public function createRequest(string $method, $uri): \Psr\Http\Message\RequestInterface
    {
        throw new \LogicException('This factory is never asked to build anything.');
    }

    public function createStream(string $content = ''): \Psr\Http\Message\StreamInterface
    {
        throw new \LogicException('This factory is never asked to build anything.');
    }

    public function createStreamFromFile(string $filename, string $mode = 'r'): \Psr\Http\Message\StreamInterface
    {
        throw new \LogicException('This factory is never asked to build anything.');
    }

    public function createStreamFromResource($resource): \Psr\Http\Message\StreamInterface
    {
        throw new \LogicException('This factory is never asked to build anything.');
    }
};

$webhook = new SlackWebhook($client, $factory, $factory);

check('a payload is built without an HTTP client', $webhook->buildPayload($message) === $expected);

// No PSR-17 implementation is a requirement, so with nothing to discover the constructor has to say so.
try {
    new SlackWebhook($client);

    check('a missing PSR-17 factory is reported', false);
} catch (\RuntimeException $e) {
    check('a missing PSR-17 factory is reported', strpos($e->getMessage(), 'PSR-17') !== false);
}

if ($failures > 0) {
    echo "\n$failures check(s) failed.\n";
    exit(1);
}

echo "\nAll checks passed.\n";
