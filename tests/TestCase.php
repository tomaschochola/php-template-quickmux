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

namespace Tests;

use Override;
use PHPUnit\Framework\TestCase as PHPUnitFrameworkTestCase;
use Pdo\Mysql;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Random\Randomizer;
use TomasChochola\Migrations\MigratorInterface;
use TomasChochola\Psr\Container\Container;
use TomasChochola\Template\Quickmux\Integration\ContainerManifest;
use TomasChochola\Template\Quickmux\Integration\MigrationManifest;

use function assert;
use function implode;
use function iterator_to_array;
use function range;

/**
 * @internal
 *
 * @no-named-arguments
 */
abstract class TestCase extends PHPUnitFrameworkTestCase
{
    private Container | null $container = null;

    private string $database = '';

    private bool $migrated = false;

    #[Override()]
    protected function setUp(): void
    {
        parent::setUp();

        $this->database = (new Randomizer())->getBytesFromString(implode('', range('a', 'z') + range('A', 'Z') + range('0', '9')), 32);
    }

    #[Override()]
    protected function tearDown(): void
    {
        try {
            if ($this->migrated) {
                $this->mysql()->exec("DROP DATABASE IF EXISTS `{$this->database}`");
            }
        } finally {
            parent::tearDown();
        }
    }

    protected function container(): Container
    {
        if ($this->container === null) {
            $this->container = new Container(iterator_to_array(new ContainerManifest()));
        }

        return $this->container;
    }

    /**
     * @param array<mixed, mixed> $params
     */
    protected function createServerRequest(string $method, UriInterface | string $uri, array $params = []): ServerRequestInterface
    {
        return $this->resolve(ServerRequestFactoryInterface::class)->createServerRequest($method, $uri, $params);
    }

    protected function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->resolve(RequestHandlerInterface::class)->handle($request);
    }

    protected function migrate(): void
    {
        $mysql = $this->mysql();

        $mysql->exec("CREATE DATABASE `{$this->database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci");
        $this->migrated = true;
        $mysql->exec("USE `{$this->database}`");

        $this->resolve(MigratorInterface::class)->migrate(new MigrationManifest());
    }

    protected function mysql(): Mysql
    {
        return $this->resolve(Mysql::class);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $id
     *
     * @return T
     */
    protected function resolve(string $id): object
    {
        $resolved = $this->container()->get($id);

        assert($resolved instanceof $id);

        return $resolved;
    }
}
