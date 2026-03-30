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

\set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

require_once __DIR__ . '/vendor/autoload.php';

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Src\Integration\CONTAINER_CACHE;
use Src\Integration\ContainerManifest;
use TomasChochola\Psr\Container\Container;
use TomasChochola\Psr\Http\RequestHandlers\ResponseEmitterInterface;
use TomasChochola\Psr\SimpleCache\ApcuSimpleCache;
use TomasChochola\Psr\SimpleCache\SimpleCaches;

$container = new Container(CONTAINER_CACHE::current() ? SimpleCaches::remember(new ApcuSimpleCache(), __FILE__, static fn(): array => \iterator_to_array(new ContainerManifest())) : \iterator_to_array(new ContainerManifest()));

$emitter = $container->get(ResponseEmitterInterface::class);
$handler = $container->get(RequestHandlerInterface::class);
$request = $container->get(ServerRequestInterface::class);

\assert($emitter instanceof ResponseEmitterInterface);
\assert($handler instanceof RequestHandlerInterface);
\assert($request instanceof ServerRequestInterface);

$emitter->emit($handler->handle($request));
