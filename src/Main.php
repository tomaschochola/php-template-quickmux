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

namespace Src;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TomasChochola\Psr\Container\Container;
use TomasChochola\Psr\Http\RequestHandlers\ResponseEmitter;
use TomasChochola\Psr\SimpleCache\ApcuSimpleCache;
use TomasChochola\Psr\SimpleCache\SimpleCaches;
use TomasChochola\Quickmux\CONTAINER_CACHE;

use function assert;
use function iterator_to_array;

final readonly class Main
{
    public function __invoke(): void
    {
        $container = new Container(CONTAINER_CACHE::current() ? SimpleCaches::remember(new ApcuSimpleCache(), self::class, static fn(): array => iterator_to_array(new ContainerManifest())) : iterator_to_array(new ContainerManifest()));

        $emitter = $container->get(ResponseEmitter::class);
        $handler = $container->get(RequestHandlerInterface::class);
        $request = $container->get(ServerRequestInterface::class);

        assert($emitter instanceof ResponseEmitter);
        assert($handler instanceof RequestHandlerInterface);
        assert($request instanceof ServerRequestInterface);

        $emitter->emit($handler->handle($request));
    }
}
