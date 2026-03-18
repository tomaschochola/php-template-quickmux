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

use TomasChochola\Psr\Http\RequestHandlers\OkRequestHandler;
use TomasChochola\Psr\Http\RequestHandlers\RouteLoader;
use TomasChochola\Quickmux\FrameworkManifest;
use TomasChochola\Quickmux\FrameworkTestingManifest;
use TomasChochola\Quickmux\APP_CACHE;
use TomasChochola\Quickmux\APP_ENV;
use TomasChochola\Quickmux\PHPUNIT_TESTSUITE;
use TomasChochola\Quickmux\EnvLoader;
use TomasChochola\Quickmux\IniLoader;
use TomasChochola\Quickmux\PhpLoader;
use GlobIterator;
use IteratorAggregate;
use Override;
use Traversable;

/**
 * @no-named-arguments
 *
 * @implements IteratorAggregate<mixed, mixed>
 */
readonly class ContainerManifest implements IteratorAggregate
{
    #[Override]
    public function getIterator(): Traversable
    {
        yield from new FrameworkManifest();

        yield from new EnvLoader(['APP_ENV']);

        yield from self::routes();

        yield from new IniLoader(new GlobIterator('./config/base.ini'));

        yield from new IniLoader(new GlobIterator('./.env.ini'));

        yield from new PhpLoader(new GlobIterator('./config/base.php'));

        yield from new PhpLoader(new GlobIterator('./.env.php'));

        $scope = APP_ENV::current();

        yield from new IniLoader(new GlobIterator('./config/' . $scope . '.ini'));

        yield from new IniLoader(new GlobIterator('./.env.' . $scope . '.ini'));

        yield from new PhpLoader(new GlobIterator('./config/' . $scope . '.php'));

        yield from new PhpLoader(new GlobIterator('./.env.' . $scope . '.php'));

        if (PHPUNIT_TESTSUITE::current()) {
            yield from new FrameworkTestingManifest();

            yield from new EnvLoader(['PHPUNIT_TESTSUITE']);

            yield from new IniLoader(new GlobIterator('./config/phpunit.ini'));

            yield from new IniLoader(new GlobIterator('./.phpunit.ini'));

            yield from new PhpLoader(new GlobIterator('./config/phpunit.php'));

            yield from new PhpLoader(new GlobIterator('./.phpunit.php'));
        }
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
