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
use TomasChochola\Psr\Container\CallableCargo;
use TomasChochola\Psr\Container\CargoContainer;
use TomasChochola\Psr\Container\CargoInterface;
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
    private CargoContainer|null $container = null;

    protected function container(): CargoContainer
    {
        if ($this->container === null) {
            $this->container = new CargoContainer(iterator_to_array(new VariadicIterator(Bootstrapper::bootstrap(), $this->registry())));
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
     * @return iterable<mixed, CargoInterface>
     */
    protected function registry(): iterable
    {
        yield ErrorHandlerMiddleware::class => new CallableCargo([NullMiddleware::class, 'unload']);
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
