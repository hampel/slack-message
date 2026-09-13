<?php

declare(strict_types=1);

namespace Hampel\SlackMessage\Tests\Fixtures;

use GuzzleHttp\Psr7\HttpFactory;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;

/**
 * The other half of a split PSR-17 pair. See RequestOnlyFactory.
 */
class StreamOnlyFactory implements StreamFactoryInterface
{
    public function createStream(string $content = ''): StreamInterface
    {
        return (new HttpFactory())->createStream($content);
    }

    public function createStreamFromFile(string $filename, string $mode = 'r'): StreamInterface
    {
        return (new HttpFactory())->createStreamFromFile($filename, $mode);
    }

    public function createStreamFromResource($resource): StreamInterface
    {
        return (new HttpFactory())->createStreamFromResource($resource);
    }
}
