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

use LogicException;
use NoDiscard;
use PDO;
use Pdo\Mysql;
use Psr\Clock\ClockInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Log\LoggerInterface;
use TomasChochola\Migrations\Migrator;
use TomasChochola\Migrations\MigrationsInterface;
use TomasChochola\Migrations\Mysql\MysqlMigrations;
use TomasChochola\Pdo\LockerInterface;
use TomasChochola\Pdo\Mysql\MysqlFactory;
use TomasChochola\Pdo\Mysql\MysqlLocker;
use TomasChochola\Pdo\Mysql\MysqlProbe;
use TomasChochola\Pdo\Mysql\MysqlQuery;
use TomasChochola\Pdo\Mysql\MysqlSettings;
use TomasChochola\Pdo\Mysql\MysqlSettingsFactory;
use TomasChochola\Pdo\Mysql\MysqlSettingsInterface;
use TomasChochola\Pdo\QueryInterface;
use TomasChochola\Psr\Clock\FixedClock;
use TomasChochola\Psr\Clock\NowClock;
use TomasChochola\Psr\Container\Container;
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
use TomasChochola\Psr\Http\RequestHandlers\RouteMatcher;
use TomasChochola\Psr\Http\RequestHandlers\RouteRequestHandler;
use TomasChochola\Psr\Http\RequestHandlers\RouteSettings;
use TomasChochola\Psr\Http\RequestHandlers\StreamWriter;
use TomasChochola\Psr\Http\RequestHandlers\ThrowableCatcherMiddleware;
use TomasChochola\Psr\Http\RequestHandlers\ThrowableLoggerMiddleware;
use TomasChochola\Psr\Http\RequestHandlers\WithRequestCookiesMiddleware;
use TomasChochola\Psr\Http\RequestHandlers\WithRequestFormMiddleware;
use TomasChochola\Psr\Http\RequestHandlers\WithRequestHeadersMiddleware;
use TomasChochola\Psr\Http\RequestHandlers\WithRequestJsonMiddleware;
use TomasChochola\Psr\Http\RequestHandlers\WithRequestQueryMiddleware;
use TomasChochola\Psr\Log\CollectingExporter;
use TomasChochola\Psr\Log\ExceptFilter;
use TomasChochola\Psr\Log\ExporterInterface;
use TomasChochola\Psr\Log\FilterExporter;
use TomasChochola\Psr\Log\FilterInterface;
use TomasChochola\Psr\Log\FormatterInterface;
use TomasChochola\Psr\Log\FormatterWriterExporter;
use TomasChochola\Psr\Log\Interpolator;
use TomasChochola\Psr\Log\InterpolatorInterface;
use TomasChochola\Psr\Log\JsonFormatter;
use TomasChochola\Psr\Log\Logger;
use TomasChochola\Psr\Log\OnlyFilter;
use TomasChochola\Psr\Log\Recorder;
use TomasChochola\Psr\Log\RecorderInterface;
use TomasChochola\Psr\Log\ResourceWriter;
use TomasChochola\Psr\Log\WriterInterface;
use TomasChochola\Psr\SimpleCache\ApcuSimpleCache;
use TomasChochola\Psr\SimpleCache\NullSimpleCache;
use UnexpectedValueException;

/**
 * @no-named-arguments
 */
final readonly class Resolver
{
    private function __construct()
    {
        throw new LogicException('never');
    }

    #[NoDiscard]
    public static function afterPipeline(Container $container): AfterPipeline
    {
        return new AfterPipeline();
    }

    #[NoDiscard]
    public static function apcuSimpleCache(Container $container): ApcuSimpleCache
    {
        return new ApcuSimpleCache();
    }

    #[NoDiscard]
    public static function beforePipeline(Container $container): BeforePipeline
    {
        return new BeforePipeline();
    }

    #[NoDiscard]
    public static function cgiServerRequestFactory(Container $container): CgiServerRequestFactory
    {
        $factory = $container->resolve(ServerRequestFactoryInterface::class);

        return new CgiServerRequestFactory($factory);
    }

    #[NoDiscard]
    public static function serverRequest(Container $container): ServerRequestInterface
    {
        $factory = $container->resolve(CgiServerRequestFactory::class);

        return $factory->create();
    }

    #[NoDiscard]
    public static function collectingExporter(Container $container): CollectingExporter
    {
        return new CollectingExporter();
    }

    #[NoDiscard]
    public static function exceptFilter(Container $container): ExceptFilter
    {
        return new ExceptFilter([]);
    }

    #[NoDiscard]
    public static function filterCollectingExporter(Container $container): FilterExporter
    {
        $filter = $container->resolve(FilterInterface::class);
        $exporter = $container->resolve(CollectingExporter::class);

        return new FilterExporter($filter, $exporter);
    }

    #[NoDiscard]
    public static function filterFormatterWriterExporter(Container $container): FilterExporter
    {
        $filter = $container->resolve(FilterInterface::class);
        $exporter = $container->resolve(FormatterWriterExporter::class);

        return new FilterExporter($filter, $exporter);
    }

    #[NoDiscard]
    public static function mysqlMigrations(Container $container): MysqlMigrations
    {
        $query = $container->resolve(QueryInterface::class);
        $logger = $container->resolve(LoggerInterface::class);

        return new MysqlMigrations($query, $logger);
    }

    #[NoDiscard]
    public static function curlClient(Container $container): CurlClient
    {
        $responseFactory = $container->resolve(ResponseFactoryInterface::class);

        return new CurlClient($responseFactory);
    }

    #[NoDiscard]
    public static function errorCatcherMiddleware(Container $container): ErrorCatcherMiddleware
    {
        $responseFactory = $container->resolve(ResponseFactoryInterface::class);

        return new ErrorCatcherMiddleware($responseFactory);
    }

    #[NoDiscard]
    public static function errorLoggerMiddleware(Container $container): ErrorLoggerMiddleware
    {
        $logger = $container->resolve(LoggerInterface::class);

        return new ErrorLoggerMiddleware($logger);
    }

    #[NoDiscard]
    public static function errorRaiserMiddleware(Container $container): ErrorRaiserMiddleware
    {
        return new ErrorRaiserMiddleware();
    }

    #[NoDiscard]
    public static function exceptionCatcherMiddleware(Container $container): ExceptionCatcherMiddleware
    {
        $responseFactory = $container->resolve(ResponseFactoryInterface::class);

        return new ExceptionCatcherMiddleware($responseFactory);
    }

    #[NoDiscard]
    public static function exceptionLoggerMiddleware(Container $container): ExceptionLoggerMiddleware
    {
        $logger = $container->resolve(LoggerInterface::class);

        return new ExceptionLoggerMiddleware($logger);
    }

    #[NoDiscard]
    public static function fixedClock(Container $container): FixedClock
    {
        return new FixedClock();
    }

    #[NoDiscard]
    public static function formatterWriterExporter(Container $container): FormatterWriterExporter
    {
        $formatter = $container->resolve(FormatterInterface::class);
        $writer = $container->resolve(WriterInterface::class);

        return new FormatterWriterExporter($formatter, $writer);
    }

    #[NoDiscard]
    public static function interpolator(Container $container): Interpolator
    {
        return new Interpolator();
    }

    #[NoDiscard]
    public static function jsonEncoder(Container $container): JsonEncoder
    {
        return new JsonEncoder();
    }

    #[NoDiscard]
    public static function jsonFormatter(Container $container): JsonFormatter
    {
        $interpolator = $container->resolve(InterpolatorInterface::class);

        return new JsonFormatter($interpolator);
    }

    #[NoDiscard]
    public static function jsonResponder(Container $container): JsonResponder
    {
        $jsonWriter = $container->resolve(JsonWriter::class);
        $responseFactory = $container->resolve(ResponseFactoryInterface::class);

        return new JsonResponder($jsonWriter, $responseFactory);
    }

    #[NoDiscard]
    public static function jsonWriter(Container $container): JsonWriter
    {
        $streamWriter = $container->resolve(StreamWriter::class);
        $jsonEncoder = $container->resolve(JsonEncoder::class);

        return new JsonWriter($streamWriter, $jsonEncoder);
    }

    #[NoDiscard]
    public static function logger(Container $container): Logger
    {
        $exporter = $container->resolve(ExporterInterface::class);
        $recorder = $container->resolve(RecorderInterface::class);

        return new Logger($recorder, $exporter);
    }

    #[NoDiscard]
    public static function mysql(Container $container): Mysql
    {
        $factory = $container->resolve(MysqlFactory::class);
        $settings = $container->resolve(MysqlSettingsInterface::class);

        return $factory->create($settings);
    }

    #[NoDiscard]
    public static function mysqlFactory(Container $container): MysqlFactory
    {
        return new MysqlFactory();
    }

    #[NoDiscard]
    public static function mysqlLocker(Container $container): MysqlLocker
    {
        $query = $container->resolve(QueryInterface::class);

        return new MysqlLocker($query);
    }

    #[NoDiscard]
    public static function mysqlProbe(Container $container): MysqlProbe
    {
        $query = $container->resolve(QueryInterface::class);

        return new MysqlProbe($query);
    }

    #[NoDiscard]
    public static function negativeCatcherMiddleware(Container $container): NegativeCatcherMiddleware
    {
        $responseFactory = $container->resolve(ResponseFactoryInterface::class);

        return new NegativeCatcherMiddleware($responseFactory);
    }

    #[NoDiscard]
    public static function noContentRequestHandler(Container $container): NoContentRequestHandler
    {
        $responseFactory = $container->resolve(ResponseFactoryInterface::class);

        return new NoContentRequestHandler($responseFactory);
    }

    #[NoDiscard]
    public static function notFoundRequestHandler(Container $container): NotFoundRequestHandler
    {
        $responseFactory = $container->resolve(ResponseFactoryInterface::class);

        return new NotFoundRequestHandler($responseFactory);
    }

    #[NoDiscard]
    public static function onlyFilter(Container $container): OnlyFilter
    {
        return new OnlyFilter(['notice', 'warning', 'error', 'critical', 'alert', 'emergency']);
    }

    #[NoDiscard]
    public static function nowClock(Container $container): NowClock
    {
        return new NowClock();
    }

    #[NoDiscard]
    public static function nullMiddleware(Container $container): NullMiddleware
    {
        return new NullMiddleware();
    }

    #[NoDiscard]
    public static function nullSimpleCache(Container $container): NullSimpleCache
    {
        return new NullSimpleCache();
    }

    #[NoDiscard]
    public static function okRequestHandler(Container $container): OkRequestHandler
    {
        $responseFactory = $container->resolve(ResponseFactoryInterface::class);

        return new OkRequestHandler($responseFactory);
    }

    #[NoDiscard]
    public static function mysqlQuery(Container $container): MysqlQuery
    {
        $pdo = $container->resolve(PDO::class);

        return new MysqlQuery($pdo);
    }

    #[NoDiscard]
    public static function mysqlSettings(Container $container): MysqlSettings
    {
        $factory = $container->resolve(MysqlSettingsFactory::class);

        return $factory->createFrom([
            'host' => $container->get('MYSQL_HOST'),
            'port' => '',
            'dbname' => $container->get('MYSQL_DATABASE'),
            'socket' => '',
            'username' => $container->get('MYSQL_USER'),
            'password' => $container->get('MYSQL_PASSWORD_FILE'),
            'options' => [],
        ]);
    }

    #[NoDiscard]
    public static function mysqlSettingsFactory(Container $container): MysqlSettingsFactory
    {
        return new MysqlSettingsFactory();
    }

    #[NoDiscard]
    public static function pipelineResolver(Container $container): PipelineResolver
    {
        return new PipelineResolver($container);
    }

    #[NoDiscard]
    public static function recorder(Container $container): Recorder
    {
        $clock = $container->resolve(ClockInterface::class);

        return new Recorder($clock);
    }

    #[NoDiscard]
    public static function requestFactory(Container $container): RequestFactory
    {
        $streamFactory = $container->resolve(StreamFactoryInterface::class);
        $uriFactory = $container->resolve(UriFactoryInterface::class);

        return new RequestFactory($streamFactory, $uriFactory);
    }

    #[NoDiscard]
    public static function requireParsedBodyMiddleware(Container $container): RequireParsedBodyMiddleware
    {
        $responseFactory = $container->resolve(ResponseFactoryInterface::class);

        return new RequireParsedBodyMiddleware($responseFactory);
    }

    #[NoDiscard]
    public static function resourceWriter(Container $container): ResourceWriter
    {
        $resource = fopen('php://stderr', 'w');

        if (!is_resource($resource)) {
            throw new UnexpectedValueException('fopen');
        }

        return new ResourceWriter($resource);
    }

    #[NoDiscard]
    public static function responseEmitter(Container $container): ResponseEmitter
    {
        return new ResponseEmitter();
    }

    #[NoDiscard]
    public static function responseFactory(Container $container): ResponseFactory
    {
        $streamFactory = $container->resolve(StreamFactoryInterface::class);

        return new ResponseFactory($streamFactory);
    }

    #[NoDiscard]
    public static function routeMatcher(Container $container): RouteMatcher
    {
        $registry = $container->resolve(RouteSettings::class);

        return new RouteMatcher($registry);
    }

    #[NoDiscard]
    public static function routeRequestHandler(Container $container): RouteRequestHandler
    {
        $after = $container->resolve(AfterPipeline::class);
        $before = $container->resolve(BeforePipeline::class);
        $matcher = $container->resolve(RouteMatcher::class);
        $resolver = $container->resolve(PipelineResolver::class);

        return new RouteRequestHandler($matcher, $resolver, $before, $after);
    }

    #[NoDiscard]
    public static function serverRequestFactory(Container $container): ServerRequestFactory
    {
        $streamFactory = $container->resolve(StreamFactoryInterface::class);
        $uriFactory = $container->resolve(UriFactoryInterface::class);

        return new ServerRequestFactory($streamFactory, $uriFactory);
    }

    #[NoDiscard]
    public static function streamFactory(Container $container): StreamFactory
    {
        return new StreamFactory();
    }

    #[NoDiscard]
    public static function streamWriter(Container $container): StreamWriter
    {
        return new StreamWriter();
    }

    #[NoDiscard]
    public static function throwableCatcherMiddleware(Container $container): ThrowableCatcherMiddleware
    {
        $responseFactory = $container->resolve(ResponseFactoryInterface::class);

        return new ThrowableCatcherMiddleware($responseFactory);
    }

    #[NoDiscard]
    public static function throwableLoggerMiddleware(Container $container): ThrowableLoggerMiddleware
    {
        $logger = $container->resolve(LoggerInterface::class);

        return new ThrowableLoggerMiddleware($logger);
    }

    #[NoDiscard]
    public static function uploadedFileFactory(Container $container): UploadedFileFactory
    {
        return new UploadedFileFactory();
    }

    #[NoDiscard]
    public static function uriFactory(Container $container): UriFactory
    {
        return new UriFactory();
    }

    #[NoDiscard]
    public static function withRequestCookiesMiddleware(Container $container): WithRequestCookiesMiddleware
    {
        return new WithRequestCookiesMiddleware();
    }

    #[NoDiscard]
    public static function withRequestFormMiddleware(Container $container): WithRequestFormMiddleware
    {
        $streamFactory = $container->resolve(StreamFactoryInterface::class);
        $uploadedFileFactory = $container->resolve(UploadedFileFactoryInterface::class);

        return new WithRequestFormMiddleware($streamFactory, $uploadedFileFactory);
    }

    #[NoDiscard]
    public static function withRequestHeadersMiddleware(Container $container): WithRequestHeadersMiddleware
    {
        return new WithRequestHeadersMiddleware();
    }

    #[NoDiscard]
    public static function withRequestJsonMiddleware(Container $container): WithRequestJsonMiddleware
    {
        return new WithRequestJsonMiddleware();
    }

    #[NoDiscard]
    public static function withRequestQueryMiddleware(Container $container): WithRequestQueryMiddleware
    {
        return new WithRequestQueryMiddleware();
    }

    #[NoDiscard]
    public static function migrator(Container $container): Migrator
    {
        $pdo = $container->resolve(QueryInterface::class);
        $logger = $container->resolve(LoggerInterface::class);
        $migrations = $container->resolve(MigrationsInterface::class);
        $locker = $container->resolve(LockerInterface::class);

        return new Migrator($pdo, $logger, $migrations, $locker);
    }
}
