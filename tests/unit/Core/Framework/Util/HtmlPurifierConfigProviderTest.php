<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Util;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Util\HtmlPurifierConfigProvider;

/**
 * @internal
 */
#[CoversClass(HtmlPurifierConfigProvider::class)]
class HtmlPurifierConfigProviderTest extends TestCase
{
    #[TestDox('Returns a fresh instance on each call so callers cannot share mutated state')]
    public function testReturnsFreshInstanceOnEachCall(): void
    {
        $provider = new HtmlPurifierConfigProvider();

        static::assertNotSame($provider->getConfig(), $provider->getConfig());
    }
}
