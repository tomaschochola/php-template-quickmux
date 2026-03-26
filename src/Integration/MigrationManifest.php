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

use EmptyIterator;
use IteratorAggregate;
use Override;
use TomasChochola\Migrations\MigrationInterface;
use Traversable;

/**
 * @no-named-arguments
 *
 * @implements IteratorAggregate<mixed, MigrationInterface>
 */
final readonly class MigrationManifest implements IteratorAggregate
{
    #[Override]
    public function getIterator(): Traversable
    {
        return new EmptyIterator();
    }
}
