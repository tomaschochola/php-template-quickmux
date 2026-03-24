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
use Pdo\Mysql;
use Psr\Clock\ClockInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use TomasChochola\Loaders\EnvLoader;
use TomasChochola\Loaders\IniLoader;
use TomasChochola\Loaders\PhpLoader;
use TomasChochola\Migrations\MigrationInterface;
use TomasChochola\Pdo\Mysql\CreateMigrationsTableMigration;
use TomasChochola\Pdo\Mysql\MysqlFactory;
use TomasChochola\Pdo\Mysql\MysqlProbe;
use TomasChochola\Pdo\PdoQuery;
use TomasChochola\Pdo\PdoSettings;
use TomasChochola\Pdo\PdoSettingsFactory;
use TomasChochola\Pdo\PdoSettingsInterface;
use TomasChochola\Psr\Clock\FixedClock;
use TomasChochola\Psr\Clock\NowClock;
use TomasChochola\Psr\Container\Container;
use TomasChochola\Psr\Container\FactoryResolver;
use TomasChochola\Psr\Container\SingletonResolver;
use TomasChochola\Psr\Http\Client\CurlClient;
use TomasChochola\Psr\Http\Factory\CgiServerRequestFactory;
use TomasChochola\Psr\Http\Factory\RequestFactory;
use TomasChochola\Psr\Http\Factory\ResponseFactory;
use TomasChochola\Psr\Http\Factory\ServerRequestFactory;
use TomasChochola\Psr\Http\Factory\StreamFactory;
use TomasChochola\Psr\Http\Factory\UploadedFileFactory;
use TomasChochola\Psr\Http\Factory\UriFactory;
use TomasChochola\Psr\Http\RequestHandlers\AfterPipeline;
use TomasChochola\Psr\Http\RequestHandlers\BeforePipeline;
use TomasChochola\Psr\Http\RequestHandlers\ErrorCatcherMiddleware;
use TomasChochola\Psr\Http\RequestHandlers\ErrorLoggerMiddleware;
use TomasChochola\Psr\Http\RequestHandlers\ErrorRaiserMiddleware;
use TomasChochola\Psr\Http\RequestHandlers\ExceptionCatcherMiddleware;
use TomasChochola\Psr\Http\RequestHandlers\ExceptionLoggerMiddleware;
use TomasChochola\Psr\Http\RequestHandlers\JsonEncoder;
use TomasChochola\Psr\Http\RequestHandlers\JsonResponder;
use TomasChochola\Psr\Http\RequestHandlers\JsonWriter;
use TomasChochola\Psr\Http\RequestHandlers\NegativeCatcherMiddleware;
use TomasChochola\Psr\Http\RequestHandlers\NoContentRequestHandler;
use TomasChochola\Psr\Http\RequestHandlers\NotFoundRequestHandler;
use TomasChochola\Psr\Http\RequestHandlers\NullMiddleware;
use TomasChochola\Psr\Http\RequestHandlers\OkRequestHandler;
use TomasChochola\Psr\Http\RequestHandlers\PipelineResolver;
use TomasChochola\Psr\Http\RequestHandlers\RequireParsedBodyMiddleware;
use TomasChochola\Psr\Http\RequestHandlers\ResponseEmitter;
use TomasChochola\Psr\Http\RequestHandlers\RouteLoader;
use TomasChochola\Psr\Http\RequestHandlers\RouteMatcher;
use TomasChochola\Psr\Http\RequestHandlers\RouteRequestHandler;
use TomasChochola\Psr\Http\RequestHandlers\StreamWriter;
use TomasChochola\Psr\Http\RequestHandlers\ThrowableCatcherMiddleware;
use TomasChochola\Psr\Http\RequestHandlers\ThrowableLoggerMiddleware;
use TomasChochola\Psr\Http\RequestHandlers\WithRequestCookiesMiddleware;
use TomasChochola\Psr\Http\RequestHandlers\WithRequestFormMiddleware;
use TomasChochola\Psr\Http\RequestHandlers\WithRequestHeadersMiddleware;
use TomasChochola\Psr\Http\RequestHandlers\WithRequestJsonMiddleware;
use TomasChochola\Psr\Http\RequestHandlers\WithRequestQueryMiddleware;
use TomasChochola\Psr\Log\CollectingExporter;
use TomasChochola\Psr\Log\ExporterInterface;
use TomasChochola\Psr\Log\FormatterInterface;
use TomasChochola\Psr\Log\Interpolator;
use TomasChochola\Psr\Log\InterpolatorInterface;
use TomasChochola\Psr\Log\Logger;
use TomasChochola\Psr\Log\Recorder;
use TomasChochola\Psr\Log\RecorderInterface;
use TomasChochola\Psr\SimpleCache\ApcuSimpleCache;
use TomasChochola\Psr\SimpleCache\NullSimpleCache;
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

        if ($scope === 'unit') {
            yield from new IniLoader(new GlobIterator('./config/phpunit.ini'));
            yield from new IniLoader(new GlobIterator('./.phpunit.ini'));
            yield from new PhpLoader(new GlobIterator('./config/phpunit.php'));
            yield from new PhpLoader(new GlobIterator('./.phpunit.php'));
        }
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
}
