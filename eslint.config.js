/**
 * @file
 * @author Tomáš Chochola <tomaschochola@tomaschochola.cz>
 * @copyright © 2026 Tomáš Chochola <tomaschochola@tomaschochola.cz>
 *
 * @license CC-BY-ND-4.0
 *
 * @see {@link https://creativecommons.org/licenses/by-nd/4.0/} License
 * @see {@link https://github.com/tomaschochola} GitHub Profile
 * @see {@link https://github.com/sponsors/tomaschochola} GitHub Sponsors
 */

import { EslintConfig } from '@tomaschochola/ts-tooling-eslint-config';

// eslint-disable-next-line no-restricted-exports
export default EslintConfig.compose(
  EslintConfig.base(),
  EslintConfig.globalsRc(),
  EslintConfig.globalsNode(),
  EslintConfig.ignores(),
  EslintConfig.ignores(['node_modules', 'vendor', '.phpunit.cache', '.phpunit.coverage', '.phpunit.result.cache', '.php-cs-fixer.cache']),
  EslintConfig.recommended(),
  EslintConfig.stylistic(),
  EslintConfig.sonarjs(),
);
