<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Sitemap\Provider;

use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Category\CategoryDefinition;
use Contena\Core\Content\Category\CategoryEntity;
use Contena\Core\Content\Sitemap\Provider\CategoryUrlProvider;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Test\Seo\FrontendChannelTestHelper;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Frontend\Framework\Seo\SeoUrlRoute\BlogPageSeoUrlRoute;

/**
 * @internal
 */
class CategoryUrlProviderTest extends TestCase
{
    use FrontendChannelTestHelper;
    use IntegrationTestBehaviour;

    private ChannelContext $channelContext;

    protected function setUp(): void
    {
        if (!static::getContainer()->has(BlogPageSeoUrlRoute::class)) {
            static::markTestSkipped('NEXT-16799: Sitemap module has a dependency on frontend routes');
        }

        $navigationCategoryId = $this->createRootCategoryData();

        $this->channelContext = $this->createFrontendChannelContext(
            Uuid::randomHex(),
            'test-category-sitemap',
            Defaults::LANGUAGE_SYSTEM,
            [],
            $navigationCategoryId
        );

        $this->createCategoryTree($navigationCategoryId);
    }

    public function testCategoryUrlObjectContainsValidContent(): void
    {
        $urlResult = $this->getCategoryUrlProvider()->getUrls($this->channelContext, 5);
        [$firstUrl] = $urlResult->getUrls();

        static::assertSame('daily', $firstUrl->getChangefreq());
        static::assertSame(0.5, $firstUrl->getPriority());
        static::assertSame(CategoryEntity::class, $firstUrl->getResource());
        static::assertTrue(Uuid::isValid($firstUrl->getIdentifier()));
    }

    public function testReturnedOffsetIsValid(): void
    {
        $categoryUrlProvider = $this->getCategoryUrlProvider();

        // first run
        $urlResult = $categoryUrlProvider->getUrls($this->channelContext, 3);
        static::assertIsNumeric($urlResult->getNextOffset());

        // 1+n run
        $urlResult = $categoryUrlProvider->getUrls($this->channelContext, 2, $urlResult->getNextOffset());
        static::assertIsNumeric($urlResult->getNextOffset());

        // last run
        $urlResult = $categoryUrlProvider->getUrls($this->channelContext, 100, $urlResult->getNextOffset()); // test with high number to get last chunk
        static::assertNull($urlResult->getNextOffset());
    }

    public function testExcludeCategoryLinkAndFolder(): void
    {
        $urlResult = $this->getCategoryUrlProvider()->getUrls($this->channelContext, 10);
        $ids = array_map(static fn ($url) => $url->getIdentifier(), $urlResult->getUrls());

        // link
        static::assertNotContains('0191233394c57345a56e1b4df4db81c3', $ids);

        // folder
        static::assertNotContains('0191233394c57345a56e1b4df521dca6', $ids);
    }

    private function getCategoryUrlProvider(): CategoryUrlProvider
    {
        return $this->getContainer()->get(CategoryUrlProvider::class);
    }

    private function createRootCategoryData(): string
    {
        $id = Uuid::randomHex();
        $categories = [
            [
                'id' => $id,
                'name' => 'Main',
            ],
        ];

        static::getContainer()->get('category.repository')->create($categories, Context::createDefaultContext());

        return $id;
    }

    private function createCategoryTree(string $rootId): void
    {
        static::getContainer()->get('category.repository')->upsert([
            [
                'id' => $rootId,
                'children' => [
                    [
                        'name' => 'Sub 1',
                        'active' => true,
                    ],
                    [
                        'name' => 'Sub 2',
                        'active' => true,
                    ],
                    [
                        'name' => 'Sub 3',
                        'active' => true,
                    ],
                    [
                        'name' => 'Sub 4',
                        'active' => true,
                    ],
                    [
                        'id' => '0191233394c57345a56e1b4df4db81c3',
                        'name' => 'Sub 5',
                        'active' => true,
                        'type' => CategoryDefinition::TYPE_LINK,
                    ],
                    [
                        'id' => '0191233394c57345a56e1b4df521dca6',
                        'name' => 'Sub 6',
                        'active' => true,
                        'type' => CategoryDefinition::TYPE_FOLDER,
                    ],
                ],
            ],
        ], Context::createDefaultContext());
    }
}
