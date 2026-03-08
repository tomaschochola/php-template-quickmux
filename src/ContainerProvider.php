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

use Override;
use TomasChochola\Psr\Http\RequestHandlers\OkRequestHandler;
use TomasChochola\Psr\Http\RequestHandlers\RouteLoader;
use TomasChochola\Quickmux\ContainerProvider as TomasChocholaQuickmuxContainerProvider;
use Traversable;

final readonly class ContainerProvider extends TomasChocholaQuickmuxContainerProvider
{
    #[Override]
    public function getIterator(): Traversable
    {
        yield from parent::getIterator();

        yield from self::routes();
    }

    /**
     * @return iterable<mixed, mixed>
     */
    protected static function routes(): iterable
    {
        $routes = new RouteLoader();

        $routes->route(['GET'], '/healthz/live', [OkRequestHandler::class]);

        return $routes;
    }
}
