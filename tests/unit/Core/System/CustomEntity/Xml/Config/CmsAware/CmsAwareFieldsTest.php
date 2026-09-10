<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\CustomEntity\Xml\Config\CmsAware;

use Contena\Core\System\CustomEntity\Xml\Config\CmsAware\CmsAwareFields;
use Contena\Core\System\CustomEntity\Xml\Field\Field;
use Contena\Core\System\CustomEntity\Xml\Field\JsonField;
use Contena\Core\System\CustomEntity\Xml\Field\ManyToManyField;
use Contena\Core\System\CustomEntity\Xml\Field\ManyToOneField;
use Contena\Core\System\CustomEntity\Xml\Field\StringField;
use Contena\Core\System\CustomEntity\Xml\Field\TextField;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(CmsAwareFields::class)]
class CmsAwareFieldsTest extends TestCase
{
    private const TEST_LOCALE = 'en-GB';

    public function testGetCmsAwareFields(): void
    {
        $actualCmsAwareFields = array_reduce(CmsAwareFields::getCmsAwareFields(), static function ($accumulator, $field) {
            $accumulator[$field->getName()] = $field;

            return $accumulator;
        }, []);

        static::assertCount(11, $actualCmsAwareFields);

        foreach ($actualCmsAwareFields as $actualCmsAwareField) {
            static::assertInstanceOf(Field::class, $actualCmsAwareField);
            $currentField = $actualCmsAwareField->toArray(self::TEST_LOCALE);
            static::assertStringStartsWith('ct_', $currentField['name']);
            static::assertTrue($currentField['channelApiAware']);
        }

        static::assertInstanceOf(StringField::class, $actualCmsAwareFields['ct_title']);
        $swTitle = $actualCmsAwareFields['ct_title']->toArray(self::TEST_LOCALE);
        static::assertSame('string', $swTitle['type']);
        static::assertTrue($swTitle['translatable']);
        static::assertFalse($swTitle['required']);

        static::assertInstanceOf(TextField::class, $actualCmsAwareFields['ct_content']);
        $swDescription = $actualCmsAwareFields['ct_content']->toArray(self::TEST_LOCALE);
        static::assertSame('text', $swDescription['type']);
        static::assertTrue($swDescription['translatable']);
        static::assertFalse($swDescription['required']);
        static::assertFalse($swDescription['allowHtml']);

        static::assertInstanceOf(ManyToOneField::class, $actualCmsAwareFields['ct_cms_page']);
        $swCmsPage = $actualCmsAwareFields['ct_cms_page']->toArray(self::TEST_LOCALE);
        static::assertSame('many-to-one', $swCmsPage['type']);
        static::assertFalse($swCmsPage['required']);
        static::assertSame('cms_page', $swCmsPage['reference']);
        static::assertFalse($swCmsPage['inherited']);
        static::assertSame('set-null', $swCmsPage['onDelete']);

        static::assertInstanceOf(JsonField::class, $actualCmsAwareFields['ct_slot_config']);
        $swCategories = $actualCmsAwareFields['ct_slot_config']->toArray(self::TEST_LOCALE);
        static::assertSame('json', $swCategories['type']);
        static::assertFalse($swCategories['required']);

        static::assertInstanceOf(ManyToManyField::class, $actualCmsAwareFields['ct_categories']);
        $swCategories = $actualCmsAwareFields['ct_categories']->toArray(self::TEST_LOCALE);
        static::assertSame('many-to-many', $swCategories['type']);
        static::assertFalse($swCategories['required']);
        static::assertSame('category', $swCategories['reference']);
        static::assertFalse($swCategories['inherited']);
        static::assertSame('cascade', $swCategories['onDelete']);

        static::assertInstanceOf(ManyToOneField::class, $actualCmsAwareFields['ct_og_image']);
        $swMedia = $actualCmsAwareFields['ct_og_image']->toArray(self::TEST_LOCALE);
        static::assertSame('many-to-one', $swMedia['type']);
        static::assertFalse($swMedia['required']);
        static::assertSame('media', $swMedia['reference']);
        static::assertFalse($swMedia['inherited']);
        static::assertSame('set-null', $swMedia['onDelete']);

        static::assertInstanceOf(StringField::class, $actualCmsAwareFields['ct_seo_meta_title']);
        $swSeoMetaTitle = $actualCmsAwareFields['ct_seo_meta_title']->toArray(self::TEST_LOCALE);
        static::assertSame('string', $swSeoMetaTitle['type']);
        static::assertTrue($swSeoMetaTitle['translatable']);
        static::assertFalse($swSeoMetaTitle['required']);

        static::assertInstanceOf(StringField::class, $actualCmsAwareFields['ct_seo_meta_description']);
        $swSeoMetaDescription = $actualCmsAwareFields['ct_seo_meta_description']->toArray(self::TEST_LOCALE);
        static::assertSame('string', $swSeoMetaDescription['type']);
        static::assertTrue($swSeoMetaDescription['translatable']);
        static::assertFalse($swSeoMetaDescription['required']);
    }
}
