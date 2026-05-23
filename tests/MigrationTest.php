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

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\Test;
use TomasChochola\Oracle\Database\OracleConnection;
use UnexpectedValueException;

use function is_string;

/**
 * @internal
 *
 * @no-named-arguments
 */
#[CoversNothing()]
#[Medium()]
class MigrationTest extends TestCase
{
    #[Test()]
    public function migrates(): void
    {
        $this->migrate();

        $statement = $this->resolve(OracleConnection::class)->parse(
            <<<'SQL'
                SELECT COUNT(*) AS COUNT_NUMBER
                FROM user_tables
                WHERE table_name = 'MIGRATIONS'
                SQL,
        );

        $statement->execute();

        $row = $statement->fetchAssoc();

        if ($row === null) {
            throw new UnexpectedValueException('$row');
        }

        $count = $row['COUNT_NUMBER'] ?? null;

        if (!is_string($count)) {
            throw new UnexpectedValueException('$count');
        }

        self::assertSame('1', $count);
    }
}
