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
use Src\ContainerManifest;
use TomasChochola\Psr\Container\Container;
use TomasChochola\Psr\Http\RequestHandlers\ResponseExiter;
use TomasChochola\Quickmux\ContainerManifestCache;

use function assert;

readonly class Main
{
    public function __invoke(): void
    {
        $container = new Container(ContainerManifestCache::remember(new ContainerManifest()));

        $emitter = $container->get(ResponseExiter::class);
        $handler = $container->get(RequestHandlerInterface::class);
        $request = $container->get(ServerRequestInterface::class);

        assert($emitter instanceof ResponseExiter);
        assert($handler instanceof RequestHandlerInterface);
        assert($request instanceof ServerRequestInterface);

        $emitter->emit($handler->handle($request));
    }
}
