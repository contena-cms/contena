<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Blog\BlogFeatureSet;

use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Aggregate\BlogFeatureSet\BlogFeatureSetCollection;
use Contena\Core\Content\Blog\Aggregate\BlogFeatureSet\BlogFeatureSetDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogFeatureSet\BlogFeatureSetEntity;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

/**
 * @internal
 */
class BlogFeatureSetEntityTest extends TestCase
{
    use KernelTestBehaviour;

    private BlogFeatureSetDefinition $definition;

    protected function setUp(): void
    {
        parent::setUp();
        $this->definition = static::getContainer()->get(BlogFeatureSetDefinition::class);
    }

    public function testEntityDefinitionIsComplete(): void
    {
        static::assertSame(BlogFeatureSetDefinition::ENTITY_NAME, $this->definition->getEntityName());
        static::assertSame(BlogFeatureSetCollection::class, $this->definition->getCollectionClass());
        static::assertSame(BlogFeatureSetEntity::class, $this->definition->getEntityClass());
    }

    #[TestWith(['id'])]
    #[TestWith(['name'])]
    #[TestWith(['description'])]
    #[TestWith(['features'])]
    public function testDefinitionFieldsAreComplete(string $field): void
    {
        static::assertTrue($this->definition->getFields()->has($field));
    }

    #[TestWith(['getName'])]
    #[TestWith(['getDescription'])]
    #[TestWith(['getFeatures'])]
    #[TestWith(['getTranslations'])]
    public function testEntityIsComplete(string $method): void
    {
        static::assertTrue(method_exists(BlogFeatureSetEntity::class, $method));
    }

    public function testRepositoryIsWorking(): void
    {
        static::assertInstanceOf(EntityRepository::class, static::getContainer()->get('blog_feature_set.repository'));
    }

    public function testTranslationReferenceFieldIsCorrect(): void
    {
        $translationsField = static::getContainer()->get(BlogFeatureSetDefinition::class)->getField('translations');

        static::assertInstanceOf(TranslationsAssociationField::class, $translationsField);
        static::assertSame(
            \sprintf('%s_id', BlogFeatureSetDefinition::ENTITY_NAME),
            $translationsField->getReferenceField()
        );
    }
}
