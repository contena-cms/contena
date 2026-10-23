<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Theme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Media\MediaCollection;
use Contena\Core\Content\Media\MediaEntity;
use Contena\Core\Framework\Feature\FeatureException;
use Contena\Core\System\Channel\ChannelCollection;
use Contena\Core\Test\Annotation\DisabledFeatures;
use Contena\Frontend\Theme\Aggregate\ThemeTranslationCollection;
use Contena\Frontend\Theme\ThemeCollection;
use Contena\Frontend\Theme\ThemeEntity;

/**
 * @internal
 */
#[CoversClass(ThemeEntity::class)]
class ThemeEntityTest extends TestCase
{
    public function testScalarAccessorsRoundTrip(): void
    {
        $theme = new ThemeEntity();

        $theme->setTechnicalName('SwagTheme');
        $theme->setName('Swag Theme');
        $theme->setAuthor('Contena');
        $theme->setDescription('A theme');
        $theme->setPreviewMediaId('preview-media-id');
        $theme->setParentThemeId('parent-theme-id');
        $theme->setThemeJson(['name' => 'Swag Theme']);
        $theme->setBaseConfig(['fields' => []]);
        $theme->setConfigValues(['sw-color-brand-primary' => ['value' => '#0042a0']]);
        $theme->setActive(true);

        static::assertSame('SwagTheme', $theme->getTechnicalName());
        static::assertSame('Swag Theme', $theme->getName());
        static::assertSame('Contena', $theme->getAuthor());
        static::assertSame('A theme', $theme->getDescription());
        static::assertSame('preview-media-id', $theme->getPreviewMediaId());
        static::assertSame('parent-theme-id', $theme->getParentThemeId());
        static::assertSame(['name' => 'Swag Theme'], $theme->getThemeJson());
        static::assertSame(['fields' => []], $theme->getBaseConfig());
        static::assertSame(['sw-color-brand-primary' => ['value' => '#0042a0']], $theme->getConfigValues());
        static::assertTrue($theme->isActive());
    }

    public function testAssociationAccessorsRoundTrip(): void
    {
        $theme = new ThemeEntity();

        static::assertNull($theme->getChannels());
        static::assertNull($theme->getMedia());
        static::assertNull($theme->getPreviewMedia());
        static::assertNull($theme->getTranslations());
        static::assertNull($theme->getDependentThemes());

        $channels = new ChannelCollection();
        $media = new MediaCollection();
        $previewMedia = new MediaEntity();
        $translations = new ThemeTranslationCollection();
        $dependentThemes = new ThemeCollection();

        $theme->setChannels($channels);
        $theme->setMedia($media);
        $theme->setPreviewMedia($previewMedia);
        $theme->setTranslations($translations);
        $theme->setDependentThemes($dependentThemes);

        static::assertSame($channels, $theme->getChannels());
        static::assertSame($media, $theme->getMedia());
        static::assertSame($previewMedia, $theme->getPreviewMedia());
        static::assertSame($translations, $theme->getTranslations());
        static::assertSame($dependentThemes, $theme->getDependentThemes());
    }

    public function testGetLabelsThrowsWhenTheFeatureIsActive(): void
    {
        $this->expectExceptionObject(FeatureException::error(
            'Tried to access deprecated functionality: Method "Contena\Frontend\Theme\ThemeEntity::getLabels()" is deprecated and will be removed in v6.8.0.0. Use "ThemeConfigField::getLabelSnippetKey" instead.'
        ));

        (new ThemeEntity())->getLabels();
    }

    public function testGetHelpTextsThrowsWhenTheFeatureIsActive(): void
    {
        $this->expectExceptionObject(FeatureException::error(
            'Tried to access deprecated functionality: Method "Contena\Frontend\Theme\ThemeEntity::getHelpTexts()" is deprecated and will be removed in v6.8.0.0. Use "ThemeConfigField::getHelpTextSnippetKey" instead.'
        ));

        (new ThemeEntity())->getHelpTexts();
    }

    public function testSetLabelsThrowsWhenTheFeatureIsActive(): void
    {
        $this->expectExceptionObject(FeatureException::error(
            'Tried to access deprecated functionality: Method "Contena\Frontend\Theme\ThemeEntity::setLabels()" is deprecated and will be removed in v6.8.0.0.'
        ));

        (new ThemeEntity())->setLabels(['fields.sw-color-brand-primary' => 'Primary colour']);
    }

    public function testSetHelpTextsThrowsWhenTheFeatureIsActive(): void
    {
        $this->expectExceptionObject(FeatureException::error(
            'Tried to access deprecated functionality: Method "Contena\Frontend\Theme\ThemeEntity::setHelpTexts()" is deprecated and will be removed in v6.8.0.0.'
        ));

        (new ThemeEntity())->setHelpTexts(['fields.sw-color-brand-primary' => 'The main colour']);
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testLabelsAndHelpTextsRoundTripWhenTheFeatureIsInactive(): void
    {
        $theme = new ThemeEntity();

        $theme->setLabels(['fields.sw-color-brand-primary' => 'Primary colour']);
        $theme->setHelpTexts(['fields.sw-color-brand-primary' => 'The main colour']);

        static::assertSame(['fields.sw-color-brand-primary' => 'Primary colour'], $theme->getLabels());
        static::assertSame(['fields.sw-color-brand-primary' => 'The main colour'], $theme->getHelpTexts());
    }
}
