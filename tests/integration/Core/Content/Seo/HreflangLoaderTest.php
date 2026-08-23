<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Seo;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Content\Seo\HreflangLoaderInterface;
use Contena\Core\Content\Seo\HreflangLoaderParameter;
use Contena\Core\Content\Seo\SeoUrl\SeoUrlCollection;
use Contena\Core\Content\Test\TestBlogSeoUrlRoute;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainCollection;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainDefinition;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainEntity;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Language\LanguageCollection;
use Contena\Core\System\Language\LanguageEntity;
use Contena\Core\System\Locale\LocaleEntity;
use Contena\Core\Test\TestDefaults;

/**
 * @internal
 */
class HreflangLoaderTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<SeoUrlCollection>
     */
    private EntityRepository $seoUrlRepository;

    /**
     * @var EntityRepository<ChannelDomainCollection>
     */
    private EntityRepository $channelDomainRepository;

    private ChannelContext $channelContext;

    private HreflangLoaderInterface $hreflangLoader;

    /**
     * @var EntityRepository<LanguageCollection>
     */
    private EntityRepository $languageRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanDefaultChannelDomain();

        $this->seoUrlRepository = static::getContainer()->get('seo_url.repository');
        $this->channelDomainRepository = static::getContainer()->get('channel_domain.repository');
        $this->languageRepository = static::getContainer()->get('language.repository');

        $contextFactory = static::getContainer()->get(ChannelContextFactory::class);
        $this->channelContext = $contextFactory->create('', TestDefaults::CHANNEL);

        $this->hreflangLoader = static::getContainer()->get(HreflangLoaderInterface::class);

        $this->createBlogs();
    }

    public function testDisable(): void
    {
        $randomBlog = static::getContainer()->get('blog.repository')->searchIds(new Criteria(), $this->channelContext->getContext());
        $this->channelContext->getChannel()->setHreflangActive(false);

        $randomId = $randomBlog->firstId();
        static::assertNotNull($randomId);
        $links = $this->hreflangLoader->load($this->createParameter($randomId));

        static::assertCount(0, $links);
    }

    public function testBlogWithOnlyOneDomain(): void
    {
        $blogId = Uuid::randomHex();

        $languageId = $this->languageRepository->searchIds(new Criteria(), $this->channelContext->getContext())->firstId();
        static::assertNotNull($languageId);

        $domain = new ChannelDomainEntity();
        $domain->setId(Uuid::randomHex());
        $domain->setUrl('https://test.de');
        $domain->setHreflangUseOnlyLocale(false);
        $domain->setLanguageId($languageId);

        static::assertInstanceOf(ChannelDomainCollection::class, $this->channelContext->getChannel()->getDomains());
        $this->channelContext->getChannel()->getDomains()->add($domain);
        $firstDomain = $this->channelContext->getChannel()->getDomains()->first();
        static::assertInstanceOf(ChannelDomainEntity::class, $firstDomain);

        $this->seoUrlRepository->create([
            [
                'id' => Uuid::randomHex(),
                'channelId' => $this->channelContext->getChannelId(),
                'languageId' => $firstDomain->getLanguageId(),
                'routeName' => TestBlogSeoUrlRoute::ROUTE_NAME,
                'foreignKey' => $blogId,
                'pathInfo' => '/test/' . $blogId,
                'seoPathInfo' => '/test-path',
            ],
        ], $this->channelContext->getContext());

        $links = $this->hreflangLoader->load($this->createParameter($blogId));
        static::assertCount(0, $links);
    }

    public function testBlogWithTwoDomains(): void
    {
        $this->channelContext->getChannel()->setHreflangActive(true);

        $blogId = Uuid::randomHex();

        list($first, $last) = $this->getFirstAndLastLanguages();

        $this->channelDomainRepository->create([
            [
                'url' => 'https://test.de',
                'hreflangUseOnlyLocale' => false,
                'languageId' => $first->getId(),
                'channelId' => $this->channelContext->getChannelId(),
                'snippetSetId' => $this->getSnippetSetIdForLocale('de-DE'),
            ],
            [
                'url' => 'https://test.de/en',
                'hreflangUseOnlyLocale' => false,
                'languageId' => $last->getId(),
                'channelId' => $this->channelContext->getChannelId(),
                'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
            ],
        ], $this->channelContext->getContext());

        $this->seoUrlRepository->create([
            [
                'channelId' => $this->channelContext->getChannelId(),
                'languageId' => $first->getId(),
                'routeName' => TestBlogSeoUrlRoute::ROUTE_NAME,
                'foreignKey' => $blogId,
                'pathInfo' => '/test/' . $blogId,
                'seoPathInfo' => 'test-path',
                'isCanonical' => true,
            ],
            [
                'channelId' => $this->channelContext->getChannelId(),
                'languageId' => $last->getId(),
                'routeName' => TestBlogSeoUrlRoute::ROUTE_NAME,
                'foreignKey' => $blogId,
                'pathInfo' => '/test/' . $blogId,
                'seoPathInfo' => 'test-path',
                'isCanonical' => true,
            ],
        ], $this->channelContext->getContext());

        $links = $this->hreflangLoader->load($this->createParameter($blogId));

        static::assertCount(2, $links);
        $foundLinks = 0;

        static::assertInstanceOf(LocaleEntity::class, $first->getLocale());
        static::assertInstanceOf(LocaleEntity::class, $last->getLocale());

        foreach ($links->getElements() as $element) {
            if ($element->getLocale() === $first->getLocale()->getCode()) {
                static::assertSame('https://test.de/test-path', $element->getUrl());
                ++$foundLinks;
            }

            if ($element->getLocale() === $last->getLocale()->getCode()) {
                static::assertSame('https://test.de/en/test-path', $element->getUrl());
                ++$foundLinks;
            }
        }

        static::assertSame(2, $foundLinks);
    }

    public function testBlogWithTwoDomainsWithDefault(): void
    {
        $this->channelContext->getChannel()->setHreflangActive(true);

        $blogId = Uuid::randomHex();

        list($first, $last) = $this->getFirstAndLastLanguages();

        $defaultDomainId = Uuid::randomHex();
        $this->channelContext->getChannel()->setHreflangDefaultDomainId($defaultDomainId);

        $this->channelDomainRepository->create([
            [
                'id' => $defaultDomainId,
                'url' => 'https://test.de',
                'hreflangUseOnlyLocale' => false,
                'languageId' => $first->getId(),
                'channelId' => $this->channelContext->getChannelId(),
                'snippetSetId' => $this->getSnippetSetIdForLocale('de-DE'),
            ],
            [
                'url' => 'https://test.de/en',
                'hreflangUseOnlyLocale' => false,
                'languageId' => $last->getId(),
                'channelId' => $this->channelContext->getChannelId(),
                'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
            ],
        ], $this->channelContext->getContext());

        $this->seoUrlRepository->create([
            [
                'channelId' => $this->channelContext->getChannelId(),
                'languageId' => $first->getId(),
                'routeName' => TestBlogSeoUrlRoute::ROUTE_NAME,
                'foreignKey' => $blogId,
                'pathInfo' => '/test/' . $blogId,
                'seoPathInfo' => 'test-path',
                'isCanonical' => true,
            ],
            [
                'channelId' => $this->channelContext->getChannelId(),
                'languageId' => $last->getId(),
                'routeName' => TestBlogSeoUrlRoute::ROUTE_NAME,
                'foreignKey' => $blogId,
                'pathInfo' => '/test/' . $blogId,
                'seoPathInfo' => 'test-path',
                'isCanonical' => true,
            ],
        ], $this->channelContext->getContext());

        $links = $this->hreflangLoader->load($this->createParameter($blogId));

        static::assertCount(3, $links);

        $foundLinks = 0;

        static::assertInstanceOf(LocaleEntity::class, $first->getLocale());
        static::assertInstanceOf(LocaleEntity::class, $last->getLocale());

        foreach ($links->getElements() as $element) {
            if ($element->getLocale() === $first->getLocale()->getCode()) {
                static::assertSame('https://test.de/test-path', $element->getUrl());
                ++$foundLinks;
            }

            if ($element->getLocale() === $last->getLocale()->getCode()) {
                static::assertSame('https://test.de/en/test-path', $element->getUrl());
                ++$foundLinks;
            }

            if ($element->getLocale() === 'x-default') {
                static::assertSame('https://test.de/test-path', $element->getUrl());
                ++$foundLinks;
            }
        }

        static::assertSame(3, $foundLinks);
    }

    public function testBlogWithTwoDomainsFirstOnlyLocale(): void
    {
        $this->channelContext->getChannel()->setHreflangActive(true);

        $blogId = Uuid::randomHex();

        list($first, $last) = $this->getFirstAndLastLanguages();

        $defaultDomainId = Uuid::randomHex();
        $this->channelContext->getChannel()->setHreflangDefaultDomainId($defaultDomainId);

        $this->channelDomainRepository->create([
            [
                'id' => $defaultDomainId,
                'url' => 'https://test.de',
                'hreflangUseOnlyLocale' => true,
                'languageId' => $first->getId(),
                'channelId' => $this->channelContext->getChannelId(),
                'snippetSetId' => $this->getSnippetSetIdForLocale('de-DE'),
            ],
            [
                'url' => 'https://test.de/en',
                'hreflangUseOnlyLocale' => false,
                'languageId' => $last->getId(),
                'channelId' => $this->channelContext->getChannelId(),
                'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
            ],
        ], $this->channelContext->getContext());

        $this->seoUrlRepository->create([
            [
                'channelId' => $this->channelContext->getChannelId(),
                'languageId' => $first->getId(),
                'routeName' => TestBlogSeoUrlRoute::ROUTE_NAME,
                'foreignKey' => $blogId,
                'pathInfo' => '/test/' . $blogId,
                'seoPathInfo' => 'test-path',
                'isCanonical' => true,
            ],
            [
                'channelId' => $this->channelContext->getChannelId(),
                'languageId' => $last->getId(),
                'routeName' => TestBlogSeoUrlRoute::ROUTE_NAME,
                'foreignKey' => $blogId,
                'pathInfo' => '/test/' . $blogId,
                'seoPathInfo' => 'test-path',
                'isCanonical' => true,
            ],
        ], $this->channelContext->getContext());

        $links = $this->hreflangLoader->load($this->createParameter($blogId));

        static::assertCount(3, $links);

        $foundLinks = 0;

        static::assertInstanceOf(LocaleEntity::class, $first->getLocale());
        static::assertInstanceOf(LocaleEntity::class, $last->getLocale());

        foreach ($links->getElements() as $element) {
            if ($element->getLocale() === mb_substr($first->getLocale()->getCode(), 0, 2)) {
                static::assertSame('https://test.de/test-path', $element->getUrl());
                ++$foundLinks;
            }

            if ($element->getLocale() === $last->getLocale()->getCode()) {
                static::assertSame('https://test.de/en/test-path', $element->getUrl());
                ++$foundLinks;
            }
        }

        static::assertSame(2, $foundLinks);
    }

    public function testHomePageWithTwoDomains(): void
    {
        $this->channelContext->getChannel()->setHreflangActive(true);

        list($first, $last) = $this->getFirstAndLastLanguages();

        $this->channelDomainRepository->create([
            [
                'url' => 'https://test.de',
                'hreflangUseOnlyLocale' => false,
                'languageId' => $first->getId(),
                'channelId' => $this->channelContext->getChannelId(),
                'snippetSetId' => $this->getSnippetSetIdForLocale('de-DE'),
            ],
            [
                'url' => 'https://test.de/en',
                'hreflangUseOnlyLocale' => false,
                'languageId' => $last->getId(),
                'channelId' => $this->channelContext->getChannelId(),
                'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
            ],
        ], $this->channelContext->getContext());

        $links = $this->hreflangLoader->load(
            new HreflangLoaderParameter('frontend.home.page', [], $this->channelContext, true)
        );

        static::assertCount(2, $links);
        $foundLinks = 0;

        static::assertInstanceOf(LocaleEntity::class, $first->getLocale());
        static::assertInstanceOf(LocaleEntity::class, $last->getLocale());

        foreach ($links->getElements() as $element) {
            if ($element->getLocale() === $first->getLocale()->getCode()) {
                static::assertSame('https://test.de', $element->getUrl());
                ++$foundLinks;
            }

            if ($element->getLocale() === $last->getLocale()->getCode()) {
                static::assertSame('https://test.de/en', $element->getUrl());
                ++$foundLinks;
            }
        }

        static::assertSame(2, $foundLinks);
    }

    public function testHomePageWithTwoDomainsAndDefault(): void
    {
        $this->channelContext->getChannel()->setHreflangActive(true);

        list($first, $last) = $this->getFirstAndLastLanguages();

        $defaultDomainId = Uuid::randomHex();
        $this->channelContext->getChannel()->setHreflangDefaultDomainId($defaultDomainId);

        $this->channelDomainRepository->create([
            [
                'id' => $defaultDomainId,
                'url' => 'https://test.de',
                'hreflangUseOnlyLocale' => false,
                'languageId' => $first->getId(),
                'channelId' => $this->channelContext->getChannelId(),
                'snippetSetId' => $this->getSnippetSetIdForLocale('de-DE'),
            ],
            [
                'url' => 'https://test.de/en',
                'hreflangUseOnlyLocale' => false,
                'languageId' => $last->getId(),
                'channelId' => $this->channelContext->getChannelId(),
                'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
            ],
        ], $this->channelContext->getContext());

        $links = $this->hreflangLoader->load(
            new HreflangLoaderParameter('frontend.home.page', [], $this->channelContext, true)
        );

        static::assertCount(3, $links);
        $foundLinks = 0;

        static::assertInstanceOf(LocaleEntity::class, $first->getLocale());
        static::assertInstanceOf(LocaleEntity::class, $last->getLocale());

        foreach ($links->getElements() as $element) {
            if ($element->getLocale() === $first->getLocale()->getCode()) {
                static::assertSame('https://test.de', $element->getUrl());
                ++$foundLinks;
            }

            if ($element->getLocale() === $last->getLocale()->getCode()) {
                static::assertSame('https://test.de/en', $element->getUrl());
                ++$foundLinks;
            }

            if ($element->getLocale() === 'x-default') {
                static::assertSame('https://test.de', $element->getUrl());
                ++$foundLinks;
            }
        }

        static::assertSame(3, $foundLinks);
    }

    private function createParameter(string $blogId): HreflangLoaderParameter
    {
        return new HreflangLoaderParameter(TestBlogSeoUrlRoute::ROUTE_NAME, [
            'blogId' => $blogId,
        ], $this->channelContext);
    }

    private function cleanDefaultChannelDomain(): void
    {
        $connection = static::getContainer()->get(Connection::class);

        $connection->delete(ChannelDomainDefinition::ENTITY_NAME, [
            'channel_id' => Uuid::fromHexToBytes(TestDefaults::CHANNEL),
        ]);
    }

    private function createBlogs(): void
    {
        $blogs = $this->getBlogTestData($this->channelContext);

        static::getContainer()->get('blog.repository')->create($blogs, $this->channelContext->getContext());
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function getBlogTestData(ChannelContext $channelContext): array
    {
        $blogs = [
            [
                'id' => Uuid::randomHex(),
                'name' => 'test blog 1',
                'active' => true,
                'visibilities' => [
                    ['channelId' => $channelContext->getChannelId(), 'visibility' => BlogVisibilityDefinition::VISIBILITY_ALL],
                ],
            ],
            [
                'id' => Uuid::randomHex(),
                'name' => 'test blog 2',
                'active' => true,
                'visibilities' => [
                    ['channelId' => $channelContext->getChannelId(), 'visibility' => BlogVisibilityDefinition::VISIBILITY_ALL],
                ],
            ],
            [
                'id' => Uuid::randomHex(),
                'name' => 'test blog 3',
                'active' => true,
                'visibilities' => [
                    ['channelId' => $channelContext->getChannelId(), 'visibility' => BlogVisibilityDefinition::VISIBILITY_ALL],
                ],
            ],
            [
                'id' => Uuid::randomHex(),
                'name' => 'test blog 4',
                'active' => true,
                'visibilities' => [
                    ['channelId' => $channelContext->getChannelId(), 'visibility' => BlogVisibilityDefinition::VISIBILITY_ALL],
                ],
            ],
            [
                'id' => Uuid::randomHex(),
                'name' => 'test blog 5',
                'active' => true,
                'visibilities' => [
                    ['channelId' => $channelContext->getChannelId(), 'visibility' => BlogVisibilityDefinition::VISIBILITY_ALL],
                ],
            ],
        ];

        return $blogs;
    }

    /**
     * @return LanguageEntity[]
     */
    private function getFirstAndLastLanguages(): array
    {
        $criteria = new Criteria();
        $criteria->addAssociation('locale');

        $languages = $this->languageRepository->search($criteria, $this->channelContext->getContext())->getEntities();

        $first = $languages->first();
        static::assertInstanceOf(LanguageEntity::class, $first);
        $last = $languages->last();
        static::assertInstanceOf(LanguageEntity::class, $last);

        return [$first, $last];
    }
}
