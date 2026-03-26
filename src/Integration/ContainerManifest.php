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
use PDO;
use Psr\Clock\ClockInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use TomasChochola\Loaders\EnvLoader;
use TomasChochola\Loaders\IniLoader;
use TomasChochola\Loaders\PhpLoader;
use TomasChochola\Migrations\MigrationsInterface;
use TomasChochola\Migrations\Migrator;
use TomasChochola\Migrations\MigratorInterface;
use TomasChochola\Migrations\Mysql\MysqlMigrations;
use TomasChochola\Pdo\LockerInterface;
use TomasChochola\Pdo\Mysql\MysqlFactory;
use TomasChochola\Pdo\Mysql\MysqlLocker;
use TomasChochola\Pdo\Mysql\MysqlQuery;
use TomasChochola\Pdo\Mysql\MysqlSettings;
use TomasChochola\Pdo\Mysql\MysqlSettingsFactory;
use TomasChochola\Pdo\Mysql\MysqlSettingsInterface;
use TomasChochola\Pdo\QueryInterface;
use TomasChochola\Psr\Container\SingletonResolver;
use TomasChochola\Psr\Http\Factory\CgiServerRequestFactory;
use TomasChochola\Psr\Http\RequestHandlers\AfterPipeline;
use TomasChochola\Psr\Http\RequestHandlers\BeforePipeline;
use TomasChochola\Psr\Http\RequestHandlers\ErrorRaiserMiddleware;
use TomasChochola\Psr\Http\RequestHandlers\NotFoundRequestHandler;
use TomasChochola\Psr\Http\RequestHandlers\OkRequestHandler;
use TomasChochola\Psr\Http\RequestHandlers\PipelineResolver;
use TomasChochola\Psr\Http\RequestHandlers\ResponseEmitter;
use TomasChochola\Psr\Http\RequestHandlers\RouteLoader;
use TomasChochola\Psr\Http\RequestHandlers\RouteMatcher;
use TomasChochola\Psr\Http\RequestHandlers\ThrowableCatcherMiddleware;
use TomasChochola\Psr\Http\RequestHandlers\ThrowableLoggerMiddleware;
use TomasChochola\Psr\Log\CollectingExporter;
use TomasChochola\Psr\Log\ExceptFilter;
use TomasChochola\Psr\Log\ExporterInterface;
use TomasChochola\Psr\Log\FilterExporter;
use TomasChochola\Psr\Log\FilterInterface;
use TomasChochola\Psr\Log\FormatterInterface;
use TomasChochola\Psr\Log\FormatterWriterExporter;
use TomasChochola\Psr\Log\InterpolatorInterface;
use TomasChochola\Psr\Log\OnlyFilter;
use TomasChochola\Psr\Log\RecorderInterface;
use TomasChochola\Psr\Log\WriterInterface;
use Traversable;

/**
 * @no-named-arguments
 *
 * @implements IteratorAggregate<mixed, mixed>
 */
final readonly class ContainerManifest implements IteratorAggregate
{
    #[Override]
    public function getIterator(): Traversable
    {
        yield from self::global();

        yield from new EnvLoader(['APP_ENV', 'MYSQL_HOST', 'MYSQL_DATABASE', 'MYSQL_USER', 'MYSQL_PASSWORD_FILE']);

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

        if ($scope === 'unit') {
            yield from self::unit();

            yield from new EnvLoader(['MYSQL_UNIT_DATABASE' => 'MYSQL_DATABASE', 'MYSQL_ROOT_USER' => 'MYSQL_USER', 'MYSQL_ROOT_PASSWORD_FILE' => 'MYSQL_PASSWORD_FILE']);

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
        yield ServerRequestFactoryInterface::class => new SingletonResolver([Resolver::class, 'serverRequestFactory']);

        yield StreamFactoryInterface::class => new SingletonResolver([Resolver::class, 'streamFactory']);

        yield UriFactoryInterface::class => new SingletonResolver([Resolver::class, 'uriFactory']);

        yield RequestHandlerInterface::class => new SingletonResolver([Resolver::class, 'routeRequestHandler']);

        yield AfterPipeline::class => new SingletonResolver([Resolver::class, 'afterPipeline']);

        yield BeforePipeline::class => new SingletonResolver([Resolver::class, 'beforePipeline']);

        yield RouteMatcher::class => new SingletonResolver([Resolver::class, 'routeMatcher']);

        yield PipelineResolver::class => new SingletonResolver([Resolver::class, 'pipelineResolver']);

        yield ThrowableCatcherMiddleware::class => new SingletonResolver([Resolver::class, 'throwableCatcherMiddleware']);

        yield ResponseFactoryInterface::class => new SingletonResolver([Resolver::class, 'responseFactory']);

        yield ThrowableLoggerMiddleware::class => new SingletonResolver([Resolver::class, 'throwableLoggerMiddleware']);

        yield LoggerInterface::class => new SingletonResolver([Resolver::class, 'logger']);

        yield ExporterInterface::class => new SingletonResolver([Resolver::class, 'filterFormatterWriterExporter']);

        yield CollectingExporter::class => new SingletonResolver([Resolver::class, 'collectingExporter']);

        yield FilterExporter::class => new SingletonResolver([Resolver::class, 'filterFormatterWriterExporter']);

        yield FilterInterface::class => new SingletonResolver([Resolver::class, 'onlyFilter']);

        yield FormatterInterface::class => new SingletonResolver([Resolver::class, 'jsonFormatter']);

        yield FormatterWriterExporter::class => new SingletonResolver([Resolver::class, 'formatterWriterExporter']);

        yield InterpolatorInterface::class => new SingletonResolver([Resolver::class, 'interpolator']);

        yield OnlyFilter::class => new SingletonResolver([Resolver::class, 'onlyFilter']);

        yield WriterInterface::class => new SingletonResolver([Resolver::class, 'resourceWriter']);

        yield RecorderInterface::class => new SingletonResolver([Resolver::class, 'recorder']);

        yield ClockInterface::class => new SingletonResolver([Resolver::class, 'nowClock']);

        yield ErrorRaiserMiddleware::class => new SingletonResolver([Resolver::class, 'errorRaiserMiddleware']);

        yield NotFoundRequestHandler::class => new SingletonResolver([Resolver::class, 'notFoundRequestHandler']);

        yield OkRequestHandler::class => new SingletonResolver([Resolver::class, 'okRequestHandler']);

        yield ResponseEmitter::class => new SingletonResolver([Resolver::class, 'responseEmitter']);

        yield ServerRequestInterface::class => new SingletonResolver([Resolver::class, 'serverRequest']);

        yield CgiServerRequestFactory::class => new SingletonResolver([Resolver::class, 'cgiServerRequestFactory']);

        yield QueryInterface::class => new SingletonResolver([Resolver::class, 'mysqlQuery']);

        yield LockerInterface::class => new SingletonResolver([Resolver::class, 'mysqlLocker']);

        yield MysqlLocker::class => new SingletonResolver([Resolver::class, 'mysqlLocker']);

        yield MysqlQuery::class => new SingletonResolver([Resolver::class, 'mysqlQuery']);

        yield PDO::class => new SingletonResolver([Resolver::class, 'mysql']);

        yield MysqlFactory::class => new SingletonResolver([Resolver::class, 'mysqlFactory']);

        yield MysqlSettingsInterface::class => new SingletonResolver([Resolver::class, 'mysqlSettings']);

        yield MysqlSettings::class => new SingletonResolver([Resolver::class, 'mysqlSettings']);

        yield MysqlSettingsFactory::class => new SingletonResolver([Resolver::class, 'mysqlSettingsFactory']);

        yield MigratorInterface::class => new SingletonResolver([Resolver::class, 'migrator']);

        yield Migrator::class => new SingletonResolver([Resolver::class, 'migrator']);

        yield MigrationsInterface::class => new SingletonResolver([Resolver::class, 'mysqlMigrations']);

        yield MysqlMigrations::class => new SingletonResolver([Resolver::class, 'mysqlMigrations']);
    }

    /**
     * @return iterable<mixed, mixed>
     */
    private static function local(): iterable
    {
        yield ThrowableCatcherMiddleware::class => new SingletonResolver([Resolver::class, 'nullMiddleware']);
    }

    /**
     * @return iterable<mixed, mixed>
     */
    private static function routes(): iterable
    {
        $routes = new RouteLoader();

        $routes->route(['GET'], '/healthz/live', [OkRequestHandler::class]);

        return $routes;
    }

    /**
     * @return iterable<mixed, mixed>
     */
    private static function unit(): iterable
    {
        yield ExporterInterface::class => new SingletonResolver([Resolver::class, 'filterCollectingExporter']);

        yield FilterExporter::class => new SingletonResolver([Resolver::class, 'filterCollectingExporter']);

        yield FilterInterface::class => new SingletonResolver([Resolver::class, 'exceptFilter']);

        yield ExceptFilter::class => new SingletonResolver([Resolver::class, 'exceptFilter']);

        yield ThrowableCatcherMiddleware::class => new SingletonResolver([Resolver::class, 'nullMiddleware']);
    }
}
