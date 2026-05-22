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
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;

use function uniqid;

/**
 * @internal
 *
 * @no-named-arguments
 */
#[CoversNothing()]
#[Small()]
class SmokeTest extends TestCase
{
    #[Test()]
    public function healtzLive(): void
    {
        $response = $this->handle($this->createServerRequest('GET', '/healthz/live'));

        self::assertSame(200, $response->getStatusCode());
    }

    #[DoesNotPerformAssertions()]
    #[Test()]
    public function migrates(): void
    {
        $this->migrate();
    }

    #[Test()]
    public function notFound(): void
    {
        $response = $this->handle($this->createServerRequest('GET', '/' . uniqid('notfound')));

        self::assertSame(404, $response->getStatusCode());
    }
}
