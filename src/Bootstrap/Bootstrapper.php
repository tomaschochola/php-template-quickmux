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

namespace Src\Bootstrap;

use Override;
use TomasChochola\Psr\Http\RequestHandlers\RouteManifest;
use TomasChochola\Psr\Http\RequestHandlers\OkRequestHandler;
use TomasChochola\Quickmux\Bootstrapper as TomasChocholaQuickmuxBootstrapper;

final readonly class Bootstrapper extends TomasChocholaQuickmuxBootstrapper
{
    /**
     * @return iterable<int|string, mixed>
     */
    #[Override]
    public static function bootstrap(): iterable
    {
        yield from parent::bootstrap();

        yield from self::routes();
    }

    /**
     * @return iterable<int|string, mixed>
     */
    protected static function routes(): iterable
    {
        $routes = new RouteManifest();

        $routes->route(['GET'], '/healthz/live', [OkRequestHandler::class]);

        return $routes;
    }
}
