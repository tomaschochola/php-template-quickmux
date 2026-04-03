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

use PHPUnit\Framework\TestCase as PHPUnitFrameworkTestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Src\Integration\ContainerManifest;
use Src\Integration\MigrationManifest;
use TomasChochola\Migrations\MigratorInterface;
use TomasChochola\Oracle\Database\OracleDatabase;
use TomasChochola\Oracle\Database\OracleSettings;
use TomasChochola\Psr\Container\Container;
use UnexpectedValueException;

use function assert;
use function file_get_contents;
use function is_string;
use function iterator_to_array;
use function mb_trim;

use const OCI_DEFAULT;

/**
 * @internal
 * @no-named-arguments
 */
abstract class TestCase extends PHPUnitFrameworkTestCase
{
    private Container|null $container = null;

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
        return $this->resolve(ServerRequestFactoryInterface::class)->createServerRequest($method, $uri, $params);
    }

    protected function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->resolve(RequestHandlerInterface::class)->handle($request);
    }

    protected function migrate(): void
    {
        $this->refresh();
        $this->resolve(MigratorInterface::class)->migrate(new MigrationManifest());
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

    private function refresh(): void
    {
        $container = $this->container();
        $host = $container->get('ORACLE_HOST');
        $database = $container->get('ORACLE_DATABASE');
        $passwordFile = $container->get('ORACLE_PASSWORD');

        if (!is_string($host) || !is_string($database) || !is_string($passwordFile)) {
            throw new UnexpectedValueException('container');
        }

        $password = file_get_contents($passwordFile);

        if (!is_string($password)) {
            throw new UnexpectedValueException('file_get_contents');
        }

        $password = mb_trim($password);
        $oracleDatabase = new OracleDatabase(new OracleSettings('SYSTEM', $password, '//' . $host . '/' . $database, 'AL32UTF8', OCI_DEFAULT));
        $oracle = $oracleDatabase->connect();

        $oracle->free();

        $sql = <<<'SQL'
            BEGIN
                EXECUTE IMMEDIATE 'DROP USER APP_UNIT CASCADE';
            EXCEPTION
                WHEN OTHERS THEN NULL;
            END;
            SQL;

        $statement = $oracle->parse($sql);

        $statement->free();
        $statement->execute();

        $sql = <<<'SQL'
            BEGIN
                EXECUTE IMMEDIATE 'DROP ROLE APP_UNIT';
            EXCEPTION
                WHEN OTHERS THEN NULL;
            END;
            SQL;

        $statement = $oracle->parse($sql);

        $statement->free();
        $statement->execute();

        $sql = <<<'SQL'
            BEGIN
                EXECUTE IMMEDIATE 'CREATE USER APP_UNIT IDENTIFIED BY "' || :password || '"';
                EXECUTE IMMEDIATE 'GRANT CREATE SESSION, CREATE TABLE, CREATE SEQUENCE, CREATE VIEW, CREATE PROCEDURE, CREATE TRIGGER TO APP_UNIT';
                EXECUTE IMMEDIATE 'ALTER USER APP_UNIT QUOTA UNLIMITED ON USERS';
            END;
            SQL;

        $statement = $oracle->parse($sql);

        $statement->free();
        $statement->bindByName('password', $password);
        $statement->execute();
    }
}
