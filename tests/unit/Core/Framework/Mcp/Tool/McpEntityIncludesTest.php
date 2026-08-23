<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Mcp\Tool;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\Field;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToManyIdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Mcp\Tool\McpEntityIncludes;

/**
 * @internal
 */
#[CoversClass(McpEntityIncludes::class)]
class McpEntityIncludesTest extends TestCase
{
    use McpEntityIncludes;

    public function testScalarFieldsAreIncluded(): void
    {
        [$blog] = $this->compileDefinitions([
            'blog' => [
                new IdField('id', 'id')->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
                new StringField('name', 'name')->addFlags(new ApiAware()),
                new StringField('slug', 'slug')->addFlags(new ApiAware()),
            ],
        ]);

        $includes = $this->buildDefaultIncludes($blog, new Criteria());

        static::assertArrayHasKey('blog', $includes);
        static::assertContains('id', $includes['blog']);
        static::assertContains('name', $includes['blog']);
        static::assertContains('slug', $includes['blog']);
    }

    public function testUnrequestedAssociationsAreExcluded(): void
    {
        [$blog] = $this->compileDefinitions([
            'blog' => [
                new IdField('id', 'id')->addFlags(new ApiAware(), new PrimaryKey()),
                new StringField('name', 'name')->addFlags(new ApiAware()),
                new OneToManyAssociationField('categories', 'category', 'blog_id'),
            ],
            'category' => [
                new IdField('id', 'id')->addFlags(new ApiAware(), new PrimaryKey()),
                new StringField('name', 'name')->addFlags(new ApiAware()),
            ],
        ]);

        $includes = $this->buildDefaultIncludes($blog, new Criteria());

        static::assertNotContains('categories', $includes['blog']);
        static::assertArrayNotHasKey('category', $includes);
    }

    public function testRequestedAssociationsAreIncluded(): void
    {
        [$blog] = $this->compileDefinitions([
            'blog' => [
                new IdField('id', 'id')->addFlags(new ApiAware(), new PrimaryKey()),
                new StringField('name', 'name')->addFlags(new ApiAware()),
                new FkField('author_id', 'authorId', 'author')->addFlags(new ApiAware()),
                new ManyToOneAssociationField('author', 'author_id', 'author'),
            ],
            'author' => [
                new IdField('id', 'id')->addFlags(new ApiAware(), new PrimaryKey()),
                new StringField('name', 'name')->addFlags(new ApiAware()),
            ],
        ]);

        $criteria = new Criteria();
        $criteria->addAssociation('author');

        $includes = $this->buildDefaultIncludes($blog, $criteria);

        static::assertContains('author', $includes['blog']);
        static::assertContains('authorId', $includes['blog']);
        static::assertArrayHasKey('author', $includes);
        static::assertContains('id', $includes['author']);
        static::assertContains('name', $includes['author']);
    }

    public function testNestedAssociationsAreHandledRecursively(): void
    {
        [$blog] = $this->compileDefinitions([
            'blog' => [
                new IdField('id', 'id')->addFlags(new ApiAware(), new PrimaryKey()),
                new StringField('name', 'name')->addFlags(new ApiAware()),
                new OneToManyAssociationField('properties', 'tag_option', 'blog_id'),
            ],
            'tag_option' => [
                new IdField('id', 'id')->addFlags(new ApiAware(), new PrimaryKey()),
                new StringField('name', 'name')->addFlags(new ApiAware()),
                new FkField('tag_group_id', 'groupId', 'tag_group')->addFlags(new ApiAware()),
                new ManyToOneAssociationField('group', 'tag_group_id', 'tag_group'),
            ],
            'tag_group' => [
                new IdField('id', 'id')->addFlags(new ApiAware(), new PrimaryKey()),
                new StringField('name', 'name')->addFlags(new ApiAware()),
            ],
        ]);

        $criteria = new Criteria();
        $criteria->getAssociation('properties')->addAssociation('group');

        $includes = $this->buildDefaultIncludes($blog, $criteria);

        static::assertContains('properties', $includes['blog']);
        static::assertArrayHasKey('tag_option', $includes);
        static::assertContains('group', $includes['tag_option']);
        static::assertArrayHasKey('tag_group', $includes);
        static::assertContains('id', $includes['tag_group']);
        static::assertContains('name', $includes['tag_group']);
    }

    public function testDeepUnrequestedAssociationsAreExcluded(): void
    {
        [$blog] = $this->compileDefinitions([
            'blog' => [
                new IdField('id', 'id')->addFlags(new ApiAware(), new PrimaryKey()),
                new StringField('name', 'name')->addFlags(new ApiAware()),
                new FkField('cover_id', 'coverId', 'blog_asset')->addFlags(new ApiAware()),
                new ManyToOneAssociationField('cover', 'cover_id', 'blog_asset'),
            ],
            'blog_asset' => [
                new IdField('id', 'id')->addFlags(new ApiAware(), new PrimaryKey()),
                new FkField('media_id', 'mediaId', 'media')->addFlags(new ApiAware()),
                new ManyToOneAssociationField('media', 'media_id', 'media'),
            ],
            'media' => [
                new IdField('id', 'id')->addFlags(new ApiAware(), new PrimaryKey()),
                new StringField('url', 'url')->addFlags(new ApiAware()),
                new OneToManyAssociationField('thumbnails', 'media_thumbnail', 'media_id'),
            ],
            'media_thumbnail' => [
                new IdField('id', 'id')->addFlags(new ApiAware(), new PrimaryKey()),
                new StringField('url', 'url')->addFlags(new ApiAware()),
            ],
        ]);

        $criteria = new Criteria();
        $criteria->getAssociation('cover')->addAssociation('media');

        $includes = $this->buildDefaultIncludes($blog, $criteria);

        static::assertContains('cover', $includes['blog']);
        static::assertContains('media', $includes['blog_asset']);
        static::assertArrayHasKey('media', $includes);
        static::assertNotContains('thumbnails', $includes['media']);
        static::assertArrayNotHasKey('media_thumbnail', $includes);
    }

    public function testTranslatedFieldIsIncludedInDefaults(): void
    {
        [$blog] = $this->compileDefinitions([
            'blog' => [
                new IdField('id', 'id')->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
                new TranslatedField('name'),
                new StringField('slug', 'slug')->addFlags(new ApiAware()),
            ],
        ]);

        $criteria = new Criteria();
        $this->applyDefaultIncludes($blog, $criteria);

        $includes = $criteria->getIncludes();
        static::assertNotNull($includes);
        static::assertContains('translated', $includes['blog']);
        static::assertContains('id', $includes['blog']);
        static::assertContains('slug', $includes['blog']);
    }

    public function testTranslatedNotIncludedWhenNoTranslatedFields(): void
    {
        [$blog] = $this->compileDefinitions([
            'blog' => [
                new IdField('id', 'id')->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
                new StringField('slug', 'slug')->addFlags(new ApiAware()),
            ],
        ]);

        $criteria = new Criteria();
        $this->applyDefaultIncludes($blog, $criteria);

        $includes = $criteria->getIncludes();
        static::assertNotNull($includes);
        static::assertNotContains('translated', $includes['blog']);
    }

    public function testApplyDefaultInjectsTranslatedIntoUserProvidedIncludes(): void
    {
        [$blog] = $this->compileDefinitions([
            'blog' => [
                new IdField('id', 'id')->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
                new TranslatedField('name'),
                new StringField('slug', 'slug')->addFlags(new ApiAware()),
            ],
        ]);

        $criteria = new Criteria();
        $criteria->setIncludes(['blog' => ['id', 'name']]);

        $this->applyDefaultIncludes($blog, $criteria);

        $includes = $criteria->getIncludes();
        static::assertNotNull($includes);
        static::assertContains('translated', $includes['blog']);
        static::assertContains('id', $includes['blog']);
        static::assertContains('name', $includes['blog']);
    }

    public function testApplyDefaultSkipsTranslatedWhenAlreadyPresent(): void
    {
        [$blog] = $this->compileDefinitions([
            'blog' => [
                new IdField('id', 'id')->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
                new TranslatedField('name'),
            ],
        ]);

        $criteria = new Criteria();
        $criteria->setIncludes(['blog' => ['id', 'name', 'translated']]);

        $this->applyDefaultIncludes($blog, $criteria);

        $includes = $criteria->getIncludes();
        static::assertNotNull($includes);
        static::assertCount(1, array_keys($includes['blog'], 'translated', true));
    }

    public function testApplyDefaultInjectsTranslatedRecursivelyIntoAssociations(): void
    {
        [$blog] = $this->compileDefinitions([
            'blog' => [
                new IdField('id', 'id')->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
                new TranslatedField('name'),
                new FkField('author_id', 'authorId', 'author')->addFlags(new ApiAware()),
                new ManyToOneAssociationField('author', 'author_id', 'author'),
            ],
            'author' => [
                new IdField('id', 'id')->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
                new TranslatedField('name'),
            ],
        ]);

        $criteria = new Criteria();
        $criteria->addAssociation('author');
        $criteria->setIncludes([
            'blog' => ['id', 'name', 'author'],
            'author' => ['id', 'name'],
        ]);

        $this->applyDefaultIncludes($blog, $criteria);

        $includes = $criteria->getIncludes();
        static::assertNotNull($includes);
        static::assertContains('translated', $includes['blog']);
        static::assertContains('translated', $includes['author']);
    }

    public function testManyToManyAssociationResolvesReferenceDefinition(): void
    {
        [$blog] = $this->compileDefinitions([
            'blog' => [
                new IdField('id', 'id')->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
                new StringField('name', 'name')->addFlags(new ApiAware()),
                new ManyToManyIdField('category_ids', 'categoryIds', 'categories')->addFlags(new ApiAware()),
                new ManyToManyAssociationField('categories', 'category', 'blog_category_link', 'blog_id', 'category_id'),
            ],
            'category' => [
                new IdField('id', 'id')->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
                new StringField('name', 'name')->addFlags(new ApiAware()),
            ],
            'blog_category_link' => [
                new FkField('blog_id', 'blogId', 'blog')->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
                new FkField('category_id', 'categoryId', 'category')->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
            ],
        ]);

        $criteria = new Criteria();
        $criteria->addAssociation('categories');

        $includes = $this->buildDefaultIncludes($blog, $criteria);

        static::assertContains('categories', $includes['blog']);
        static::assertArrayHasKey('category', $includes);
        static::assertContains('id', $includes['category']);
        static::assertContains('name', $includes['category']);
    }

    public function testRecursionGuardPreventsInfiniteLoop(): void
    {
        [$blog] = $this->compileDefinitions([
            'blog' => [
                new IdField('id', 'id')->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
                new StringField('name', 'name')->addFlags(new ApiAware()),
                new FkField('author_id', 'authorId', 'author')->addFlags(new ApiAware()),
                new ManyToOneAssociationField('author', 'author_id', 'author'),
            ],
            'author' => [
                new IdField('id', 'id')->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
                new StringField('name', 'name')->addFlags(new ApiAware()),
                new OneToManyAssociationField('blogs', 'blog', 'author_id'),
            ],
        ]);

        $criteria = new Criteria();
        $criteria->getAssociation('author')->addAssociation('blogs');

        $includes = $this->buildDefaultIncludes($blog, $criteria);

        static::assertContains('author', $includes['blog']);
        static::assertArrayHasKey('author', $includes);
        static::assertContains('blogs', $includes['author']);
        static::assertArrayHasKey('blog', $includes);
    }

    public function testEnsureTranslatedRecursivelyTraversesManyToManyAssociations(): void
    {
        [$blog] = $this->compileDefinitions([
            'blog' => [
                new IdField('id', 'id')->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
                new TranslatedField('name'),
                new ManyToManyIdField('tag_ids', 'tagIds', 'tags')->addFlags(new ApiAware()),
                new ManyToManyAssociationField('tags', 'tag', 'blog_tag_link', 'blog_id', 'tag_id'),
            ],
            'tag' => [
                new IdField('id', 'id')->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
                new TranslatedField('name'),
            ],
            'blog_tag_link' => [
                new FkField('blog_id', 'blogId', 'blog')->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
                new FkField('tag_id', 'tagId', 'tag')->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
            ],
        ]);

        $criteria = new Criteria();
        $criteria->addAssociation('tags');
        $criteria->setIncludes([
            'blog' => ['id', 'name', 'tags'],
            'tag' => ['id', 'name'],
        ]);

        $this->applyDefaultIncludes($blog, $criteria);

        $includes = $criteria->getIncludes();
        static::assertNotNull($includes);
        static::assertContains('translated', $includes['blog']);
        static::assertContains('translated', $includes['tag']);
    }

    public function testEnsureTranslatedSkipsEntityNotInIncludesMap(): void
    {
        [$blog] = $this->compileDefinitions([
            'blog' => [
                new IdField('id', 'id')->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
                new TranslatedField('name'),
            ],
        ]);

        $criteria = new Criteria();
        $criteria->setIncludes(['other_entity' => ['id']]);

        $this->applyDefaultIncludes($blog, $criteria);

        $includes = $criteria->getIncludes();
        static::assertNotNull($includes);
        static::assertArrayNotHasKey('blog', $includes);
        static::assertArrayHasKey('other_entity', $includes);
    }

    public function testCollectIncludesStopsOnAlreadyVisitedEntity(): void
    {
        [$blog] = $this->compileDefinitions([
            'blog' => [
                new IdField('id', 'id')->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
                new ManyToOneAssociationField('author', 'author_id', 'author', 'id')->addFlags(new ApiAware()),
                new ManyToOneAssociationField('cover', 'cover_id', 'media', 'id')->addFlags(new ApiAware()),
            ],
            'author' => [
                new IdField('id', 'id')->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
                new ManyToOneAssociationField('logo', 'logo_id', 'media', 'id')->addFlags(new ApiAware()),
            ],
            'media' => [
                new IdField('id', 'id')->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
                new StringField('file_name', 'fileName')->addFlags(new ApiAware()),
            ],
        ]);

        $criteria = new Criteria();
        $criteria->addAssociation('author');
        $criteria->getAssociation('author')->addAssociation('logo');
        $criteria->addAssociation('cover');

        $this->applyDefaultIncludes($blog, $criteria);

        $includes = $criteria->getIncludes();
        static::assertNotNull($includes);
        static::assertArrayHasKey('blog', $includes);
        static::assertArrayHasKey('author', $includes);
        static::assertArrayHasKey('media', $includes);
    }

    public function testAddTranslatedSkipsNonAssociationCriteriaKey(): void
    {
        [$blog] = $this->compileDefinitions([
            'blog' => [
                new IdField('id', 'id')->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
                new TranslatedField('name'),
                new StringField('slug', 'slug')->addFlags(new ApiAware()),
            ],
        ]);

        $criteria = new Criteria();
        $criteria->addAssociation('nonExistentField');
        $criteria->setIncludes(['blog' => ['id', 'name']]);

        $this->applyDefaultIncludes($blog, $criteria);

        $includes = $criteria->getIncludes();
        static::assertNotNull($includes);
        static::assertContains('translated', $includes['blog']);
    }

    public function testEnsureTranslatedSkipsEntityWithoutTranslatedFields(): void
    {
        [$blog] = $this->compileDefinitions([
            'blog' => [
                new IdField('id', 'id')->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
                new StringField('slug', 'slug')->addFlags(new ApiAware()),
            ],
        ]);

        $criteria = new Criteria();
        $criteria->setIncludes(['blog' => ['id', 'slug']]);

        $this->applyDefaultIncludes($blog, $criteria);

        $includes = $criteria->getIncludes();
        static::assertNotNull($includes);
        static::assertNotContains('translated', $includes['blog']);
        static::assertSame(['id', 'slug'], $includes['blog']);
    }

    /**
     * @param array<non-empty-string, list<Field>> $definitionsMap
     *
     * @return list<EntityDefinition>
     */
    private function compileDefinitions(array $definitionsMap): array
    {
        $definitions = [];

        foreach ($definitionsMap as $entityName => $fields) {
            $fieldCollection = new FieldCollection($fields);

            $definitions[$entityName] = new class($entityName, $fieldCollection) extends EntityDefinition {
                /**
                 * @param non-empty-string $name
                 */
                public function __construct(
                    private readonly string $name,
                    private readonly FieldCollection $fieldList,
                ) {
                }

                public function getEntityName(): string
                {
                    return $this->name;
                }

                protected function defineFields(): FieldCollection
                {
                    return $this->fieldList;
                }
            };
        }

        $registry = static::createStub(DefinitionInstanceRegistry::class);
        $registry->method('getByClassOrEntityName')->willReturnCallback(
            function (string $classOrName) use ($definitions): EntityDefinition {
                foreach ($definitions as $def) {
                    if ($def::class === $classOrName || $def->getEntityName() === $classOrName) {
                        return $def;
                    }
                }

                throw new \RuntimeException('Definition not found: ' . $classOrName);
            }
        );

        foreach ($definitions as $def) {
            $def->compile($registry);
        }

        return array_values($definitions);
    }
}
