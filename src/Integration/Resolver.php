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

namespace TomasChochola\Template\Quickmux\Integration;

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
use TomasChochola\Database\Mysql\Contract\QueryInterface;
use TomasChochola\Database\Mysql\MysqlFactory;
use TomasChochola\Database\Mysql\MysqlQuery;
use TomasChochola\Database\Mysql\MysqlSettingsFactory;
use TomasChochola\Migrations\MigrationsInterface;
use TomasChochola\Migrations\Migrator;
use TomasChochola\Migrations\MigratorInterface;
use TomasChochola\Migrations\Mysql\MysqlMigrations;
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
use function fopen;
use function is_resource;
use function is_string;

use const PHP_SAPI;

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
        $query = $container->get(QueryInterface::class);
        $logger = $container->get(LoggerInterface::class);

        assert($query instanceof QueryInterface);
        assert($logger instanceof LoggerInterface);

        return new MysqlMigrations($query, $logger);
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
    public static function Mysql(ContainerInterface $container): Mysql
    {
        $host = $container->get('MYSQL_HOST');
        $database = $container->get('MYSQL_DATABASE');
        $user = $container->get('MYSQL_USER');
        $passwordFile = $container->get('MYSQL_PASSWORD_FILE');

        if (!is_string($host) || !is_string($database) || !is_string($user) || !is_string($passwordFile)) {
            throw new UnexpectedValueException('mysql settings');
        }

        $settings = (new MysqlSettingsFactory())->createFrom([
            'host' => $host,
            'port' => '',
            'dbname' => $database,
            'socket' => '',
            'username' => $user,
            'password' => $passwordFile,
            'options' => [],
        ]);

        return (new MysqlFactory())->create($settings);
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
    public static function QueryInterface(ContainerInterface $container): QueryInterface
    {
        $mysql = $container->get(Mysql::class);

        assert($mysql instanceof Mysql);

        return new MysqlQuery($mysql);
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

        if (PHP_SAPI === 'cli-server') {
            $method = $_SERVER['REQUEST_METHOD'] ?? null;
            $uri = $_SERVER['REQUEST_URI'] ?? null;
            $host = $_SERVER['HTTP_HOST'] ?? null;

            if (!is_string($method) || !is_string($uri) || !is_string($host)) {
                throw new UnexpectedValueException('server settings');
            }

            return $factory->createServerRequest($method, 'http://' . $host . $uri, $_SERVER);
        }

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
