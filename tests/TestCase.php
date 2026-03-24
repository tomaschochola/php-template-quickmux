<?php

/**
 * @author Tomáš Chochola <tomaschochola@tomaschochola.cz>
 * @copyright © 2026 Tomáš Chochola <tomaschochola@tomaschochola.cz>
 *
 * @license CC-BY-ND-4.0
 *
 * @see {@link https://creativecommons.org/licenses/by-nd/4.0/} License
 * @see {@link https://github.com/tomaschochola} GitHub Profile
 * @see {@link https://github.com/sponsors/tomaschochola} GitHub Sponsors
 */

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase as PHPUnitFrameworkTestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Src\Integration\ContainerManifest;
use TomasChochola\Psr\Container\Container;

use function iterator_to_array;

/**
 * @internal
 * @no-named-arguments
 */
abstract class TestCase extends PHPUnitFrameworkTestCase
{
    private Container|null $container = null;

    protected function container(): Container
    {
        if ($this->container === null) {
            $this->container = new Container(iterator_to_array(new ContainerManifest()));
        }

        return $this->container;
    }

    /**
     * @param array<mixed, mixed> $params
     */
    protected function createServerRequest(string $method, UriInterface|string $uri, array $params = []): ServerRequestInterface
    {
        return $this->container()->resolve(ServerRequestFactoryInterface::class)->createServerRequest($method, $uri, $params);
    }

    protected function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->container()->resolve(RequestHandlerInterface::class)->handle($request);
    }
}
