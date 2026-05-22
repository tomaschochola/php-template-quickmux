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
use TomasChochola\Migrations\Oracle\Database\OracleMigrations;
use TomasChochola\Oracle\Database\OracleConnection;
use TomasChochola\Oracle\Database\OracleDatabase;
use TomasChochola\Oracle\Database\OracleSettingsFactory;
use TomasChochola\Psr\Clock\NowClock;
use TomasChochola\Psr\Http\Factory\CgiServerRequestFactory;
use TomasChochola\Psr\Http\Factory\ResponseFactory;
use TomasChochola\Psr\Http\Factory\ServerRequestFactory;
use TomasChochola\Psr\Http\Factory\StreamFactory;
use TomasChochola\Psr\Http\Factory\UriFactory;
use TomasChochola\Psr\Http\RequestHandlers\NotFoundRequestHandler;
use TomasChochola\Psr\Http\RequestHandlers\OkRequestHandler;
use TomasChochola\Psr\Http\RequestHandlers\ResponseEmitter;
use TomasChochola\Psr\Http\RequestHandlers\ResponseEmitterInterface;
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
use function file_get_contents;
use function fopen;
use function is_resource;
use function is_string;
use function mb_trim;

use const OCI_DEFAULT;

/**
 * @no-named-arguments
 */
readonly class Resolver
{
    private function __construct()
    {
        throw new LogicException('never');
    }

    #[NoDiscard()]
    public static function ClockInterface(ContainerInterface $container): ClockInterface
    {
        return new NowClock();
    }

    #[NoDiscard()]
    public static function ExporterInterface(ContainerInterface $container): ExporterInterface
    {
        $filter = $container->get(FilterInterface::class);
        $formatter = $container->get(FormatterInterface::class);
        $writer = $container->get(WriterInterface::class);

        assert($filter instanceof FilterInterface);
        assert($formatter instanceof FormatterInterface);
        assert($writer instanceof WriterInterface);

        return new FilterExporter($filter, new FormatterWriterExporter($formatter, $writer));
    }

    #[NoDiscard()]
    public static function FilterInterface(ContainerInterface $container): FilterInterface
    {
        return new OnlyFilter(['notice', 'warning', 'error', 'critical', 'alert', 'emergency']);
    }

    #[NoDiscard()]
    public static function FormatterInterface(ContainerInterface $container): FormatterInterface
    {
        $interpolator = $container->get(InterpolatorInterface::class);

        assert($interpolator instanceof InterpolatorInterface);

        return new JsonFormatter($interpolator);
    }

    #[NoDiscard()]
    public static function InterpolatorInterface(ContainerInterface $container): InterpolatorInterface
    {
        return new Interpolator();
    }

    #[NoDiscard()]
    public static function LoggerInterface(ContainerInterface $container): LoggerInterface
    {
        $exporter = $container->get(ExporterInterface::class);
        $recorder = $container->get(RecorderInterface::class);

        assert($exporter instanceof ExporterInterface);
        assert($recorder instanceof RecorderInterface);

        return new Logger($recorder, $exporter);
    }

    #[NoDiscard()]
    public static function MigrationsInterface(ContainerInterface $container): MigrationsInterface
    {
        $oracle = $container->get(OracleConnection::class);
        $logger = $container->get(LoggerInterface::class);

        assert($oracle instanceof OracleConnection);
        assert($logger instanceof LoggerInterface);

        return new OracleMigrations($oracle, $logger);
    }

    #[NoDiscard()]
    public static function MigratorInterface(ContainerInterface $container): MigratorInterface
    {
        $logger = $container->get(LoggerInterface::class);
        $migrations = $container->get(MigrationsInterface::class);

        assert($logger instanceof LoggerInterface);
        assert($migrations instanceof MigrationsInterface);

        return new Migrator($logger, $migrations);
    }

    #[NoDiscard()]
    public static function NotFoundRequestHandler(ContainerInterface $container): RequestHandlerInterface
    {
        $factory = $container->get(ResponseFactoryInterface::class);

        assert($factory instanceof ResponseFactoryInterface);

        return new NotFoundRequestHandler($factory);
    }

    #[NoDiscard()]
    public static function OkRequestHandler(ContainerInterface $container): RequestHandlerInterface
    {
        $factory = $container->get(ResponseFactoryInterface::class);

        assert($factory instanceof ResponseFactoryInterface);

        return new OkRequestHandler($factory);
    }

    #[NoDiscard()]
    public static function OracleConnection(ContainerInterface $container): OracleConnection
    {
        $database = $container->get(OracleDatabase::class);

        assert($database instanceof OracleDatabase);

        return $database->connect();
    }

    #[NoDiscard()]
    public static function OracleDatabase(ContainerInterface $container): OracleDatabase
    {
        $host = $container->get('ORACLE_HOST');
        $database = $container->get('ORACLE_DATABASE');
        $user = $container->get('ORACLE_USER');
        $passwordFile = $container->get('ORACLE_PASSWORD');

        if (!is_string($host) || !is_string($database) || !is_string($user) || !is_string($passwordFile)) {
            throw new UnexpectedValueException('oracle settings');
        }

        $password = file_get_contents($passwordFile);

        if (!is_string($password)) {
            throw new UnexpectedValueException('file_get_contents');
        }

        $settings = (new OracleSettingsFactory())->createFrom([
            'username' => $user,
            'password' => mb_trim($password),
            'connectionString' => '//' . $host . '/' . $database,
            'encoding' => 'AL32UTF8',
            'sessionMode' => OCI_DEFAULT,
        ]);

        return new OracleDatabase($settings);
    }

    #[NoDiscard()]
    public static function RecorderInterface(ContainerInterface $container): RecorderInterface
    {
        $clock = $container->get(ClockInterface::class);

        assert($clock instanceof ClockInterface);

        return new Recorder($clock);
    }

    #[NoDiscard()]
    public static function ResponseEmitterInterface(ContainerInterface $container): ResponseEmitterInterface
    {
        return new ResponseEmitter();
    }

    #[NoDiscard()]
    public static function ResponseFactoryInterface(ContainerInterface $container): ResponseFactoryInterface
    {
        $factory = $container->get(StreamFactoryInterface::class);

        assert($factory instanceof StreamFactoryInterface);

        return new ResponseFactory($factory);
    }

    #[NoDiscard()]
    public static function RouteRequestHandler(ContainerInterface $container): RequestHandlerInterface
    {
        $settings = $container->get(RouteSettingsInterface::class);

        assert($settings instanceof RouteSettingsInterface);

        return new RouteRequestHandler($settings, $container);
    }

    #[NoDiscard()]
    public static function ServerRequestFactoryInterface(ContainerInterface $container): ServerRequestFactoryInterface
    {
        $stream = $container->get(StreamFactoryInterface::class);
        $uri = $container->get(UriFactoryInterface::class);

        assert($stream instanceof StreamFactoryInterface);
        assert($uri instanceof UriFactoryInterface);

        return new ServerRequestFactory($stream, $uri);
    }

    #[NoDiscard()]
    public static function ServerRequestInterface(ContainerInterface $container): ServerRequestInterface
    {
        $factory = $container->get(ServerRequestFactoryInterface::class);

        assert($factory instanceof ServerRequestFactoryInterface);

        return (new CgiServerRequestFactory($factory))->create();
    }

    #[NoDiscard()]
    public static function StreamFactoryInterface(ContainerInterface $container): StreamFactoryInterface
    {
        return new StreamFactory();
    }

    #[NoDiscard()]
    public static function ThrowableCatcherMiddleware(ContainerInterface $container): MiddlewareInterface
    {
        $factory = $container->get(ResponseFactoryInterface::class);

        assert($factory instanceof ResponseFactoryInterface);

        return new ThrowableCatcherMiddleware($factory);
    }

    #[NoDiscard()]
    public static function ThrowableLoggerMiddleware(ContainerInterface $container): MiddlewareInterface
    {
        $logger = $container->get(LoggerInterface::class);

        assert($logger instanceof LoggerInterface);

        return new ThrowableLoggerMiddleware($logger);
    }

    #[NoDiscard()]
    public static function UriFactoryInterface(ContainerInterface $container): UriFactoryInterface
    {
        return new UriFactory();
    }

    #[NoDiscard()]
    public static function WriterInterface(ContainerInterface $container): WriterInterface
    {
        $resource = fopen('php://stderr', 'wb');

        if (!is_resource($resource)) {
            throw new UnexpectedValueException('fopen');
        }

        return new ResourceWriter($resource);
    }
}
