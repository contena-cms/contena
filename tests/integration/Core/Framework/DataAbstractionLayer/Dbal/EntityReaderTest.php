<?php

declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\DataAbstractionLayer\Dbal;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Test\Blog\BlogBuilder;
use Contena\Core\Defaults;
use Contena\Core\Framework\Api\Context\SystemSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\CriteriaFieldsResolver;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\CriteriaQueryBuilder;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\EntityHydrator;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\EntityReader;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Grouping\FieldGrouping;
use Contena\Core\Framework\DataAbstractionLayer\Search\Parser\SqlQueryParser;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
class EntityReaderTest extends TestCase
{
    use IntegrationTestBehaviour;

    private EntityReader $entityReader;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entityReader = new EntityReader(
            static::getContainer()->get(Connection::class),
            static::getContainer()->get(EntityHydrator::class),
            static::getContainer()->get(EntityDefinitionQueryHelper::class),
            static::getContainer()->get(SqlQueryParser::class),
            static::getContainer()->get(CriteriaQueryBuilder::class),
            static::getContainer()->get('logger'),
            static::getContainer()->get(CriteriaFieldsResolver::class)
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        static::getContainer()->get(Connection::class)->executeQuery('SET FOREIGN_KEY_CHECKS=1;');
    }

    public function testReadLoadsTranslationsAssociations(): void
    {
        $blogId = $this->createBlog(
            deDeTranslation: 'Deutscher Name',
            defaultTranslation: null,
        );

        $criteria = new Criteria();
        $criteria->addAssociations(['translations']);

        $blogs = $this->entityReader->read(
            static::getContainer()->get(BlogDefinition::class),
            $criteria,
            Context::createDefaultContext(),
        );

        static::assertInstanceOf(BlogCollection::class, $blogs);
        $translations = $blogs->get($blogId)?->getTranslations();
        static::assertNotNull($translations);
        static::assertCount(2, $translations);
        $deDeTranslation = $translations->filterByLanguageId($this->getDeDeLanguageId())->first();
        static::assertSame('Deutscher Name', $deDeTranslation?->get('name'));
    }

    public function testReadLoadsTranslationsAssociationsWithCriteriaFields(): void
    {
        $blogId = $this->createBlog(
            deDeTranslation: 'Deutscher Name',
            defaultTranslation: null,
        );

        $criteria = new Criteria();
        $criteria->addAssociations(['translations']);
        $criteria->addFields(['translations.name']);

        $blogs = $this->entityReader->read(
            static::getContainer()->get(BlogDefinition::class),
            $criteria,
            Context::createDefaultContext(),
        );

        $translations = $blogs->get($blogId)?->get('translations');
        static::assertInstanceOf(EntityCollection::class, $translations);
        static::assertCount(2, $translations);
        $deDeTranslation = $translations
            ->filter(fn (Entity $entity) => $entity->get('languageId') === $this->getDeDeLanguageId())
            ->first();
        static::assertSame('Deutscher Name', $deDeTranslation?->get('name'));
    }

    public function testReadLoadsTranslatedFieldsInCorrectLanguage(): void
    {
        $blogId = $this->createBlog(
            deDeTranslation: 'Deutscher Name',
            defaultTranslation: 'Default Name',
        );

        $blogs = $this->entityReader->read(
            static::getContainer()->get(BlogDefinition::class),
            new Criteria(),
            self::createLocalizedContext([
                $this->getDeDeLanguageId(),
                Defaults::LANGUAGE_SYSTEM,
            ]),
        );

        static::assertInstanceOf(BlogCollection::class, $blogs);
        $translatedFields = $blogs->get($blogId)?->get('translated');
        static::assertSame('Deutscher Name', $translatedFields['name']);
    }

    public function testReadLoadsTranslatedFieldsInCorrectLanguageWithCriteriaFields(): void
    {
        $blogId = $this->createBlog(
            deDeTranslation: 'Deutscher Name',
            defaultTranslation: 'Default name',
        );

        $criteria = new Criteria();
        // Selecting the name field is necessary to include the the translated value in the result
        $criteria->addFields(['name']);

        $blogs = $this->entityReader->read(
            static::getContainer()->get(BlogDefinition::class),
            $criteria,
            self::createLocalizedContext([
                $this->getDeDeLanguageId(),
                Defaults::LANGUAGE_SYSTEM,
            ]),
        );

        $translatedFields = $blogs->get($blogId)?->get('translated');
        static::assertNotNull($translatedFields);
        static::assertCount(1, $translatedFields);
        static::assertSame('Deutscher Name', $translatedFields['name']);
    }

    public function testReadLoadsTranslatedFieldsByApplyingLanguageOverrides(): void
    {
        $blogId = $this->createBlog(
            deDeTranslation: null,
            defaultTranslation: 'Fallback name',
        );

        $criteria = new Criteria();
        // Selecting the name field is necessary to include the the translated value in the result
        $criteria->addFields(['name']);

        $blogs = $this->entityReader->read(
            static::getContainer()->get(BlogDefinition::class),
            $criteria,
            self::createLocalizedContext([
                $this->getDeDeLanguageId(),
                Defaults::LANGUAGE_SYSTEM,
            ]),
        );

        $translatedFields = $blogs->get($blogId)?->get('translated');
        static::assertNotNull($translatedFields);
        static::assertCount(1, $translatedFields);
        static::assertSame('Fallback name', $translatedFields['name']);
    }

    public function testReadLoadsTranslatedFieldsByApplyingInheritanceAndLanguageOverridesPreferringOwnTranslation(): void
    {
        $ids = new IdsCollection();
        $this->createBlog(
            deDeTranslation: 'Parent: Deutscher Name',
            defaultTranslation: 'Parent: Fallback name',
            blogNumber: 'parent-blog',
            ids: $ids,
        );
        $blogId = $this->createBlog(
            deDeTranslation: 'Deutscher Name',
            defaultTranslation: 'Fallback name',
            parentBlogNumber: 'parent-blog',
            ids: $ids,
        );

        $criteria = new Criteria();
        // Selecting the name field is necessary to include the the translated value in the result
        $criteria->addFields(['name']);

        $context = self::createLocalizedContext([
            $this->getDeDeLanguageId(),
            Defaults::LANGUAGE_SYSTEM,
        ]);
        $context->setConsiderInheritance(true);

        $blogs = $this->entityReader->read(
            static::getContainer()->get(BlogDefinition::class),
            $criteria,
            $context,
        );

        $translatedFields = $blogs->get($blogId)?->get('translated');
        static::assertNotNull($translatedFields);
        static::assertCount(1, $translatedFields);
        static::assertSame('Deutscher Name', $translatedFields['name']);
    }

    public function testReadLoadsTranslatedFieldsByApplyingInheritanceAndLanguageOverridesUsingParentTranslationAsFallback(): void
    {
        $ids = new IdsCollection();
        $this->createBlog(
            deDeTranslation: 'Parent: Deutscher Name',
            defaultTranslation: 'Parent: Fallback name',
            blogNumber: 'parent-blog',
            ids: $ids,
        );
        $blogId = $this->createBlog(
            deDeTranslation: null,
            defaultTranslation: 'Fallback name',
            parentBlogNumber: 'parent-blog',
            ids: $ids,
        );

        $criteria = new Criteria();
        // Selecting the name field is necessary to include the the translated value in the result
        $criteria->addFields(['name']);

        $context = self::createLocalizedContext([
            $this->getDeDeLanguageId(),
            Defaults::LANGUAGE_SYSTEM,
        ]);
        $context->setConsiderInheritance(true);

        $blogs = $this->entityReader->read(
            static::getContainer()->get(BlogDefinition::class),
            $criteria,
            $context,
        );

        $translatedFields = $blogs->get($blogId)?->get('translated');
        static::assertNotNull($translatedFields);
        static::assertCount(1, $translatedFields);
        static::assertSame('Parent: Deutscher Name', $translatedFields['name']);
    }

    public function testAssociationWithOrderBy(): void
    {
        $ids = new IdsCollection();
        $blogBuilder = (new BlogBuilder($ids, 'test'))
            ->active(true)
            ->price(100)
            ->visibility()
            ->variant(
                (new BlogBuilder($ids, 'test-1'))
                    ->active(true)
                    ->name('foo')
                    ->visibility()
                    ->build()
            );

        /** @var EntityRepository<BlogCollection> $entityRepository */
        $entityRepository = static::getContainer()->get('blog.repository');
        $entityRepository->create(
            [$blogBuilder->build()],
            Context::createDefaultContext()
        );

        $criteria = new Criteria([$ids->get('test')]);

        $criteria->getAssociation('children')
            ->addSorting(new FieldSorting('purchaseUnit'))
            ->addGroupField(new FieldGrouping('displayGroup'));

        $context = Context::createDefaultContext();
        $context->setConsiderInheritance(true);

        $result = $entityRepository->search($criteria, $context);

        static::assertTrue($result->getEntities()->has($ids->get('test')));
    }

    private function createBlog(
        ?string $deDeTranslation,
        ?string $defaultTranslation,
        string $blogNumber = 'blog-1',
        ?string $parentBlogNumber = null,
        ?IdsCollection $ids = null
    ): string {
        $ids ??= new IdsCollection();
        $blogBuilder = new BlogBuilder($ids, $blogNumber);
        $blogBuilder->price(100);
        if ($deDeTranslation !== null) {
            $blogBuilder->translation($this->getDeDeLanguageId(), 'name', $deDeTranslation);
        }
        if ($defaultTranslation !== null) {
            $blogBuilder->translation(Defaults::LANGUAGE_SYSTEM, 'name', $defaultTranslation);
        }
        if ($parentBlogNumber !== null) {
            $blogBuilder->parent($parentBlogNumber);
        }

        static::getContainer()->get('blog.repository')->create(
            [$blogBuilder->build()],
            Context::createDefaultContext()
        );

        return $ids->get($blogNumber);
    }

    /**
     * @param non-empty-list<string> $languageIdChain
     */
    private static function createLocalizedContext(array $languageIdChain): Context
    {
        return new Context(
            source: new SystemSource(),
            ruleIds: [],
            currencyId: Defaults::CURRENCY,
            languageIdChain: $languageIdChain,
        );
    }
}
