<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Storefront\Theme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Log\Package;
use Contena\Storefront\Theme\ThemeCollection;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ThemeCollection::class)]
class ThemeCollectionTest extends TestCase
{
    public function testGetByTechnicalName(): void
    {
        $storefront = self::theme('Storefront');

        $collection = new ThemeCollection([$storefront, self::theme('SwagTheme')]);

        static::assertSame($storefront, $collection->getByTechnicalName('Storefront'));
        static::assertNull($collection->getByTechnicalName('Unknown'));
    }

}
