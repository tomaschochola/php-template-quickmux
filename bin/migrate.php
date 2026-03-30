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

require_once __DIR__ . '/../vendor/autoload.php';

use Src\Integration\ContainerManifest;
use Src\Integration\MigrationManifest;
use TomasChochola\Migrations\MigratorInterface;
use TomasChochola\Psr\Container\Container;

$container = new Container(\iterator_to_array(new ContainerManifest()));

$migrator = $container->get(MigratorInterface::class);

\assert($migrator instanceof MigratorInterface);

$migrator->migrate(new MigrationManifest());
