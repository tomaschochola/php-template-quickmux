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
use TomasChochola\Database\Mysql\Contract\QueryInterface;

/**
 * @internal
 *
 * @no-named-arguments
 */
#[CoversNothing()]
#[Medium()]
final class MigrationTest extends TestCase
{
    #[Test()]
    public function migrates(): void
    {
        $this->migrate();

        $count = $this->resolve(QueryInterface::class)->int(
            <<<'SQL'
                SELECT COUNT(*)
                FROM information_schema.tables
                WHERE table_schema = DATABASE() AND table_name = 'migrations'
                SQL,
        );

        self::assertSame(1, $count);
    }
}
