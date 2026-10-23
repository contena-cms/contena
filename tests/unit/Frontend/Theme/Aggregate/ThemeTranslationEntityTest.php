<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Theme\Aggregate;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Feature\FeatureException;
use Contena\Core\Test\Annotation\DisabledFeatures;
use Contena\Frontend\Theme\Aggregate\ThemeTranslationEntity;
use Contena\Frontend\Theme\ThemeEntity;

/**
 * @internal
 */
#[CoversClass(ThemeTranslationEntity::class)]
class ThemeTranslationEntityTest extends TestCase
{
    public function testAccessorsRoundTrip(): void
    {
        $translation = new ThemeTranslationEntity();
        $theme = new ThemeEntity();

        $translation->setDescription('A theme');
        $translation->setThemeId('theme-id');
        $translation->setTheme($theme);

        static::assertSame('A theme', $translation->getDescription());
        static::assertSame('theme-id', $translation->getThemeId());
        static::assertSame($theme, $translation->getTheme());
    }

    public function testGetLabelsThrowsWhenTheFeatureIsActive(): void
    {
        $this->expectExceptionObject(FeatureException::error(
            'Tried to access deprecated functionality: Method "Contena\Frontend\Theme\Aggregate\ThemeTranslationEntity::getLabels()" is deprecated and will be removed in v6.8.0.0. Use "ThemeConfigField::getLabelSnippetKey" instead.'
        ));

        (new ThemeTranslationEntity())->getLabels();
    }

    public function testGetHelpTextsThrowsWhenTheFeatureIsActive(): void
    {
        $this->expectExceptionObject(FeatureException::error(
            'Tried to access deprecated functionality: Method "Contena\Frontend\Theme\Aggregate\ThemeTranslationEntity::getHelpTexts()" is deprecated and will be removed in v6.8.0.0. Use "ThemeConfigField::getHelpTextSnippetKey" instead.'
        ));

        (new ThemeTranslationEntity())->getHelpTexts();
    }

    public function testSetLabelsThrowsWhenTheFeatureIsActive(): void
    {
        $this->expectExceptionObject(FeatureException::error(
            'Tried to access deprecated functionality: Method "Contena\Frontend\Theme\Aggregate\ThemeTranslationEntity::setLabels()" is deprecated and will be removed in v6.8.0.0.'
        ));

        (new ThemeTranslationEntity())->setLabels(['fields.sw-color-brand-primary' => 'Primary colour']);
    }

    public function testSetHelpTextsThrowsWhenTheFeatureIsActive(): void
    {
        $this->expectExceptionObject(FeatureException::error(
            'Tried to access deprecated functionality: Method "Contena\Frontend\Theme\Aggregate\ThemeTranslationEntity::setHelpTexts()" is deprecated and will be removed in v6.8.0.0.'
        ));

        (new ThemeTranslationEntity())->setHelpTexts(['fields.sw-color-brand-primary' => 'The main colour']);
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testLabelsAndHelpTextsRoundTripWhenTheFeatureIsInactive(): void
    {
        $translation = new ThemeTranslationEntity();

        $translation->setLabels(['fields.sw-color-brand-primary' => 'Primary colour']);
        $translation->setHelpTexts(['fields.sw-color-brand-primary' => 'The main colour']);

        static::assertSame(['fields.sw-color-brand-primary' => 'Primary colour'], $translation->getLabels());
        static::assertSame(['fields.sw-color-brand-primary' => 'The main colour'], $translation->getHelpTexts());
    }
}
