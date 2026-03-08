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
use Src\Bootstrap\Bootstrapper;
use TomasChochola\Psr\Container\DependencyContainer;

use function assert;
use function iterator_to_array;

/**
 * @internal
 */
abstract class TestCase extends PHPUnitFrameworkTestCase
{
    private DependencyContainer|null $container = null;

    protected function container(): DependencyContainer
    {
        if ($this->container === null) {
            $this->container = new DependencyContainer(iterator_to_array(Bootstrapper::bootstrap()));
        }

        return $this->container;
    }

    /**
     * @param array<mixed, mixed> $params
     */
    protected function createServerRequest(string $method, UriInterface|string $uri, array $params = []): ServerRequestInterface
    {
        return $this->resolve(ServerRequestFactoryInterface::class)->createServerRequest($method, $uri, $params);
    }

    protected function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->resolve(RequestHandlerInterface::class)->handle($request);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    protected function resolve(string $class): object
    {
        $resolved = $this->container()->get($class);

        assert($resolved instanceof $class);

        return $resolved;
    }
}
