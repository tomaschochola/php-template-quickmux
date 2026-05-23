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

namespace Src\Integration;

use GlobIterator;
use IteratorAggregate;
use Override;
use Psr\Clock\ClockInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use TomasChochola\Loaders\EnvLoader;
use TomasChochola\Loaders\IniLoader;
use TomasChochola\Loaders\PhpLoader;
use TomasChochola\Migrations\MigrationsInterface;
use TomasChochola\Migrations\MigratorInterface;
use TomasChochola\Oracle\Database\OracleConnection;
use TomasChochola\Oracle\Database\OracleDatabase;
use TomasChochola\Psr\Clock\FixedClock;
use TomasChochola\Psr\Container\SingletonResolver;
use TomasChochola\Psr\Http\RequestHandlers\NotFoundRequestHandler;
use TomasChochola\Psr\Http\RequestHandlers\NullMiddleware;
use TomasChochola\Psr\Http\RequestHandlers\OkRequestHandler;
use TomasChochola\Psr\Http\RequestHandlers\ResponseEmitterInterface;
use TomasChochola\Psr\Http\RequestHandlers\RouteLoader;
use TomasChochola\Psr\Http\RequestHandlers\ThrowableCatcherMiddleware;
use TomasChochola\Psr\Http\RequestHandlers\ThrowableLoggerMiddleware;
use TomasChochola\Psr\Log\ExporterInterface;
use TomasChochola\Psr\Log\FilterInterface;
use TomasChochola\Psr\Log\FormatterInterface;
use TomasChochola\Psr\Log\InterpolatorInterface;
use TomasChochola\Psr\Log\OnlyFilter;
use TomasChochola\Psr\Log\RecorderInterface;
use TomasChochola\Psr\Log\WriterInterface;
use TomasChochola\Psr\SimpleCache\NullSimpleCache;
use Traversable;

/**
 * @no-named-arguments
 *
 * @implements IteratorAggregate<mixed, mixed>
 */
readonly class ContainerManifest implements IteratorAggregate
{
    #[Override()]
    public function getIterator(): Traversable
    {
        yield from self::global();
        yield from new EnvLoader(['APP_ENV', 'ORACLE_DATABASE', 'ORACLE_HOST', 'ORACLE_PASSWORD', 'ORACLE_USER']);
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

        if ($scope === 'local') {
            yield from self::local();
        }

        if ($scope === 'phpunit') {
            yield from self::phpunit();
            yield from new EnvLoader(['ORACLE_UNIT_USER' => 'ORACLE_USER']);
            yield from new IniLoader(new GlobIterator('./config/phpunit.ini'));
            yield from new IniLoader(new GlobIterator('./.phpunit.ini'));
            yield from new PhpLoader(new GlobIterator('./config/phpunit.php'));
            yield from new PhpLoader(new GlobIterator('./.phpunit.php'));
        }
    }

    /**
     * @return iterable<mixed, mixed>
     */
    private static function global(): iterable
    {
        yield ServerRequestFactoryInterface::class => new SingletonResolver([Resolver::class, 'ServerRequestFactoryInterface']);
        yield StreamFactoryInterface::class => new SingletonResolver([Resolver::class, 'StreamFactoryInterface']);
        yield UriFactoryInterface::class => new SingletonResolver([Resolver::class, 'UriFactoryInterface']);
        yield RequestHandlerInterface::class => new SingletonResolver([Resolver::class, 'RouteRequestHandler']);
        yield ThrowableCatcherMiddleware::class => new SingletonResolver([Resolver::class, 'ThrowableCatcherMiddleware']);
        yield ResponseFactoryInterface::class => new SingletonResolver([Resolver::class, 'ResponseFactoryInterface']);
        yield ThrowableLoggerMiddleware::class => new SingletonResolver([Resolver::class, 'ThrowableLoggerMiddleware']);
        yield LoggerInterface::class => new SingletonResolver([Resolver::class, 'LoggerInterface']);
        yield ExporterInterface::class => new SingletonResolver([Resolver::class, 'ExporterInterface']);
        yield FilterInterface::class => new SingletonResolver([Resolver::class, 'FilterInterface']);
        yield FormatterInterface::class => new SingletonResolver([Resolver::class, 'FormatterInterface']);
        yield InterpolatorInterface::class => new SingletonResolver([Resolver::class, 'InterpolatorInterface']);
        yield WriterInterface::class => new SingletonResolver([Resolver::class, 'WriterInterface']);
        yield RecorderInterface::class => new SingletonResolver([Resolver::class, 'RecorderInterface']);
        yield ClockInterface::class => new SingletonResolver([Resolver::class, 'ClockInterface']);
        yield NotFoundRequestHandler::class => new SingletonResolver([Resolver::class, 'NotFoundRequestHandler']);
        yield OkRequestHandler::class => new SingletonResolver([Resolver::class, 'OkRequestHandler']);
        yield ResponseEmitterInterface::class => new SingletonResolver([Resolver::class, 'ResponseEmitterInterface']);
        yield ServerRequestInterface::class => new SingletonResolver([Resolver::class, 'ServerRequestInterface']);
        yield OracleDatabase::class => new SingletonResolver([Resolver::class, 'OracleDatabase']);
        yield OracleConnection::class => new SingletonResolver([Resolver::class, 'OracleConnection']);
        yield MigratorInterface::class => new SingletonResolver([Resolver::class, 'MigratorInterface']);
        yield MigrationsInterface::class => new SingletonResolver([Resolver::class, 'MigrationsInterface']);
    }

    /**
     * @return iterable<mixed, mixed>
     */
    private static function local(): iterable
    {
        yield ThrowableCatcherMiddleware::class => new NullMiddleware();
    }

    /**
     * @return iterable<mixed, mixed>
     */
    private static function phpunit(): iterable
    {
        yield CacheInterface::class => new NullSimpleCache();
        yield ClockInterface::class => new FixedClock();
        yield FilterInterface::class => new OnlyFilter(['warning', 'error', 'critical', 'alert', 'emergency']);
        yield ThrowableCatcherMiddleware::class => new NullMiddleware();
    }

    /**
     * @return iterable<mixed, mixed>
     */
    private static function routes(): iterable
    {
        $routes = new RouteLoader();
        $global = [ThrowableCatcherMiddleware::class, ThrowableLoggerMiddleware::class];

        $routes->route(['GET'], '/healthz/live', [...$global, OkRequestHandler::class]);
        $routes->route(['*'], '*', [NotFoundRequestHandler::class]);

        return $routes;
    }
}
