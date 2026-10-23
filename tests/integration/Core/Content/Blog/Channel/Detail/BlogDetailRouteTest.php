<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Blog\Channel\Detail;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\Channel\Detail\BlogDetailRoute;
use Contena\Core\Content\Test\Blog\BlogBuilder;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\ChannelApiTestBehaviour;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Channel\Context\ChannelContextService;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Group('store-api')]
class BlogDetailRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use ChannelApiTestBehaviour;

    private const LANGUAGE_IDS = [
        'en' => Defaults::LANGUAGE_SYSTEM,
        'de' => '20354d7ae4fe47af8ff6187bc0dedede',
    ];

    private KernelBrowser $browser;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        static::getContainer()->get(SystemConfigService::class)
            ->set('core.listing.hideCloseoutBlogsWhenOutOfStock', false);
        static::getContainer()->get(SystemConfigService::class)
            ->set('core.listing.findBestVariant', false);

        $this->browser = $this->createCustomChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);

        $this->createData();
    }

    public function testLoadBlog(): void
    {
        $this->browser->request('POST', $this->getUrl($this->ids->get('blog')));

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame('blog_detail', $response['apiAlias']);
        static::assertArrayHasKey('blog', $response);
    }

    public function testLoadBlogWithMeasurementSystem(): void
    {
        $blog = (new BlogBuilder($this->ids, 'measurement-system-blog'))
            ->price(100)
            ->visibility($this->ids->get('sales-channel'))
            ->length(100.0)
            ->width(50.0)
            ->height(30.0)
            ->weight(1.5)
            ->build();

        static::getContainer()->get('blog.repository')->create([$blog], Context::createDefaultContext());

        $this->browser->request('POST', $this->getUrl($this->ids->get('measurement-system-blog')));

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame('blog_detail', $response['apiAlias']);
        static::assertArrayHasKey('blog', $response);
        static::assertArrayHasKey('length', $response['blog']);
        static::assertArrayHasKey('width', $response['blog']);
        static::assertArrayHasKey('height', $response['blog']);
        static::assertArrayHasKey('weight', $response['blog']);

        // blog dimension stay in the default unit
        static::assertSame(100, $response['blog']['length']);
        static::assertSame(50, $response['blog']['width']);
        static::assertSame(30, $response['blog']['height']);
        static::assertSame(1.5, $response['blog']['weight']);

        // measurements is calculated in the response
        static::assertArrayHasKey('measurements', $response['blog']);
        static::assertSame([
            'width' => [
                'value' => 50,
                'unit' => 'mm',
            ],
            'height' => [
                'value' => 30,
                'unit' => 'mm',
            ],
            'length' => [
                'value' => 100,
                'unit' => 'mm',
            ],
            'weight' => [
                'value' => 1.5,
                'unit' => 'kg',
            ],
            'apiAlias' => 'converted_unit_set',
        ], $response['blog']['measurements']);

        // change the default unit to imperial
        $channelRepository = static::getContainer()->get('channel.repository');
        $channelRepository->update([
            [
                'id' => $this->ids->get('sales-channel'),
                'measurementUnits' => [
                    'system' => 'imperial',
                    'units' => [
                        'length' => 'in',
                        'weight' => 'lb',
                    ],
                ],
            ],
        ], Context::createDefaultContext());

        $this->browser->request('POST', $this->getUrl($this->ids->get('measurement-system-blog')));

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame('blog_detail', $response['apiAlias']);
        static::assertArrayHasKey('blog', $response);
        static::assertArrayHasKey('length', $response['blog']);
        static::assertArrayHasKey('width', $response['blog']);
        static::assertArrayHasKey('height', $response['blog']);
        static::assertArrayHasKey('weight', $response['blog']);

        // blog dimension stay in the default unit
        static::assertSame(100, $response['blog']['length']);
        static::assertSame(50, $response['blog']['width']);
        static::assertSame(30, $response['blog']['height']);
        static::assertSame(1.5, $response['blog']['weight']);

        // measurements is calculated in the response
        static::assertArrayHasKey('measurements', $response['blog']);
        static::assertSame([
            'width' => [
                'value' => 1.97,
                'unit' => 'in',
            ],
            'height' => [
                'value' => 1.18,
                'unit' => 'in',
            ],
            'length' => [
                'value' => 3.94,
                'unit' => 'in',
            ],
            'weight' => [
                'value' => 3.31,
                'unit' => 'lb',
            ],
            'apiAlias' => 'converted_unit_set',
        ], $response['blog']['measurements']);
    }

    public function testLoadBlogVariantShowBestVariant(): void
    {
        $this->createVariantBlogs(['displayParent' => true]);

        $this->browser->request('POST', $this->getUrl($this->ids->get('variants')));

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame('blog_detail', $response['apiAlias']);
        static::assertArrayHasKey('blog', $response);

        $blog = $response['blog'];
        static::assertArrayHasKey('blogNumber', $blog);
        static::assertSame('variant-2', $blog['blogNumber']);
    }

    public function testLoadBlogVariantShowSelectedSingleVariant(): void
    {
        $this->createVariantBlogs([
            'mainVariantId' => $this->ids->get('variant-3'),
            'displayParent' => false,
        ]);

        $this->browser->request('POST', $this->getUrl($this->ids->get('variants')));

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame('blog_detail', $response['apiAlias']);
        static::assertArrayHasKey('blog', $response);

        $blog = $response['blog'];
        static::assertArrayHasKey('blogNumber', $blog);
        static::assertSame('variant-3', $blog['blogNumber']);
    }

    public function testLoadParentSearchUsesMatchedVariantWhenFindBestVariantEnabled(): void
    {
        static::getContainer()->get(SystemConfigService::class)
            ->set('core.listing.findBestVariant', true);

        $this->createVariantBlogs([
            'mainVariantId' => $this->ids->get('variant-2'),
            'displayParent' => false,
        ]);

        $this->browser->request('POST', $this->getUrl($this->ids->get('variants')) . '?search=variant-3');

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_OK, $this->browser->getResponse()->getStatusCode(), print_r($response, true));
        static::assertSame('variant-3', $response['blog']['blogNumber']);
    }

    public function testLoadParentSearchKeepsMainVariantWhenFindBestVariantDisabled(): void
    {
        $this->createVariantBlogs([
            'mainVariantId' => $this->ids->get('variant-2'),
            'displayParent' => false,
        ]);

        $this->browser->request('POST', $this->getUrl($this->ids->get('variants')) . '?search=variant-3');

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_OK, $this->browser->getResponse()->getStatusCode(), print_r($response, true));
        static::assertSame('variant-2', $response['blog']['blogNumber']);
    }

    /**
     * @param array<string, mixed> $variantListingConfig
     */
    #[DataProvider('variantListingConfigProvider')]
    public function testLoadParentReturnsNotFoundWhenAllVariantsAreInactive(array $variantListingConfig): void
    {
        $this->createVariantBlogs($variantListingConfig);

        static::getContainer()->get('blog.repository')->update([
            ['id' => $this->ids->get('variant-1'), 'active' => false],
            ['id' => $this->ids->get('variant-2'), 'active' => false],
            ['id' => $this->ids->get('variant-3'), 'active' => false],
        ], Context::createDefaultContext());

        $this->browser->request('POST', $this->getUrl($this->ids->get('variants')));

        static::assertSame(Response::HTTP_NOT_FOUND, $this->browser->getResponse()->getStatusCode());
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function variantListingConfigProvider(): iterable
    {
        yield 'display parent' => [['displayParent' => true]];
        yield 'without variant listing config' => [[]];
    }

    public function testLoadParentReturnsNotFoundWhenAllVariantsAreHiddenCloseouts(): void
    {
        static::getContainer()->get(SystemConfigService::class)
            ->set('core.listing.hideCloseoutBlogsWhenOutOfStock', true);

        $this->createVariantBlogs(['displayParent' => true]);

        static::getContainer()->get('blog.repository')->update([
            ['id' => $this->ids->get('variant-2'), 'stock' => 0],
            ['id' => $this->ids->get('variant-3'), 'stock' => 0],
        ], Context::createDefaultContext());

        $this->browser->request('POST', $this->getUrl($this->ids->get('variants')));

        static::assertSame(Response::HTTP_NOT_FOUND, $this->browser->getResponse()->getStatusCode());
    }

    public function testLoadParentResolvesRemainingActiveVariant(): void
    {
        $this->createVariantBlogs(['displayParent' => true]);

        static::getContainer()->get('blog.repository')->update([
            ['id' => $this->ids->get('variant-1'), 'active' => false],
            ['id' => $this->ids->get('variant-2'), 'active' => false],
        ], Context::createDefaultContext());

        $this->browser->request('POST', $this->getUrl($this->ids->get('variants')));

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_OK, $this->browser->getResponse()->getStatusCode(), print_r($response, true));
        static::assertSame('variant-3', $response['blog']['blogNumber']);
    }

    public function testIncludes(): void
    {
        $this->browser->request(
            'POST',
            $this->getUrl($this->ids->get('blog')),
            [
                'includes' => [
                    'blog' => ['id', 'name'],
                ],
            ]
        );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame('blog_detail', $response['apiAlias']);
        static::assertArrayHasKey('blog', $response);

        $blog = $response['blog'];
        $properties = array_keys($blog);

        $expected = ['id', 'name', 'apiAlias'];
        sort($expected);
        sort($properties);

        static::assertSame($expected, $properties);
    }

    public function testExtendCriteria(): void
    {
        $this->browser->request(
            'POST',
            $this->getUrl($this->ids->get('blog')),
            [
                'includes' => [
                    'blog' => ['id', 'name', 'manufacturer'],
                ],
                'associations' => [
                    'manufacturer' => [],
                ],
            ]
        );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame('blog_detail', $response['apiAlias']);
        static::assertArrayHasKey('blog', $response);
        static::assertArrayHasKey('manufacturer', $response['blog']);
        static::assertIsArray($response['blog']['manufacturer']);
        static::assertNotCount(0, $response['blog']['manufacturer']);
    }

    public function testIncludeForCustomFields(): void
    {
        $blog = (new BlogBuilder($this->ids, 'custom-fields-blog'))
            ->price(100)
            ->visibility($this->ids->get('sales-channel'))
            ->customField('foo', 'foo')
            ->customField('bar', 'baz')
            ->customField('nested', [
                'foo' => 'foo',
                'bar' => 'baz',
            ])
            ->build();

        static::getContainer()->get('blog.repository')->create([$blog], Context::createDefaultContext());

        $this->browser->request(
            'POST',
            $this->getUrl($this->ids->get('custom-fields-blog')),
            [
                'includes' => [
                    'blog' => ['id', 'customFields'],
                ],
            ]
        );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('blog', $response);
        static::assertArrayHasKey('customFields', $response['blog']);
        static::assertArrayHasKey('foo', $response['blog']['customFields']);
        static::assertArrayHasKey('bar', $response['blog']['customFields']);
        static::assertArrayHasKey('nested', $response['blog']['customFields']);

        $this->browser->request(
            'POST',
            $this->getUrl($this->ids->get('custom-fields-blog')),
            [
                'includes' => [
                    'blog' => ['id', 'customFields.foo'],
                ],
            ]
        );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('blog', $response);
        static::assertArrayHasKey('customFields', $response['blog']);
        static::assertArrayHasKey('foo', $response['blog']['customFields']);
        static::assertArrayNotHasKey('bar', $response['blog']['customFields']);
        static::assertArrayNotHasKey('nested', $response['blog']['customFields']);

        $this->browser->request(
            'POST',
            $this->getUrl($this->ids->get('custom-fields-blog')),
            [
                'includes' => [
                    'blog' => ['id', 'customFields.nested.foo'],
                ],
            ]
        );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('blog', $response);
        static::assertArrayHasKey('customFields', $response['blog']);
        static::assertArrayNotHasKey('foo', $response['blog']['customFields']);
        static::assertArrayNotHasKey('bar', $response['blog']['customFields']);
        static::assertArrayHasKey('nested', $response['blog']['customFields']);
        static::assertArrayHasKey('foo', $response['blog']['customFields']['nested']);
        static::assertArrayNotHasKey('bar', $response['blog']['customFields']['nested']);
    }

    public function testRecursionEncodingWithLayout(): void
    {
        $this->browser->request(
            'POST',
            $this->getUrl($this->ids->get('with-layout')),
            [
                'associations' => [
                    'media' => [
                        'sort' => [['field' => 'position']],
                        'associations' => [
                            'media' => [],
                        ],
                    ],
                    'manufacturer' => [],
                    'crossSellings' => [],
                    'blogReviews' => [],
                ],
            ]
        );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_OK, $this->browser->getResponse()->getStatusCode(), print_r($response, true));

        $expected = str_replace(
            '__stream-1__',
            $this->ids->get('stream-1'),
            (string) file_get_contents(__DIR__ . '/_fixtures/recursion_encoding_with_layout_result.json')
        );

        $expected = json_decode($expected, true, 512, \JSON_THROW_ON_ERROR);

        $this->assertArray($expected, $response);
    }

    public function testLoadBlogCmsSlotConfigFromParentLanguageOverride(): void
    {
        $context = Context::createDefaultContext();
        $this->createLanguages($context);

        $slotId = $this->ids->create('translated-slot');
        static::getContainer()->get('blog.repository')->create([[
            'id' => $this->ids->create('translated-blog'),
            'name' => 'Translated blog',
            'blogNumber' => 'translated-blog',
            'stock' => 10,
            'active' => true,
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false],
            ],
            'tax' => ['name' => 'tax', 'taxRate' => 15],
            'visibilities' => [
                ['channelId' => $this->ids->get('sales-channel'), 'visibility' => BlogVisibilityDefinition::VISIBILITY_ALL],
            ],
            'cmsPage' => [
                'id' => $this->ids->create('translated-blog-cms-page'),
                'type' => 'blog_detail',
                'sections' => [[
                    'id' => $this->ids->create('translated-section'),
                    'type' => 'default',
                    'position' => 0,
                    'blocks' => [[
                        'id' => $this->ids->create('translated-block'),
                        'type' => 'text',
                        'position' => 0,
                        'slots' => [[
                            'id' => $slotId,
                            'type' => 'text',
                            'slot' => 'content',
                            'config' => [
                                'content' => [
                                    'source' => 'static',
                                    'value' => 'layout placeholder',
                                ],
                            ],
                        ]],
                    ]],
                ]],
            ],
            'slotConfig' => [
                $slotId => [
                    'content' => [
                        'source' => 'static',
                        'value' => 'default language override',
                    ],
                ],
            ],
        ]], $context);

        $this->browser = $this->createCustomChannelBrowser([
            'id' => $this->ids->get('sales-channel'),
            'languageId' => self::LANGUAGE_IDS['de'],
            'languages' => [
                ['id' => self::LANGUAGE_IDS['en']],
                ['id' => self::LANGUAGE_IDS['de']],
            ],
            'domains' => [[
                'languageId' => self::LANGUAGE_IDS['de'],
                'currencyId' => Defaults::CURRENCY,
                'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                'url' => 'http://localhost/de-test',
            ]],
        ]);

        $this->browser->request('GET', '/store-api/context');
        $contextResponse = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $channelContext = static::getContainer()->get(ChannelContextFactory::class)->create(
            $contextResponse['token'],
            $this->ids->get('sales-channel'),
            [ChannelContextService::LANGUAGE_ID => self::LANGUAGE_IDS['de']],
        );

        $response = static::getContainer()->get(BlogDetailRoute::class)->load(
            $this->ids->get('translated-blog'),
            new Request(),
            $channelContext,
            new Criteria(),
        );

        $slot = $response->getBlog()
            ->getCmsPage()?->getSections()?->first()?->getBlocks()?->first()?->getSlots()?->first();

        static::assertSame(
            'default language override',
            $slot?->getConfig()['content']['value'] ?? null
        );
    }

    public function testLoadInheritedBlogCmsSlotConfigFromParentBlogLanguageOverride(): void
    {
        $context = Context::createDefaultContext();
        $this->createLanguages($context);

        $slotId = $this->ids->create('translated-parent-slot');
        $parentBlogId = $this->ids->create('translated-parent-blog');
        $variantBlogId = $this->ids->create('translated-variant-blog');

        static::getContainer()->get('blog.repository')->create([[
            'id' => $parentBlogId,
            'name' => 'Translated parent blog',
            'blogNumber' => 'translated-parent-blog',
            'stock' => 10,
            'active' => true,
            'type' => BlogDefinition::TYPE_PHYSICAL,
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false],
            ],
            'tax' => ['name' => 'tax', 'taxRate' => 15],
            'visibilities' => [
                ['channelId' => $this->ids->get('sales-channel'), 'visibility' => BlogVisibilityDefinition::VISIBILITY_ALL],
            ],
            'cmsPage' => [
                'id' => $this->ids->create('translated-parent-blog-cms-page'),
                'type' => 'blog_detail',
                'sections' => [[
                    'id' => $this->ids->create('translated-parent-section'),
                    'type' => 'default',
                    'position' => 0,
                    'blocks' => [[
                        'id' => $this->ids->create('translated-parent-block'),
                        'type' => 'text',
                        'position' => 0,
                        'slots' => [[
                            'id' => $slotId,
                            'type' => 'text',
                            'slot' => 'content',
                            'config' => [
                                'content' => [
                                    'source' => 'static',
                                    'value' => 'layout placeholder',
                                ],
                            ],
                        ]],
                    ]],
                ]],
            ],
            'slotConfig' => [
                $slotId => [
                    'content' => [
                        'source' => 'static',
                        'value' => 'default language override',
                    ],
                ],
            ],
            'children' => [[
                'id' => $variantBlogId,
                'blogNumber' => 'translated-variant-blog',
                'stock' => 10,
                'active' => true,
                'type' => BlogDefinition::TYPE_PHYSICAL,
                'options' => [],
                'price' => [
                    ['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false],
                ],
            ]],
            'configuratorSettings' => [],
        ]], $context);

        $this->browser = $this->createCustomChannelBrowser([
            'id' => $this->ids->get('sales-channel'),
            'languageId' => self::LANGUAGE_IDS['de'],
            'languages' => [
                ['id' => self::LANGUAGE_IDS['en']],
                ['id' => self::LANGUAGE_IDS['de']],
            ],
            'domains' => [[
                'languageId' => self::LANGUAGE_IDS['de'],
                'currencyId' => Defaults::CURRENCY,
                'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                'url' => 'http://localhost/de-test',
            ]],
        ]);

        $this->browser->request('GET', '/store-api/context');
        $contextResponse = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $channelContext = static::getContainer()->get(ChannelContextFactory::class)->create(
            $contextResponse['token'],
            $this->ids->get('sales-channel'),
            [ChannelContextService::LANGUAGE_ID => self::LANGUAGE_IDS['de']],
        );

        $response = static::getContainer()->get(BlogDetailRoute::class)->load(
            $parentBlogId,
            new Request(),
            $channelContext,
            new Criteria(),
        );

        static::assertSame($variantBlogId, $response->getBlog()->getId());

        $slot = $response->getBlog()
            ->getCmsPage()?->getSections()?->first()?->getBlocks()?->first()?->getSlots()?->first();

        static::assertSame(
            'default language override',
            $slot?->getConfig()['content']['value'] ?? null
        );
    }

    /**
     * @param array<string, string> $expected
     * @param array<string, string> $actual
     */
    private function assertArray(array $expected, array $actual, string $pointer = ''): void
    {
        foreach ($expected as $key => $value) {
            $current = \implode('.', \array_filter([$pointer, (string) $key]));

            static::assertArrayHasKey($key, $actual, \sprintf('Missing key %s', $current));

            if (\is_array($value)) {
                static::assertIsArray($actual[$key], \sprintf('Field %s is not an array', $current));

                $this->assertArray($value, $actual[$key], $current);

                continue;
            }

            static::assertSame($value, $actual[$key], \sprintf('Value for key %s not matching', $current));
        }
    }

    private function createData(): void
    {
        $blogs = [
            (new BlogBuilder($this->ids, 'blog'))
                ->price(15)
                ->manufacturer('m1')
                ->visibility($this->ids->get('sales-channel'))
                ->build(),

            // regression test for: NEXT-17603
            (new BlogBuilder($this->ids, 'with-layout'))
                ->price(100)
                ->media('m1', 1)
                ->media('m2', 2)
                ->media('m3', 3)
                ->review('Test', 'test')
                ->manufacturer('m1')
                ->crossSelling('selling', 'stream-1')
                ->visibility($this->ids->get('sales-channel'))
                ->layout('l1')
                ->build(),
        ];

        static::getContainer()->get('blog.repository')
            ->create($blogs, Context::createDefaultContext());
    }

    private function createLanguages(Context $context): void
    {
        static::getContainer()->get('language.repository')->create([[
            'id' => self::LANGUAGE_IDS['de'],
            'name' => 'TestGerman',
            'parentId' => self::LANGUAGE_IDS['en'],
            'active' => true,
            'locale' => [
                'id' => $this->ids->create('locale-de'),
                'name' => 'TestGerman',
                'territory' => 'TestGermany',
                'code' => 'de-DE-test',
            ],
            'translationCodeId' => $this->ids->get('locale-de'),
        ]], $context);
    }

    /**
     * @param array<mixed> $variantListingConfig
     */
    private function createVariantBlogs(array $variantListingConfig): void
    {
        $blogs = [
            (new BlogBuilder($this->ids, 'variants'))
                ->price(10)
                ->media('m1', 1)
                ->visibility($this->ids->get('sales-channel'))
                ->closeout(true)
                ->stock(10)
                ->variant(
                    (new BlogBuilder($this->ids, 'variant-1'))
                        ->price(5)
                        ->visibility($this->ids->get('sales-channel'))
                        ->closeout(true)
                        ->stock(0)
                        ->build()
                )
                ->variant(
                    (new BlogBuilder($this->ids, 'variant-2'))
                        ->price(15)
                        ->visibility($this->ids->get('sales-channel'))
                        ->closeout(true)
                        ->stock(10)
                        ->build()
                )
                ->variant(
                    (new BlogBuilder($this->ids, 'variant-3'))
                        ->price(40)
                        ->visibility($this->ids->get('sales-channel'))
                        ->closeout(true)
                        ->stock(10)
                        ->build()
                )
                ->variantListingConfig($variantListingConfig)
                ->build(),
        ];

        static::getContainer()->get('blog.repository')
            ->create($blogs, Context::createDefaultContext());
    }

    private function getUrl(string $id): string
    {
        return '/store-api/blog/' . $id;
    }
}
