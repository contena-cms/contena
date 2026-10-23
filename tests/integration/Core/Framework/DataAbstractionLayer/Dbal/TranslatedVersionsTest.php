<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\DataAbstractionLayer\Dbal;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Aggregate\BlogManufacturer\BlogManufacturerDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogManufacturerTranslation\BlogManufacturerTranslationDefinition;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Content\Test\Blog\BlogBuilder;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\FieldResolver\FieldResolverContext;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\FieldResolver\TranslationFieldResolver;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
class TranslatedVersionsTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var string[]
     */
    private array $languages = [
        'en-GB', 'de-DE',
    ];

    /**
     * @var EntityRepository<BlogManufacturerCollection>
     */
    private EntityRepository $manufacturerRepository;

    protected function setUp(): void
    {
        $this->manufacturerRepository = static::getContainer()->get('blog_manufacturer.repository');
    }

    public function testTranslationsAreAllSelectable(): void
    {
        $enContext = Context::createDefaultContext();
        $manufacturerId = Uuid::randomHex();

        $this->createManufacturer($this->manufacturerRepository, $manufacturerId, $enContext);

        $versionId = $this->manufacturerRepository->createVersion($manufacturerId, $enContext);
        $enVersionContext = $enContext->createWithVersionId($versionId);
        $deContext = $this->createDeContext($enContext);
        $deVersionContext = $deContext->createWithVersionId($versionId);

        $this->manufacturerRepository->update([[
            'id' => $manufacturerId,
            'name' => 'version-en-GB',
        ]], $enVersionContext);

        $this->manufacturerRepository->update([[
            'id' => $manufacturerId,
            'name' => 'version-de-DE',
        ]], $deVersionContext);

        $enOriginal = $this->manufacturerRepository->search(new Criteria([$manufacturerId]), $enContext)->getEntities()->first();
        $enVersion = $this->manufacturerRepository->search(new Criteria([$manufacturerId]), $enVersionContext)->getEntities()->first();
        $deOriginal = $this->manufacturerRepository->search(new Criteria([$manufacturerId]), $deContext)->getEntities()->first();
        $deVersion = $this->manufacturerRepository->search(new Criteria([$manufacturerId]), $deVersionContext)->getEntities()->first();

        static::assertNotNull($enOriginal);
        static::assertNotNull($enVersion);
        static::assertNotNull($deOriginal);
        static::assertNotNull($deVersion);
        static::assertSame('original-en-GB', $enOriginal->getName());
        static::assertSame('version-en-GB', $enVersion->getName());
        static::assertSame('original-de-DE', $deOriginal->getName());
        static::assertSame('version-de-DE', $deVersion->getName());
    }

    public function testTranslationsFallbackToOriginal(): void
    {
        $enContext = Context::createDefaultContext();
        $manufacturerId = Uuid::randomHex();

        $this->createManufacturer($this->manufacturerRepository, $manufacturerId, $enContext);

        $versionId = $this->manufacturerRepository->createVersion($manufacturerId, $enContext);
        $enVersionContext = $enContext->createWithVersionId($versionId);
        $deContext = $this->createDeContext($enContext);
        $deVersionContext = $deContext->createWithVersionId($versionId);

        $this->manufacturerRepository->update([[
            'id' => $manufacturerId,
            'name' => 'version-en-GB',
        ]], $enVersionContext);

        $enOriginal = $this->manufacturerRepository->search(new Criteria([$manufacturerId]), $enContext)->getEntities()->first();
        $enVersion = $this->manufacturerRepository->search(new Criteria([$manufacturerId]), $enVersionContext)->getEntities()->first();
        $deOriginal = $this->manufacturerRepository->search(new Criteria([$manufacturerId]), $deContext)->getEntities()->first();
        $deVersion = $this->manufacturerRepository->search(new Criteria([$manufacturerId]), $deVersionContext)->getEntities()->first();

        static::assertNotNull($enOriginal);
        static::assertNotNull($enVersion);
        static::assertNotNull($deOriginal);
        static::assertNotNull($deVersion);
        static::assertSame('original-en-GB', $enOriginal->getName());
        static::assertSame('version-en-GB', $enVersion->getName());
        static::assertSame('original-de-DE', $deOriginal->getName());
        static::assertSame('original-de-DE', $deVersion->getName());
    }

    public function testInheritenceWithBlogsAllAreTranslated(): void
    {
        $enContext = Context::createDefaultContext();
        $deContext = $this->createDeContext($enContext);
        $blogRepository = static::getContainer()->get('blog.repository');

        $ids = $this->createParentChildBlog();

        $enVersionContext = $enContext->createWithVersionId($blogRepository->createVersion($ids->get('child'), $enContext));
        $deVersionContext = $deContext->createWithVersionId($blogRepository->createVersion($ids->get('child'), $deContext));
        $blogRepository->update([['id' => $ids->get('child'), 'name' => 'child-version-en-GB']], $enVersionContext);
        $blogRepository->update([['id' => $ids->get('child'), 'name' => 'child-version-de-DE']], $deVersionContext);

        $this->assertBlogNames([
            ['child-original-en-GB', $enContext],
            ['child-original-de-DE', $deContext],
            ['child-version-en-GB', $enVersionContext],
            ['child-version-de-DE', $deVersionContext],
        ], $ids->get('child'));
        $this->assertBlogNames([
            ['parent-original-en-GB', $enContext],
            ['parent-original-de-DE', $deContext],
            ['parent-original-en-GB', $enVersionContext],
            ['parent-original-de-DE', $deVersionContext],
        ], $ids->get('parent'));
    }

    public function testInheritanceWithBlogsOnlyEnInVersionTranslated(): void
    {
        $enContext = Context::createDefaultContext();
        $deContext = $this->createDeContext($enContext);
        $blogRepository = static::getContainer()->get('blog.repository');

        $ids = $this->createParentChildBlog();

        $enVersionContext = $enContext->createWithVersionId($blogRepository->createVersion($ids->get('child'), $enContext));
        $deVersionContext = $deContext->createWithVersionId($blogRepository->createVersion($ids->get('child'), $deContext));
        $blogRepository->update([['id' => $ids->get('child'), 'name' => 'child-version-en-GB']], $enVersionContext);

        $this->assertBlogNames([
            ['child-original-en-GB', $enContext],
            ['child-original-de-DE', $deContext],
            ['child-version-en-GB', $enVersionContext],
            ['child-original-de-DE', $deVersionContext],
        ], $ids->get('child'));
        $this->assertBlogNames([
            ['parent-original-en-GB', $enContext],
            ['parent-original-de-DE', $deContext],
            ['parent-original-en-GB', $enVersionContext],
            ['parent-original-de-DE', $deVersionContext],
        ], $ids->get('parent'));
    }

    public function testInheritanceWithOnlyParentTranslations(): void
    {
        $enContext = Context::createDefaultContext();
        $deContext = $this->createDeContext($enContext);
        $blogRepository = static::getContainer()->get('blog.repository');

        $ids = $this->createParentChildBlog(false);

        $enVersionContext = $enContext->createWithVersionId($blogRepository->createVersion($ids->get('child'), $enContext));
        $deVersionContext = $deContext->createWithVersionId($blogRepository->createVersion($ids->get('child'), $deContext));

        $this->assertBlogNames([
            ['parent-original-en-GB', $enContext],
            ['parent-original-de-DE', $deContext],
            ['parent-original-en-GB', $enVersionContext],
            ['parent-original-de-DE', $deVersionContext],
        ], $ids->get('parent'));

        $this->assertBlogNames([
            ['parent-original-en-GB', $enContext],
            ['parent-original-de-DE', $deContext],
            ['parent-original-en-GB', $enVersionContext],
            ['parent-original-de-DE', $deVersionContext],
        ], $ids->get('child'));
    }

    public function testFieldResolverThrowsOnNotTranslatedEntities(): void
    {
        $resolver = static::getContainer()->get(TranslationFieldResolver::class);
        $context = new FieldResolverContext(
            '',
            '',
            new TranslatedField(''),
            static::getContainer()->get(BlogManufacturerTranslationDefinition::class),
            static::getContainer()->get(BlogManufacturerTranslationDefinition::class),
            new QueryBuilder(static::getContainer()->get(Connection::class)),
            Context::createDefaultContext(),
            null
        );

        $this->expectException(\RuntimeException::class);
        $resolver->join($context);
    }

    public function testFieldResolverReturnsOnNotTranslatedFields(): void
    {
        $resolver = static::getContainer()->get(TranslationFieldResolver::class);
        $result = $resolver->join(new FieldResolverContext(
            '',
            'THIS_SHOULD_BE_RETURNED',
            new StringField('', ''),
            static::getContainer()->get(BlogManufacturerDefinition::class),
            static::getContainer()->get(BlogManufacturerDefinition::class),
            new QueryBuilder(static::getContainer()->get(Connection::class)),
            Context::createDefaultContext(),
            null
        ));

        static::assertSame('THIS_SHOULD_BE_RETURNED', $result);
    }

    /**
     * @param EntityRepository<BlogManufacturerCollection> $blogManufacturerRepository
     */
    private function createManufacturer(EntityRepository $blogManufacturerRepository, string $blogManufacturerId, Context $context): void
    {
        $translations = $this->getTestTranslations();
        $blogManufacturerRepository->create([[
            'id' => $blogManufacturerId,
            'translations' => $translations,
        ]], $context);
    }

    private function createDeContext(Context $enContext): Context
    {
        $deLanguageId = $this->getDeDeLanguageId();

        return new Context(
            $enContext->getSource(),
            $enContext->getRuleIds(),
            $enContext->getCurrencyId(),
            [$deLanguageId, $enContext->getLanguageId()],
            $enContext->getVersionId(),
            $enContext->getCurrencyFactor(),
            $enContext->considerInheritance(),
            $enContext->getTaxState()
        );
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function getTestTranslations(string $prefix = ''): array
    {
        $translations = [];
        foreach ($this->languages as $locale) {
            $translations[$locale] = ['name' => $prefix . 'original-' . $locale];
        }

        return $translations;
    }

    /**
     * @param array<int, array<int, Context|string>> $assertions
     */
    private function assertBlogNames(array $assertions, string $id): void
    {
        foreach ($assertions as [$name, $context]) {
            static::assertInstanceOf(Context::class, $context);
            static::assertIsString($name);
            $this->assertBlogName($name, $id, $context);
        }
    }

    private function assertBlogName(string $name, string $id, Context $context): void
    {
        $context->setConsiderInheritance(true);

        /** @var BlogEntity $blog */
        $blog = static::getContainer()
            ->get('blog.repository')
            ->search(new Criteria([$id]), $context)->getEntities()->first();

        static::assertTrue($context->considerInheritance());
        static::assertSame($name, $blog->getTranslated()['name'], \sprintf(
            'Expected %s with language chain %s but got %s, version context: %s',
            $name,
            (string) print_r($context->getLanguageIdChain(), true),
            (string) $blog->getName(),
            $context->getVersionId() === Defaults::LIVE_VERSION ? 'NO' : 'YES'
        ));

        $context->setConsiderInheritance(false);
    }

    private function createParentChildBlog(bool $addChildTranslations = true): IdsCollection
    {
        $context = Context::createDefaultContext();
        $ids = new IdsCollection();

        $parentBlog = (new BlogBuilder($ids, 'parent'))
            ->price(100)
            ->build();

        $childBlog = (new BlogBuilder($ids, 'child'))
            ->parent('parent')
            ->price(100)
            ->build();

        unset($childBlog['name'], $parentBlog['name']);

        $parentBlog['translations'] = $this->getTestTranslations('parent-');

        if ($addChildTranslations) {
            $childBlog['translations'] = $this->getTestTranslations('child-');
        }

        static::getContainer()
            ->get('blog.repository')
            ->create([$parentBlog, $childBlog], $context);

        return $ids;
    }
}
