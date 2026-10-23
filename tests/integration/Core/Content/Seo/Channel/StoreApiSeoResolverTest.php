<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Seo\Channel;

use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Content\Seo\SeoUrlRoute\CategoryStoreApiUrlRoute;
use Contena\Core\Content\Test\TestNavigationSeoUrlRoute;
use Contena\Core\Content\Test\TestBlogSeoUrlRoute;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\ChannelApiTestBehaviour;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @internal
 */
class StoreApiSeoResolverTest extends TestCase
{
    use IntegrationTestBehaviour;
    use ChannelApiTestBehaviour;

    private KernelBrowser $browser;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $this->createData();

        $this->browser = $this->createCustomChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
            'navigationCategoryId' => $this->ids->get('category'),
        ]);

        $this->setVisibilities();
    }

    public function testDisabledState(): void
    {
        $this->browser->request(
            'POST',
            '/store-api/category/home',
            [
            ]
        );

        $content = $this->browser->getResponse()->getContent();
        static::assertIsString($content);

        $response = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        static::assertNull($response['seoUrls']);
        static::assertNull($response['cmsPage']['sections'][0]['blocks'][0]['slots'][0]['data']['listing']['elements'][0]['seoUrls']);
    }

    public function testEnabled(): void
    {
        $this->browser->setServerParameter('HTTP_sw-include-seo-urls', '1');

        $this->browser->request(
            'POST',
            '/store-api/category/home',
            [],
            [],
            []
        );

        $content = $this->browser->getResponse()->getContent();
        static::assertIsString($content);

        $response = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('seoUrls', $response);
        static::assertCount(1, $response['seoUrls']);
        static::assertSame(TestNavigationSeoUrlRoute::ROUTE_NAME, $response['seoUrls'][0]['routeName']);
        static::assertSame($this->ids->get('category'), $response['seoUrls'][0]['foreignKey']);
        static::assertSame('foo', $response['seoUrls'][0]['pathInfo']);
    }

    public function testEnabledChannelBlogs(): void
    {
        $this->browser->setServerParameter('HTTP_sw-include-seo-urls', '1');

        $this->browser->request(
            'POST',
            '/store-api/category/home',
            [],
            [],
            []
        );

        $content = $this->browser->getResponse()->getContent();
        static::assertIsString($content);

        $response = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('seoUrls', $response);
        static::assertCount(1, $response['seoUrls']);
        static::assertSame(TestNavigationSeoUrlRoute::ROUTE_NAME, $response['seoUrls'][0]['routeName']);
        static::assertSame($this->ids->get('category'), $response['seoUrls'][0]['foreignKey']);
        static::assertSame('foo', $response['seoUrls'][0]['pathInfo']);

        static::assertIsArray($response['cmsPage']['sections'][0]['blocks'][0]['slots'][0]['data']['listing']['elements'][0]['seoUrls']);
    }

    public function testEnabledHeadlessChannel(): void
    {
        $browser = $this->createCustomChannelBrowser([
            'id' => $this->ids->create('headless-sales-channel'),
            'typeId' => Defaults::CHANNEL_TYPE_API,
            'navigationCategoryId' => $this->ids->get('category'),
        ]);

        $browser->setServerParameter('HTTP_sw-include-seo-urls', '1');

        $browser->request('POST', '/store-api/category/home');

        $content = $browser->getResponse()->getContent();
        static::assertIsString($content);

        $response = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('seoUrls', $response);
        static::assertIsArray($response['seoUrls']);

        $routeNames = array_column($response['seoUrls'], 'routeName');

        static::assertContains(CategoryStoreApiUrlRoute::ROUTE_NAME, $routeNames);
        // frontend route names stay in the filter as a fallback, so their rows are returned as well
        static::assertContains(TestNavigationSeoUrlRoute::ROUTE_NAME, $routeNames);

        foreach ($response['seoUrls'] as $seoUrl) {
            static::assertSame($this->ids->get('category'), $seoUrl['foreignKey']);
        }
    }

    public function testEnabledNoAuthentication(): void
    {
        $this->browser->setServerParameter('HTTP_sw-include-seo-urls', '1');

        $this->browser->request('GET', '/store-api/test/store-api-seo-resolver/no-auth-required', ['sales-channel-id' => $this->ids->get('sales-channel')]);

        $content = $this->browser->getResponse()->getContent();
        static::assertIsString($content);

        $response = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('seoUrls', $response);
        static::assertNull($response['seoUrls']);

        static::assertNull($response['cmsPage']['sections'][0]['blocks'][0]['slots'][0]['data']['listing']['elements'][0]['seoUrls']);
    }

    private function createData(): void
    {
        $blog = [
            'name' => 'test',
            'stock' => 10,
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false],
            ],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'active' => true,
            'seoUrls' => [
                [
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'routeName' => TestBlogSeoUrlRoute::ROUTE_NAME,
                    'pathInfo' => 'foo',
                    'seoPathInfo' => 'foo',
                    'isCanonical' => true,
                ],
            ],
        ];

        $blogs = [];
        for ($i = 0; $i < 5; ++$i) {
            $blogs[] = array_merge(
                [
                    'id' => $this->ids->create('blog' . $i),
                    'manufacturer' => ['id' => $this->ids->create('manufacturer-' . $i), 'name' => 'test-' . $i],
                    'blogNumber' => $this->ids->get('blog' . $i),
                ],
                $blog
            );
        }

        $data = [
            'id' => $this->ids->create('category'),
            'name' => 'Test',
            'cmsPage' => [
                'id' => $this->ids->create('cms-page'),
                'type' => 'blog_list',
                'sections' => [
                    [
                        'position' => 0,
                        'type' => 'sidebar',
                        'blocks' => [
                            [
                                'type' => 'blog-listing',
                                'position' => 1,
                                'slots' => [
                                    ['type' => 'blog-listing', 'slot' => 'content'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'blogs' => $blogs,
            'seoUrls' => [
                [
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'routeName' => TestNavigationSeoUrlRoute::ROUTE_NAME,
                    'pathInfo' => 'foo',
                    'seoPathInfo' => 'foo',
                    'isCanonical' => true,
                ],
                [
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'routeName' => CategoryStoreApiUrlRoute::ROUTE_NAME,
                    'pathInfo' => 'headless-foo',
                    'seoPathInfo' => 'headless-foo',
                    'isCanonical' => true,
                ],
            ],
        ];

        static::getContainer()->get('category.repository')
            ->create([$data], Context::createDefaultContext());
    }

    private function setVisibilities(): void
    {
        $blogs = [];
        for ($i = 0; $i < 5; ++$i) {
            $blogs[] = [
                'id' => $this->ids->get('blog' . $i),
                'visibilities' => [
                    ['channelId' => $this->ids->get('sales-channel'), 'visibility' => BlogVisibilityDefinition::VISIBILITY_ALL],
                ],
            ];
        }

        static::getContainer()->get('blog.repository')
            ->update($blogs, Context::createDefaultContext());
    }
}
