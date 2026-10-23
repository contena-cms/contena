<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Blog\BlogFeatureSet;

use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Aggregate\BlogFeatureSetTranslation\BlogFeatureSetTranslationCollection;
use Contena\Core\Content\Blog\Aggregate\BlogFeatureSetTranslation\BlogFeatureSetTranslationDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogFeatureSetTranslation\BlogFeatureSetTranslationEntity;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;

/**
 * @internal
 */
class BlogFeatureSetTranslationEntityTest extends TestCase
{
    use KernelTestBehaviour;

    private BlogFeatureSetTranslationDefinition $definition;

    protected function setUp(): void
    {
        parent::setUp();
        $this->definition = static::getContainer()->get(BlogFeatureSetTranslationDefinition::class);
    }

    public function testEntityDefinitionIsComplete(): void
    {
        static::assertSame(BlogFeatureSetTranslationDefinition::ENTITY_NAME, $this->definition->getEntityName());
        static::assertSame(BlogFeatureSetTranslationCollection::class, $this->definition->getCollectionClass());
        static::assertSame(BlogFeatureSetTranslationEntity::class, $this->definition->getEntityClass());
    }

    #[TestWith(['name'])]
    #[TestWith(['description'])]
    public function testDefinitionFieldsAreComplete(string $field): void
    {
        static::assertTrue($this->definition->getFields()->has($field));
    }

    #[TestWith(['getBlogFeatureSetId'])]
    #[TestWith(['getName'])]
    #[TestWith(['getDescription'])]
    #[TestWith(['getBlogFeatureSet'])]
    public function testEntityIsComplete(string $method): void
    {
        static::assertTrue(method_exists(BlogFeatureSetTranslationEntity::class, $method));
    }

    public function testRepositoryIsWorking(): void
    {
        static::assertInstanceOf(EntityRepository::class, static::getContainer()->get('blog_feature_set_translation.repository'));
    }
}
