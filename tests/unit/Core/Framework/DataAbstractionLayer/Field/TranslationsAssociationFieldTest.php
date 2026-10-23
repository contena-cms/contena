<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\DataAbstractionLayer\Field;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Aggregate\BlogTranslation\BlogTranslationDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;

/**
 * @internal
 */
#[CoversClass(TranslationsAssociationField::class)]
class TranslationsAssociationFieldTest extends TestCase
{
    public function testConstructorConfiguresTheTranslationAssociation(): void
    {
        $field = new TranslationsAssociationField(BlogTranslationDefinition::class, 'blog_id');

        static::assertSame('translations', $field->getPropertyName());
        static::assertSame(BlogTranslationDefinition::class, $field->getReferenceClass());
        static::assertSame('blog_id', $field->getReferenceField());
        static::assertSame('id', $field->getLocalField());
        static::assertTrue($field->is(CascadeDelete::class));
        static::assertSame('language_id', $field->getLanguageField());
        static::assertSame(TranslationsAssociationField::PRIORITY, $field->getExtractPriority());
    }
}
