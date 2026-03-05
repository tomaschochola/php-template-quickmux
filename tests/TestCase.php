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
use TomasChochola\Psr\Container\CallableResolver;
use TomasChochola\Psr\Container\Container;
use TomasChochola\Psr\Container\ResolverInterface;
use TomasChochola\Psr\Http\RequestHandlers\ErrorHandlerMiddleware;
use TomasChochola\Psr\Http\RequestHandlers\NullMiddleware;
use TomasChochola\Splx\VariadicIterator;

use function assert;
use function iterator_to_array;

/**
 * @internal
 */
abstract class TestCase extends PHPUnitFrameworkTestCase
{
    private Container|null $container = null;

    protected function container(): Container
    {
        if ($this->container === null) {
            $this->container = new Container(iterator_to_array(new VariadicIterator(Bootstrapper::load(), $this->registry())));
        }

        return $this->container;
    }

    /**
     * @param array<int|string, mixed> $params
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
     * @return iterable<int|string, ResolverInterface>
     */
    protected function registry(): iterable
    {
        yield ErrorHandlerMiddleware::class => new CallableResolver([NullMiddleware::class, 'provide']);
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
