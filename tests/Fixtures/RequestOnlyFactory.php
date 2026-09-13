<?php

declare(strict_types=1);

namespace Hampel\SlackMessage\Tests\Fixtures;

use GuzzleHttp\Psr7\HttpFactory;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;

/**
 * Half of a split PSR-17 pair, the way Diactoros ships its factories. Discovery finds classes
 * by name, so the pair under test has to be real, autoloadable classes.
 */
class RequestOnlyFactory implements RequestFactoryInterface
{
    public function createRequest(string $method, $uri): RequestInterface
    {
        return (new HttpFactory())->createRequest($method, $uri);
    }
}
