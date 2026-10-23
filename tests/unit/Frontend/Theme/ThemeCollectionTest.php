<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Theme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Log\Package;
use Contena\Frontend\Theme\ThemeCollection;
use Contena\Frontend\Theme\ThemeEntity;

/**
 * @internal
 */
#[CoversClass(ThemeCollection::class)]
class ThemeCollectionTest extends TestCase
{
    public function testGetByTechnicalName(): void
    {
        $frontend = self::theme('Frontend');

        $collection = new ThemeCollection([$frontend, self::theme('SwagTheme')]);

        static::assertSame($frontend, $collection->getByTechnicalName('Frontend'));
        static::assertNull($collection->getByTechnicalName('Unknown'));
    }

    private static function theme(string $technicalName): ThemeEntity
    {
        $theme = new ThemeEntity();
        $theme->setUniqueIdentifier($technicalName);
        $theme->setTechnicalName($technicalName);

        return $theme;
    }
}
