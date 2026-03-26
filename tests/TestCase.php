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
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Random\Randomizer;
use Src\Integration\ContainerManifest;
use Src\Integration\MigrationManifest;
use TomasChochola\Migrations\MigratorInterface;
use TomasChochola\Pdo\QueryInterface;
use TomasChochola\Psr\Container\Container;
use function iterator_to_array;

/**
 * @internal
 * @no-named-arguments
 */
abstract class TestCase extends PHPUnitFrameworkTestCase
{
    private Container|null $container = null;

    private bool $migrated = false;

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
    protected function createServerRequest(string $method, UriInterface|string $uri, array $params = []): ServerRequestInterface
    {
        return $this->container()->resolve(ServerRequestFactoryInterface::class)->createServerRequest($method, $uri, $params);
    }

    protected function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->container()->resolve(RequestHandlerInterface::class)->handle($request);
    }

    protected function migrate(): void
    {
        $this->refresh();
        $this->container()->resolve(MigratorInterface::class)->migrate(new MigrationManifest());

        $this->migrated = true;
    }

    #[Override]
    protected function tearDown(): void
    {
        parent::tearDown();

        if ($this->migrated) {
            $this->refresh();
        }

        $this->migrated = false;
        $this->container = null;
    }

    private function refresh(): void
    {
        $pdo = $this->container()->resolve(QueryInterface::class);
        $database = $pdo->string('SELECT DATABASE()');

        $pdo->run("DROP DATABASE IF EXISTS `{$database}`");
        $pdo->run("CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET `utf8mb4` COLLATE `utf8mb4_0900_ai_ci`");

        $this->container = null;
    }
}
