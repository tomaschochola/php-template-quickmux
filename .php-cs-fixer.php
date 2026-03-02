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

use TomasChochola\Tooling\PhpCsFixerConfig\ConfigFactory;
use TomasChochola\Tooling\PhpCsFixerConfig\FinderFactory;
use TomasChochola\Tooling\PhpCsFixerConfig\PHP85;

return ConfigFactory::make(FinderFactory::make()->in(__DIR__), \array_replace(
    PHP85::base(),
    PHP85::project(),
    PHP85::tomaschochola(),
));
