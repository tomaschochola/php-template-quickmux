<?php

declare(strict_types=1);

use TomasChochola\Tooling\PhpCsFixer\ConfigFactory;
use TomasChochola\Tooling\PhpCsFixer\FinderFactory;
use TomasChochola\Tooling\PhpCsFixer\PHP85;

return ConfigFactory::create(
    FinderFactory::create()->in(__DIR__),
    \array_replace(PHP85::strictRules(), PHP85::projectRuleOverrides()),
);
