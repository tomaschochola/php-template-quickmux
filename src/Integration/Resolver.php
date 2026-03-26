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
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Log\LoggerInterface;
use TomasChochola\Migrations\MigrationsInterface;
use TomasChochola\Migrations\Migrator;
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

use function assert;
use function fopen;
use function is_resource;

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
    public static function afterPipeline(ContainerInterface $container): AfterPipeline
    {
        return new AfterPipeline();
    }

    #[NoDiscard]
    public static function apcuSimpleCache(ContainerInterface $container): ApcuSimpleCache
    {
        return new ApcuSimpleCache();
    }

    #[NoDiscard]
    public static function beforePipeline(ContainerInterface $container): BeforePipeline
    {
        return new BeforePipeline();
    }

    #[NoDiscard]
    public static function cgiServerRequestFactory(ContainerInterface $container): CgiServerRequestFactory
    {
        $factory = $container->get(ServerRequestFactoryInterface::class);

        assert($factory instanceof ServerRequestFactoryInterface);

        return new CgiServerRequestFactory($factory);
    }

    #[NoDiscard]
    public static function collectingExporter(ContainerInterface $container): CollectingExporter
    {
        return new CollectingExporter();
    }

    #[NoDiscard]
    public static function curlClient(ContainerInterface $container): CurlClient
    {
        $responseFactory = $container->get(ResponseFactoryInterface::class);

        assert($responseFactory instanceof ResponseFactoryInterface);

        return new CurlClient($responseFactory);
    }

    #[NoDiscard]
    public static function errorCatcherMiddleware(ContainerInterface $container): ErrorCatcherMiddleware
    {
        $responseFactory = $container->get(ResponseFactoryInterface::class);

        assert($responseFactory instanceof ResponseFactoryInterface);

        return new ErrorCatcherMiddleware($responseFactory);
    }

    #[NoDiscard]
    public static function errorLoggerMiddleware(ContainerInterface $container): ErrorLoggerMiddleware
    {
        $logger = $container->get(LoggerInterface::class);

        assert($logger instanceof LoggerInterface);

        return new ErrorLoggerMiddleware($logger);
    }

    #[NoDiscard]
    public static function errorRaiserMiddleware(ContainerInterface $container): ErrorRaiserMiddleware
    {
        return new ErrorRaiserMiddleware();
    }

    #[NoDiscard]
    public static function exceptFilter(ContainerInterface $container): ExceptFilter
    {
        return new ExceptFilter([]);
    }

    #[NoDiscard]
    public static function exceptionCatcherMiddleware(ContainerInterface $container): ExceptionCatcherMiddleware
    {
        $responseFactory = $container->get(ResponseFactoryInterface::class);

        assert($responseFactory instanceof ResponseFactoryInterface);

        return new ExceptionCatcherMiddleware($responseFactory);
    }

    #[NoDiscard]
    public static function exceptionLoggerMiddleware(ContainerInterface $container): ExceptionLoggerMiddleware
    {
        $logger = $container->get(LoggerInterface::class);

        assert($logger instanceof LoggerInterface);

        return new ExceptionLoggerMiddleware($logger);
    }

    #[NoDiscard]
    public static function filterCollectingExporter(ContainerInterface $container): FilterExporter
    {
        $filter = $container->get(FilterInterface::class);
        $exporter = $container->get(CollectingExporter::class);

        assert($filter instanceof FilterInterface);
        assert($exporter instanceof CollectingExporter);

        return new FilterExporter($filter, $exporter);
    }

    #[NoDiscard]
    public static function filterFormatterWriterExporter(ContainerInterface $container): FilterExporter
    {
        $filter = $container->get(FilterInterface::class);
        $exporter = $container->get(FormatterWriterExporter::class);

        assert($filter instanceof FilterInterface);
        assert($exporter instanceof FormatterWriterExporter);

        return new FilterExporter($filter, $exporter);
    }

    #[NoDiscard]
    public static function fixedClock(ContainerInterface $container): FixedClock
    {
        return new FixedClock();
    }

    #[NoDiscard]
    public static function formatterWriterExporter(ContainerInterface $container): FormatterWriterExporter
    {
        $formatter = $container->get(FormatterInterface::class);
        $writer = $container->get(WriterInterface::class);

        assert($formatter instanceof FormatterInterface);
        assert($writer instanceof WriterInterface);

        return new FormatterWriterExporter($formatter, $writer);
    }

    #[NoDiscard]
    public static function interpolator(ContainerInterface $container): Interpolator
    {
        return new Interpolator();
    }

    #[NoDiscard]
    public static function jsonEncoder(ContainerInterface $container): JsonEncoder
    {
        return new JsonEncoder();
    }

    #[NoDiscard]
    public static function jsonFormatter(ContainerInterface $container): JsonFormatter
    {
        $interpolator = $container->get(InterpolatorInterface::class);

        assert($interpolator instanceof InterpolatorInterface);

        return new JsonFormatter($interpolator);
    }

    #[NoDiscard]
    public static function jsonResponder(ContainerInterface $container): JsonResponder
    {
        $jsonWriter = $container->get(JsonWriter::class);
        $responseFactory = $container->get(ResponseFactoryInterface::class);

        assert($jsonWriter instanceof JsonWriter);
        assert($responseFactory instanceof ResponseFactoryInterface);

        return new JsonResponder($jsonWriter, $responseFactory);
    }

    #[NoDiscard]
    public static function jsonWriter(ContainerInterface $container): JsonWriter
    {
        $streamWriter = $container->get(StreamWriter::class);
        $jsonEncoder = $container->get(JsonEncoder::class);

        assert($streamWriter instanceof StreamWriter);
        assert($jsonEncoder instanceof JsonEncoder);

        return new JsonWriter($streamWriter, $jsonEncoder);
    }

    #[NoDiscard]
    public static function logger(ContainerInterface $container): Logger
    {
        $exporter = $container->get(ExporterInterface::class);
        $recorder = $container->get(RecorderInterface::class);

        assert($exporter instanceof ExporterInterface);
        assert($recorder instanceof RecorderInterface);

        return new Logger($recorder, $exporter);
    }

    #[NoDiscard]
    public static function migrator(ContainerInterface $container): Migrator
    {
        $pdo = $container->get(QueryInterface::class);
        $logger = $container->get(LoggerInterface::class);
        $migrations = $container->get(MigrationsInterface::class);
        $locker = $container->get(LockerInterface::class);

        assert($pdo instanceof QueryInterface);
        assert($logger instanceof LoggerInterface);
        assert($migrations instanceof MigrationsInterface);
        assert($locker instanceof LockerInterface);

        return new Migrator($pdo, $logger, $migrations, $locker);
    }

    #[NoDiscard]
    public static function mysql(ContainerInterface $container): Mysql
    {
        $factory = $container->get(MysqlFactory::class);
        $settings = $container->get(MysqlSettingsInterface::class);

        assert($factory instanceof MysqlFactory);
        assert($settings instanceof MysqlSettingsInterface);

        return $factory->create($settings);
    }

    #[NoDiscard]
    public static function mysqlFactory(ContainerInterface $container): MysqlFactory
    {
        return new MysqlFactory();
    }

    #[NoDiscard]
    public static function mysqlLocker(ContainerInterface $container): MysqlLocker
    {
        $query = $container->get(QueryInterface::class);

        assert($query instanceof QueryInterface);

        return new MysqlLocker($query);
    }

    #[NoDiscard]
    public static function mysqlMigrations(ContainerInterface $container): MysqlMigrations
    {
        $query = $container->get(QueryInterface::class);
        $logger = $container->get(LoggerInterface::class);

        assert($query instanceof QueryInterface);
        assert($logger instanceof LoggerInterface);

        return new MysqlMigrations($query, $logger);
    }

    #[NoDiscard]
    public static function mysqlProbe(ContainerInterface $container): MysqlProbe
    {
        $query = $container->get(QueryInterface::class);

        assert($query instanceof QueryInterface);

        return new MysqlProbe($query);
    }

    #[NoDiscard]
    public static function mysqlQuery(ContainerInterface $container): MysqlQuery
    {
        $pdo = $container->get(PDO::class);

        assert($pdo instanceof PDO);

        return new MysqlQuery($pdo);
    }

    #[NoDiscard]
    public static function mysqlSettings(ContainerInterface $container): MysqlSettings
    {
        $factory = $container->get(MysqlSettingsFactory::class);

        assert($factory instanceof MysqlSettingsFactory);

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
    public static function mysqlSettingsFactory(ContainerInterface $container): MysqlSettingsFactory
    {
        return new MysqlSettingsFactory();
    }

    #[NoDiscard]
    public static function negativeCatcherMiddleware(ContainerInterface $container): NegativeCatcherMiddleware
    {
        $responseFactory = $container->get(ResponseFactoryInterface::class);

        assert($responseFactory instanceof ResponseFactoryInterface);

        return new NegativeCatcherMiddleware($responseFactory);
    }

    #[NoDiscard]
    public static function noContentRequestHandler(ContainerInterface $container): NoContentRequestHandler
    {
        $responseFactory = $container->get(ResponseFactoryInterface::class);

        assert($responseFactory instanceof ResponseFactoryInterface);

        return new NoContentRequestHandler($responseFactory);
    }

    #[NoDiscard]
    public static function notFoundRequestHandler(ContainerInterface $container): NotFoundRequestHandler
    {
        $responseFactory = $container->get(ResponseFactoryInterface::class);

        assert($responseFactory instanceof ResponseFactoryInterface);

        return new NotFoundRequestHandler($responseFactory);
    }

    #[NoDiscard]
    public static function nowClock(ContainerInterface $container): NowClock
    {
        return new NowClock();
    }

    #[NoDiscard]
    public static function nullMiddleware(ContainerInterface $container): NullMiddleware
    {
        return new NullMiddleware();
    }

    #[NoDiscard]
    public static function nullSimpleCache(ContainerInterface $container): NullSimpleCache
    {
        return new NullSimpleCache();
    }

    #[NoDiscard]
    public static function okRequestHandler(ContainerInterface $container): OkRequestHandler
    {
        $responseFactory = $container->get(ResponseFactoryInterface::class);

        assert($responseFactory instanceof ResponseFactoryInterface);

        return new OkRequestHandler($responseFactory);
    }

    #[NoDiscard]
    public static function onlyFilter(ContainerInterface $container): OnlyFilter
    {
        return new OnlyFilter(['notice', 'warning', 'error', 'critical', 'alert', 'emergency']);
    }

    #[NoDiscard]
    public static function pipelineResolver(ContainerInterface $container): PipelineResolver
    {
        return new PipelineResolver($container);
    }

    #[NoDiscard]
    public static function recorder(ContainerInterface $container): Recorder
    {
        $clock = $container->get(ClockInterface::class);

        assert($clock instanceof ClockInterface);

        return new Recorder($clock);
    }

    #[NoDiscard]
    public static function requestFactory(ContainerInterface $container): RequestFactory
    {
        $streamFactory = $container->get(StreamFactoryInterface::class);
        $uriFactory = $container->get(UriFactoryInterface::class);

        assert($streamFactory instanceof StreamFactoryInterface);
        assert($uriFactory instanceof UriFactoryInterface);

        return new RequestFactory($streamFactory, $uriFactory);
    }

    #[NoDiscard]
    public static function requireParsedBodyMiddleware(ContainerInterface $container): RequireParsedBodyMiddleware
    {
        $responseFactory = $container->get(ResponseFactoryInterface::class);

        assert($responseFactory instanceof ResponseFactoryInterface);

        return new RequireParsedBodyMiddleware($responseFactory);
    }

    #[NoDiscard]
    public static function resourceWriter(ContainerInterface $container): ResourceWriter
    {
        $resource = fopen('php://stderr', 'w');

        if (!is_resource($resource)) {
            throw new UnexpectedValueException('fopen');
        }

        return new ResourceWriter($resource);
    }

    #[NoDiscard]
    public static function responseEmitter(ContainerInterface $container): ResponseEmitter
    {
        return new ResponseEmitter();
    }

    #[NoDiscard]
    public static function responseFactory(ContainerInterface $container): ResponseFactory
    {
        $streamFactory = $container->get(StreamFactoryInterface::class);

        assert($streamFactory instanceof StreamFactoryInterface);

        return new ResponseFactory($streamFactory);
    }

    #[NoDiscard]
    public static function routeMatcher(ContainerInterface $container): RouteMatcher
    {
        $registry = $container->get(RouteSettings::class);

        assert($registry instanceof RouteSettings);

        return new RouteMatcher($registry);
    }

    #[NoDiscard]
    public static function routeRequestHandler(ContainerInterface $container): RouteRequestHandler
    {
        $after = $container->get(AfterPipeline::class);
        $before = $container->get(BeforePipeline::class);
        $matcher = $container->get(RouteMatcher::class);
        $resolver = $container->get(PipelineResolver::class);

        assert($after instanceof AfterPipeline);
        assert($before instanceof BeforePipeline);
        assert($matcher instanceof RouteMatcher);
        assert($resolver instanceof PipelineResolver);

        return new RouteRequestHandler($matcher, $resolver, $before, $after);
    }

    #[NoDiscard]
    public static function serverRequest(ContainerInterface $container): ServerRequestInterface
    {
        $factory = $container->get(CgiServerRequestFactory::class);

        assert($factory instanceof CgiServerRequestFactory);

        return $factory->create();
    }

    #[NoDiscard]
    public static function serverRequestFactory(ContainerInterface $container): ServerRequestFactory
    {
        $streamFactory = $container->get(StreamFactoryInterface::class);
        $uriFactory = $container->get(UriFactoryInterface::class);

        assert($streamFactory instanceof StreamFactoryInterface);
        assert($uriFactory instanceof UriFactoryInterface);

        return new ServerRequestFactory($streamFactory, $uriFactory);
    }

    #[NoDiscard]
    public static function streamFactory(ContainerInterface $container): StreamFactory
    {
        return new StreamFactory();
    }

    #[NoDiscard]
    public static function streamWriter(ContainerInterface $container): StreamWriter
    {
        return new StreamWriter();
    }

    #[NoDiscard]
    public static function throwableCatcherMiddleware(ContainerInterface $container): ThrowableCatcherMiddleware
    {
        $responseFactory = $container->get(ResponseFactoryInterface::class);

        assert($responseFactory instanceof ResponseFactoryInterface);

        return new ThrowableCatcherMiddleware($responseFactory);
    }

    #[NoDiscard]
    public static function throwableLoggerMiddleware(ContainerInterface $container): ThrowableLoggerMiddleware
    {
        $logger = $container->get(LoggerInterface::class);

        assert($logger instanceof LoggerInterface);

        return new ThrowableLoggerMiddleware($logger);
    }

    #[NoDiscard]
    public static function uploadedFileFactory(ContainerInterface $container): UploadedFileFactory
    {
        return new UploadedFileFactory();
    }

    #[NoDiscard]
    public static function uriFactory(ContainerInterface $container): UriFactory
    {
        return new UriFactory();
    }

    #[NoDiscard]
    public static function withRequestCookiesMiddleware(ContainerInterface $container): WithRequestCookiesMiddleware
    {
        return new WithRequestCookiesMiddleware();
    }

    #[NoDiscard]
    public static function withRequestFormMiddleware(ContainerInterface $container): WithRequestFormMiddleware
    {
        $streamFactory = $container->get(StreamFactoryInterface::class);
        $uploadedFileFactory = $container->get(UploadedFileFactoryInterface::class);

        assert($streamFactory instanceof StreamFactoryInterface);
        assert($uploadedFileFactory instanceof UploadedFileFactoryInterface);

        return new WithRequestFormMiddleware($streamFactory, $uploadedFileFactory);
    }

    #[NoDiscard]
    public static function withRequestHeadersMiddleware(ContainerInterface $container): WithRequestHeadersMiddleware
    {
        return new WithRequestHeadersMiddleware();
    }

    #[NoDiscard]
    public static function withRequestJsonMiddleware(ContainerInterface $container): WithRequestJsonMiddleware
    {
        return new WithRequestJsonMiddleware();
    }

    #[NoDiscard]
    public static function withRequestQueryMiddleware(ContainerInterface $container): WithRequestQueryMiddleware
    {
        return new WithRequestQueryMiddleware();
    }
}
