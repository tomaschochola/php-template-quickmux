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

namespace Tests\Bootstrap;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use Src\Bootstrap\Bootstrapper;
use Tests\TestCase;

use function uniqid;

/**
 * @internal
 */
#[CoversClass(Bootstrapper::class)]
#[Small]
final class BootstrapperTest extends TestCase
{
    #[Test]
    public function testHealtzLive(): void
    {
        $response = $this->handle($this->createServerRequest('GET', '/healthz/live'));

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function testNotFound(): void
    {
        $response = $this->handle($this->createServerRequest('GET', '/' . uniqid('notfound')));

        self::assertSame(404, $response->getStatusCode());
    }
}
