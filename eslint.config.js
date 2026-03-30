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

import { Eslint } from '@tomaschochola/tooling-eslint';
import globals from 'globals';

// eslint-disable-next-line no-restricted-exports
export default new Eslint()
  .globals({
    ...globals.node,
    ...globals.es2024,
  })
  .ignores()
  .ignores(['node_modules', 'vendor', '.phpunit.cache', '.phpunit.coverage', '.phpunit.result.cache', '.php-cs-fixer.cache'])
  .recommended()
  .stylistic()
  .sonarjs()
  .build();
