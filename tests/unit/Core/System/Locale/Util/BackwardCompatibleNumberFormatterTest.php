<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Locale\Util;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Feature\FeatureException;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Locale\Util\BackwardCompatibleNumberFormatter;
use Contena\Core\Test\Annotation\DisabledFeatures;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(BackwardCompatibleNumberFormatter::class)]
class BackwardCompatibleNumberFormatterTest extends TestCase
{
    public function testItGetsNumberFormatterWithValidLocale(): void
    {
        $numberFormatter = new BackwardCompatibleNumberFormatter('en-GB', \NumberFormatter::DECIMAL);
        static::assertSame('en_GB', $numberFormatter->getLocale());
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testItFallsBackToDefaultLocaleIfGivenLocaleIsInvalid(): void
    {
        $numberFormatter = new BackwardCompatibleNumberFormatter('us', \NumberFormatter::DECIMAL);
        static::assertSame(\Locale::canonicalize(\Locale::getDefault()), $numberFormatter->getLocale());
    }

    public function testItThrowsExceptionIfGivenLocaleIsInvalid(): void
    {
        $this->expectExceptionObject(FeatureException::error('Tried to access deprecated functionality: The locale "us" is no valid PHP locale. Please use a valid locale.'));

        new BackwardCompatibleNumberFormatter('us', \NumberFormatter::DECIMAL);
    }
}
