<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\DataAbstractionLayer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Customer\CustomerDefinition;
use Contena\Core\Checkout\Promotion\Aggregate\PromotionTranslation\PromotionTranslationDefinition;
use Contena\Core\Checkout\Promotion\PromotionDefinition;
use Contena\Core\Content\Cms\Aggregate\CmsPageTranslation\CmsPageTranslationDefinition;
use Contena\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotDefinition;
use Contena\Core\Content\Cms\Aggregate\CmsSlotTranslation\CmsSlotTranslationDefinition;
use Contena\Core\Content\Cms\CmsPageDefinition;
use Contena\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Contena\Core\Content\Product\Aggregate\ProductTranslation\ProductTranslationDefinition;
use Contena\Core\Content\Product\ProductDefinition;
use Contena\Core\Content\Property\Aggregate\PropertyGroupTranslation\PropertyGroupTranslationDefinition;
use Contena\Core\Content\Property\PropertyGroupDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\Field;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Contena\Core\Framework\DataAbstractionLayer\FieldVisibility;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Contena\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateDefinition;
use Contena\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateTranslationDefinition;
use Contena\Core\System\StateMachine\StateMachineDefinition;
use Contena\Core\System\StateMachine\StateMachineTranslationDefinition;

/**
 * @internal
 */
#[Package('framework')]
class EntityDefinitionTest extends TestCase
{
    use KernelTestBehaviour;

    public function testEntityDefinitionCompilation(): void
    {
        $definition = static::getContainer()->get(ProductDefinition::class);

        static::assertContainsOnlyInstancesOf(Field::class, $definition->getFields());
        $productManufacturerVersionIdField = $definition->getFields()->get('productManufacturerVersionId');
        static::assertInstanceOf(ReferenceVersionField::class, $productManufacturerVersionIdField);
        static::assertSame('product_manufacturer_version_id', $productManufacturerVersionIdField->getStorageName());
        static::assertInstanceOf(ProductManufacturerDefinition::class, $productManufacturerVersionIdField->getVersionReferenceDefinition());
        static::assertSame(static::getContainer()->get(ProductManufacturerDefinition::class), $productManufacturerVersionIdField->getVersionReferenceDefinition());
    }

    public function testTranslationCompilation(): void
    {
        $definition = static::getContainer()->get(ProductTranslationDefinition::class);

        static::assertContainsOnlyInstancesOf(Field::class, $definition->getFields());
        $languageIdField = $definition->getFields()->get('languageId');
        static::assertInstanceOf(FkField::class, $languageIdField);
        static::assertSame('language_id', $languageIdField->getStorageName());
    }

    #[DataProvider('provideTranslatedDefinitions')]
    public function testTranslationsOnDefinitions(string $baseDefinitionClass, string $translationDefinitionClass): void
    {
        $baseDefinition = static::getContainer()->get($baseDefinitionClass);
        $translationDefinition = static::getContainer()->get($translationDefinitionClass);

        static::assertInstanceOf(EntityDefinition::class, $baseDefinition);
        static::assertInstanceOf(EntityTranslationDefinition::class, $translationDefinition);
        static::assertSame($translationDefinition, $baseDefinition->getTranslationDefinition());
        static::assertInstanceOf(JsonField::class, $baseDefinition->getFields()->get('translated'));
        static::assertSame($baseDefinition->getClass(), $translationDefinition->getParentDefinition()->getClass());
        static::assertSame($baseDefinition, $translationDefinition->getParentDefinition());
    }

    /**
     * @return array<int, array<int, string>>
     */
    public static function provideTranslatedDefinitions(): array
    {
        return [
            [CmsPageDefinition::class, CmsPageTranslationDefinition::class],
            [CmsSlotDefinition::class, CmsSlotTranslationDefinition::class],
            [PropertyGroupDefinition::class, PropertyGroupTranslationDefinition::class],
            [StateMachineDefinition::class, StateMachineTranslationDefinition::class],
            [StateMachineStateDefinition::class, StateMachineStateTranslationDefinition::class],
            [ProductDefinition::class, ProductTranslationDefinition::class],
            [PromotionDefinition::class, PromotionTranslationDefinition::class],
        ];
    }

    public function testGetFieldVisibility(): void
    {
        $definition = static::getContainer()->get(CustomerDefinition::class);

        $internalFields = [
            'password',
            'newsletterSalesChannelIds',
            'legacyPassword',
            'legacyEncoder',
        ];

        foreach ($internalFields as $field) {
            static::assertTrue($definition->getFieldVisibility()->isVisible($field));
            FieldVisibility::$isInTwigRenderingContext = true;
            static::assertFalse($definition->getFieldVisibility()->isVisible($field));
            FieldVisibility::$isInTwigRenderingContext = false;
        }
    }
}
