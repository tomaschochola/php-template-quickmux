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
use Pdo\Mysql;
use Psr\Clock\ClockInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use TomasChochola\Migrations\MigrationsInterface;
use TomasChochola\Migrations\Migrator;
use TomasChochola\Migrations\MigratorInterface;
use TomasChochola\Migrations\Mysql\MysqlMigrations;
use TomasChochola\Pdo\LockerInterface;
use TomasChochola\Pdo\ProbeInterface;
use TomasChochola\Pdo\Mysql\MysqlFactory;
use TomasChochola\Pdo\Mysql\MysqlLocker;
use TomasChochola\Pdo\Mysql\MysqlProbe;
use TomasChochola\Pdo\Mysql\MysqlQuery;
use TomasChochola\Pdo\Mysql\MysqlSettingsFactory;
use TomasChochola\Pdo\QueryInterface;
use TomasChochola\Psr\Clock\NowClock;
use TomasChochola\Psr\Http\Factory\CgiServerRequestFactory;
use TomasChochola\Psr\Http\Factory\ResponseFactory;
use TomasChochola\Psr\Http\Factory\ServerRequestFactory;
use TomasChochola\Psr\Http\Factory\StreamFactory;
use TomasChochola\Psr\Http\Factory\UriFactory;
use TomasChochola\Psr\Http\RequestHandlers\AfterPipeline;
use TomasChochola\Psr\Http\RequestHandlers\AfterPipelineInterface;
use TomasChochola\Psr\Http\RequestHandlers\BeforePipeline;
use TomasChochola\Psr\Http\RequestHandlers\BeforePipelineInterface;
use TomasChochola\Psr\Http\RequestHandlers\ErrorRaiserMiddleware;
use TomasChochola\Psr\Http\RequestHandlers\NotFoundRequestHandler;
use TomasChochola\Psr\Http\RequestHandlers\OkRequestHandler;
use TomasChochola\Psr\Http\RequestHandlers\PipelineResolver;
use TomasChochola\Psr\Http\RequestHandlers\PipelineResolverInterface;
use TomasChochola\Psr\Http\RequestHandlers\ResponseEmitter;
use TomasChochola\Psr\Http\RequestHandlers\ResponseEmitterInterface;
use TomasChochola\Psr\Http\RequestHandlers\RouteMatcher;
use TomasChochola\Psr\Http\RequestHandlers\RouteMatcherInterface;
use TomasChochola\Psr\Http\RequestHandlers\RouteRequestHandler;
use TomasChochola\Psr\Http\RequestHandlers\RouteSettingsInterface;
use TomasChochola\Psr\Http\RequestHandlers\ThrowableCatcherMiddleware;
use TomasChochola\Psr\Http\RequestHandlers\ThrowableLoggerMiddleware;
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
    public static function AfterPipelineInterface(ContainerInterface $container): AfterPipelineInterface
    {
        return new AfterPipeline();
    }

    #[NoDiscard]
    public static function BeforePipelineInterface(ContainerInterface $container): BeforePipelineInterface
    {
        return new BeforePipeline();
    }

    #[NoDiscard]
    public static function ClockInterface(ContainerInterface $container): ClockInterface
    {
        return new NowClock();
    }

    #[NoDiscard]
    public static function ErrorRaiserMiddleware(ContainerInterface $container): MiddlewareInterface
    {
        return new ErrorRaiserMiddleware();
    }

    #[NoDiscard]
    public static function ExporterInterface(ContainerInterface $container): ExporterInterface
    {
        $filter = $container->get(FilterInterface::class);
        $formatter = $container->get(FormatterInterface::class);
        $writer = $container->get(WriterInterface::class);

        assert($filter instanceof FilterInterface);
        assert($formatter instanceof FormatterInterface);
        assert($writer instanceof WriterInterface);

        return new FilterExporter(
            $filter,
            new FormatterWriterExporter($formatter, $writer),
        );
    }

    #[NoDiscard]
    public static function FilterInterface(ContainerInterface $container): FilterInterface
    {
        return new OnlyFilter(['notice', 'warning', 'error', 'critical', 'alert', 'emergency']);
    }

    #[NoDiscard]
    public static function FormatterInterface(ContainerInterface $container): FormatterInterface
    {
        $interpolator = $container->get(InterpolatorInterface::class);

        assert($interpolator instanceof InterpolatorInterface);

        return new JsonFormatter($interpolator);
    }

    #[NoDiscard]
    public static function InterpolatorInterface(ContainerInterface $container): InterpolatorInterface
    {
        return new Interpolator();
    }

    #[NoDiscard]
    public static function LockerInterface(ContainerInterface $container): LockerInterface
    {
        $query = $container->get(QueryInterface::class);

        assert($query instanceof QueryInterface);

        return new MysqlLocker($query);
    }

    #[NoDiscard]
    public static function LoggerInterface(ContainerInterface $container): LoggerInterface
    {
        $exporter = $container->get(ExporterInterface::class);
        $recorder = $container->get(RecorderInterface::class);

        assert($exporter instanceof ExporterInterface);
        assert($recorder instanceof RecorderInterface);

        return new Logger($recorder, $exporter);
    }

    #[NoDiscard]
    public static function MigrationsInterface(ContainerInterface $container): MigrationsInterface
    {
        $query = $container->get(QueryInterface::class);
        $logger = $container->get(LoggerInterface::class);

        assert($query instanceof QueryInterface);
        assert($logger instanceof LoggerInterface);

        return new MysqlMigrations($query, $logger);
    }

    #[NoDiscard]
    public static function MigratorInterface(ContainerInterface $container): MigratorInterface
    {
        $query = $container->get(QueryInterface::class);
        $logger = $container->get(LoggerInterface::class);
        $migrations = $container->get(MigrationsInterface::class);
        $locker = $container->get(LockerInterface::class);

        assert($query instanceof QueryInterface);
        assert($logger instanceof LoggerInterface);
        assert($migrations instanceof MigrationsInterface);
        assert($locker instanceof LockerInterface);

        return new Migrator($query, $logger, $migrations, $locker);
    }

    #[NoDiscard]
    public static function NotFoundRequestHandler(ContainerInterface $container): RequestHandlerInterface
    {
        $factory = $container->get(ResponseFactoryInterface::class);

        assert($factory instanceof ResponseFactoryInterface);

        return new NotFoundRequestHandler($factory);
    }

    #[NoDiscard]
    public static function OkRequestHandler(ContainerInterface $container): RequestHandlerInterface
    {
        $factory = $container->get(ResponseFactoryInterface::class);

        assert($factory instanceof ResponseFactoryInterface);

        return new OkRequestHandler($factory);
    }

    #[NoDiscard]
    public static function PipelineResolverInterface(ContainerInterface $container): PipelineResolverInterface
    {
        return new PipelineResolver($container);
    }

    #[NoDiscard]
    public static function ProbeInterface(ContainerInterface $container): ProbeInterface
    {
        $query = $container->get(QueryInterface::class);

        assert($query instanceof QueryInterface);

        return new MysqlProbe($query);
    }

    #[NoDiscard]
    public static function QueryInterface(ContainerInterface $container): QueryInterface
    {
        $pdo = (new MysqlFactory())->create(
            (new MysqlSettingsFactory())->createFrom([
                'host' => $container->get('MYSQL_HOST'),
                'port' => '',
                'dbname' => $container->get('MYSQL_DATABASE'),
                'socket' => '',
                'username' => $container->get('MYSQL_USER'),
                'password' => $container->get('MYSQL_PASSWORD_FILE'),
                'options' => [],
            ]),
        );

        assert($pdo instanceof Mysql);

        return new MysqlQuery($pdo);
    }

    #[NoDiscard]
    public static function RecorderInterface(ContainerInterface $container): RecorderInterface
    {
        $clock = $container->get(ClockInterface::class);

        assert($clock instanceof ClockInterface);

        return new Recorder($clock);
    }

    #[NoDiscard]
    public static function ResponseEmitterInterface(ContainerInterface $container): ResponseEmitterInterface
    {
        return new ResponseEmitter();
    }

    #[NoDiscard]
    public static function ResponseFactoryInterface(ContainerInterface $container): ResponseFactoryInterface
    {
        $factory = $container->get(StreamFactoryInterface::class);

        assert($factory instanceof StreamFactoryInterface);

        return new ResponseFactory($factory);
    }

    #[NoDiscard]
    public static function RouteMatcherInterface(ContainerInterface $container): RouteMatcherInterface
    {
        $settings = $container->get(RouteSettingsInterface::class);

        assert($settings instanceof RouteSettingsInterface);

        return new RouteMatcher($settings);
    }

    #[NoDiscard]
    public static function RouteRequestHandler(ContainerInterface $container): RequestHandlerInterface
    {
        $after = $container->get(AfterPipelineInterface::class);
        $before = $container->get(BeforePipelineInterface::class);
        $matcher = $container->get(RouteMatcherInterface::class);
        $resolver = $container->get(PipelineResolverInterface::class);

        assert($after instanceof AfterPipelineInterface);
        assert($before instanceof BeforePipelineInterface);
        assert($matcher instanceof RouteMatcherInterface);
        assert($resolver instanceof PipelineResolverInterface);

        return new RouteRequestHandler($matcher, $resolver, $before, $after);
    }

    #[NoDiscard]
    public static function ServerRequestFactoryInterface(ContainerInterface $container): ServerRequestFactoryInterface
    {
        $stream = $container->get(StreamFactoryInterface::class);
        $uri = $container->get(UriFactoryInterface::class);

        assert($stream instanceof StreamFactoryInterface);
        assert($uri instanceof UriFactoryInterface);

        return new ServerRequestFactory($stream, $uri);
    }

    #[NoDiscard]
    public static function ServerRequestInterface(ContainerInterface $container): ServerRequestInterface
    {
        $factory = $container->get(ServerRequestFactoryInterface::class);

        assert($factory instanceof ServerRequestFactoryInterface);

        return (new CgiServerRequestFactory($factory))->create();
    }

    #[NoDiscard]
    public static function StreamFactoryInterface(ContainerInterface $container): StreamFactoryInterface
    {
        return new StreamFactory();
    }

    #[NoDiscard]
    public static function ThrowableCatcherMiddleware(ContainerInterface $container): MiddlewareInterface
    {
        $factory = $container->get(ResponseFactoryInterface::class);

        assert($factory instanceof ResponseFactoryInterface);

        return new ThrowableCatcherMiddleware($factory);
    }

    #[NoDiscard]
    public static function ThrowableLoggerMiddleware(ContainerInterface $container): MiddlewareInterface
    {
        $logger = $container->get(LoggerInterface::class);

        assert($logger instanceof LoggerInterface);

        return new ThrowableLoggerMiddleware($logger);
    }

    #[NoDiscard]
    public static function UriFactoryInterface(ContainerInterface $container): UriFactoryInterface
    {
        return new UriFactory();
    }

    #[NoDiscard]
    public static function WriterInterface(ContainerInterface $container): WriterInterface
    {
        $resource = fopen('php://stderr', 'w');

        if (!is_resource($resource)) {
            throw new UnexpectedValueException('fopen');
        }

        return new ResourceWriter($resource);
    }
}
