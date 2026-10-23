<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Controller;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\ChannelRequest;
use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Content\Blog\BlogException;
use Contena\Core\Content\Blog\Channel\Review\BlogReviewsWidgetLoadedHook;
use Contena\Core\Content\Blog\Exception\BlogNotFoundException;
use Contena\Core\Content\Test\Blog\BlogBuilder;
use Contena\Core\Defaults;
use Contena\Core\DevOps\Environment\EnvironmentHelper;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Script\Debugging\ScriptTraces;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\DeliveryTime\DeliveryTimeEntity;
use Contena\Core\System\User\UserEntity;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Test\TestDefaults;
use Contena\Frontend\Controller\BlogController;
use Contena\Frontend\Framework\Routing\RequestTransformer;
use Contena\Frontend\Page\Blog\QuickView\BlogQuickViewWidgetLoadedHook;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class BlogControllerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use ChannelApiTestBehaviour;
    use FrontendControllerTestBehaviour;

    private const TEST_CONTENT = 'Test review content foo bar test';

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $this->createChannel([
            'id' => $this->ids->create('sales-channel'),
            'domains' => [
                [
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'url' => 'https://test.to',
                ],
            ],
        ]);
    }

    public function testForwardFromSaveReviewToLoadReviews(): void
    {
        $blogId = $this->createBlog();

        $this->login();

        $response = $this->request(
            'POST',
            '/blog/' . $blogId . '/rating',
            $this->tokenize('frontend.detail.review.save', [
                'forwardTo' => 'frontend.blog.reviews',
                'points' => 5,
                'title' => 'Test',
                'content' => self::TEST_CONTENT,
            ])
        );

        $this->checkStatusCode($response);
    }

    public function testSwitchOptionsToLoadOptionDefault(): void
    {
        $blogId = $this->createBlog();

        $response = $this->request(
            'GET',
            '/detail/' . $blogId . '/switch',
            $this->tokenize('frontend.detail.switch', [
                'blogId' => $blogId,
            ])
        );

        $responseContent = (string) $response->getContent();
        $content = json_decode($responseContent, true, 512, \JSON_THROW_ON_ERROR);

        $this->checkStatusCode($response);
        static::assertInstanceOf(JsonResponse::class, $response);
        static::assertSame($blogId, $content['blogId']);
        static::assertStringContainsString($blogId, $content['url']);
    }

    public function testSwitchDoesNotCrashOnMalformedOptions(): void
    {
        $blogId = $this->createBlog();

        $response = $this->request(
            'GET',
            '/detail/' . $blogId . '/switch',
            $this->tokenize('frontend.detail.switch', [
                'blogId' => $blogId,
                'options' => 'notJson',
            ])
        );

        $this->checkStatusCode($response);
    }

    #[DataProvider('variantProvider')]
    public function testVariantGrayedOut(
        string $requestVariant,
        bool $blue,
        bool $green,
        bool $red,
        bool $l,
        bool $xl,
        bool $shouldThrowException = false
    ): void {
        $blogs = (new BlogBuilder($this->ids, 'a.0'))
            ->manufacturer('m1')
            ->name('test')
            ->price(10)
            ->visibility()
            ->configuratorSetting('red', 'color')
            ->configuratorSetting('green', 'color')
            ->configuratorSetting('blue', 'color')
            ->configuratorSetting('l', 'size')
            ->configuratorSetting('xl', 'size')
            ->configuratorSetting('m', 'size')
            ->stock(10)
            ->closeout()
            ->variant(
                (new BlogBuilder($this->ids, 'a.1'))
                    ->option('red', 'color')
                    ->option('xl', 'size')
                    ->stock(0)
                    ->closeout(false)
                    ->build()
            )
            ->variant(
                (new BlogBuilder($this->ids, 'a.2'))
                    ->option('green', 'color')
                    ->option('xl', 'size')
                    ->stock(0)
                    ->closeout(null) // inherited
                    ->build()
            )
            ->variant(
                (new BlogBuilder($this->ids, 'a.3'))
                    ->option('red', 'color')
                    ->option('l', 'size')
                    ->stock(10)
                    ->closeout(null) // inherited
                    ->build()
            )
            ->variant(
                (new BlogBuilder($this->ids, 'a.4'))
                    ->option('green', 'color')
                    ->option('l', 'size')
                    ->stock(10)
                    ->closeout(false)
                    ->build()
            )
            ->variant(
                (new BlogBuilder($this->ids, 'a.5'))
                    ->option('blue', 'color')
                    ->option('xl', 'size')
                    ->visibility()
                    ->visibility($this->ids->get('sales-channel'))
                    ->stock(10)
                    ->closeout(null) // inherited
                    ->build()
            )
            ->variant(
                (new BlogBuilder($this->ids, 'a.6'))
                    ->option('blue', 'color')
                    ->option('l', 'size')
                    ->visibility($this->ids->get('sales-channel'))
                    ->stock(10)
                    ->closeout(null) // inherited
                    ->build()
            )
            ->variant(
                (new BlogBuilder($this->ids, 'a.7'))
                    ->option('red', 'color')
                    ->option('m', 'size')
                    ->visibility($this->ids->get('sales-channel'))
                    ->stock(10)
                    ->closeout(null) // inherited
                    ->build()
            )
            ->variant(
                (new BlogBuilder($this->ids, 'a.8'))
                    ->option('green', 'color')
                    ->option('m', 'size')
                    ->visibility($this->ids->get('sales-channel'))
                    ->stock(0)
                    ->closeout(null) // inherited
                    ->build()
            )
            ->variant(
                (new BlogBuilder($this->ids, 'a.9'))
                    ->option('blue', 'color')
                    ->option('m', 'size')
                    ->visibility($this->ids->get('sales-channel'))
                    ->stock(0)
                    ->closeout(false)
                    ->build()
            )
            ->build();

        static::getContainer()->get('blog.repository')->create([$blogs], Context::createDefaultContext());

        $context = static::getContainer()->get(ChannelContextFactory::class)->create(Uuid::randomHex(), TestDefaults::CHANNEL);
        $controller = static::getContainer()->get(BlogController::class);

        if ($shouldThrowException) {
            if (!Feature::isActive('v6.8.0.0')) {
                $this->expectException(BlogNotFoundException::class);
            } else {
                $this->expectException(BlogException::class);
            }
        }

        $response = $controller->index($context, $this->createDetailRequest($context, $this->ids->get($requestVariant)));

        $this->checkStatusCode($response);

        $crawler = new Crawler();
        $crawler->addHtmlContent((string) $response->getContent());

        $blueFound = false;
        $greenFound = false;
        $redFound = false;
        $xlFound = false;
        $lFound = false;
        $mFound = false;

        $crawler->filter('.blog-detail-configurator .blog-detail-configurator-option-label')
            ->each(static function (Crawler $option) use ($blue, $green, $red, $xl, $l, &$blueFound, &$greenFound, &$redFound, &$xlFound, &$lFound, &$mFound): void {
                if ($option->innerText() === 'blue') {
                    static::assertSame($blue, $option->matches('.is-combinable'));
                    $blueFound = true;
                }

                if ($option->innerText() === 'green') {
                    static::assertSame($green, $option->matches('.is-combinable'));
                    $greenFound = true;
                }

                if ($option->innerText() === 'red') {
                    static::assertSame($red, $option->matches('.is-combinable'));
                    $redFound = true;
                }

                if ($option->innerText() === 'xl') {
                    static::assertSame($xl, $option->matches('.is-combinable'));
                    $xlFound = true;
                }

                if ($option->innerText() === 'l') {
                    static::assertSame($l, $option->matches('.is-combinable'));
                    $lFound = true;
                }

                if ($option->innerText() === 'm') {
                    $mFound = true;
                }
            });

        static::assertTrue($blueFound, 'Option blue was not found.');
        static::assertTrue($greenFound, 'Option green was not found.');
        static::assertTrue($redFound, 'Option red was not found.');
        static::assertTrue($xlFound, 'Option xl was not found.');
        static::assertTrue($lFound, 'Option l was not found.');
        static::assertFalse($mFound, 'Option m was found.');
    }

    /**
     * @return iterable<string, array<int, string|bool>>
     */
    public static function variantProvider(): iterable
    {
        yield 'test color: red - size: xl' => ['a.1', true, false, true, true, true]; // a.1 all options should be normal
        yield 'test color: green - size: xl' => ['a.2', true, false, true, true, false]; // a.2 green and xl should be gray
        yield 'test color: red - size: l' => ['a.3', false, true, true, true, true]; // a.3 all options should be normal except blue
        yield 'test color: green - size: l' => ['a.4', false, true, true, true, false]; // a.4 xl and blue should be gray
        yield 'test color: blue - size: xl' => ['a.5', true, false, true, false, true]; // a.5 l, green should be gray
        yield 'test color: blue - size: l' => ['a.6', false, false, false, false, false, true]; // a.6 xl should throw exception
        yield 'test color: red - size: m' => ['a.7', false, false, false, false, false, true]; // a.7 m should throw exception
        yield 'test color: green - size: m' => ['a.8', false, false, false, false, false, true]; // a.8 m should throw exception
        yield 'test color: blue - size: m' => ['a.9', false, false, false, false, false, true]; // a.9 m should throw exception
    }

    public function testBlogPageLoadedScriptsAreExecuted(): void
    {
        $blogId = $this->createBlog();

        $response = $this->request(
            'GET',
            '/my-blog/' . $blogId,
            []
        );

        $this->checkStatusCode($response);

        $traces = $this->getFrontendRequestContainer()->get(ScriptTraces::class)->getTraces();

        static::assertArrayHasKey('blog-page-loaded', $traces);
    }

    public function testBuyWidgetIncludesCategoryNames(): void
    {
        $channel = static::getContainer()->get('channel.repository')
            ->search(new Criteria([$this->getChannelId()]), Context::createDefaultContext())
            ->getEntities()
            ->first();
        static::assertInstanceOf(ChannelEntity::class, $channel);

        $categoryId = Uuid::randomHex();
        static::getContainer()->get('category.repository')->create([[
            'id' => $categoryId,
            'name' => 'Dresses',
            'parentId' => $channel->getNavigationCategoryId(),
        ]], Context::createDefaultContext());

        $blogId = $this->createBlog([
            'categories' => [['id' => $categoryId]],
            'mainCategories' => [[
                'id' => Uuid::randomHex(),
                'categoryId' => $categoryId,
                'channelId' => $this->getChannelId(),
            ]],
        ]);

        $response = $this->request('GET', '/my-blog/' . $blogId, []);
        $this->checkStatusCode($response);

        $crawler = new Crawler((string) $response->getContent());
        $categoryName = $crawler->filter(\sprintf('input[name="lineItems[%s][payload][categoryNames][%s]"]', $blogId, $categoryId));

        static::assertCount(1, $categoryName);
        static::assertSame('Dresses', $categoryName->attr('value'));
    }

    public function testBlogManufacturerRelativeLinkIsNotNormalizedAsExternalUrl(): void
    {
        $blogId = $this->createBlog(['manufacturer' => ['name' => 'linked-manufacturer', 'link' => '/manufacturer-test/']]);

        $response = $this->request('GET', '/my-blog/' . $blogId, []);

        $this->checkStatusCode($response);

        $crawler = new Crawler((string) $response->getContent());

        $manufacturerLink = $crawler->filter('a.blog-detail-manufacturer-link');
        static::assertCount(1, $manufacturerLink);
        static::assertSame('/manufacturer-test/', $manufacturerLink->attr('href'));
    }

    public function testBlogJsonLdContainsMerchantListingData(): void
    {
        Feature::skipTestIfInActive('JSON_LD_DATA', $this);

        $channel = static::getContainer()->get('channel.repository')
            ->search(new Criteria([$this->getChannelId()]), Context::createDefaultContext())
            ->getEntities()
            ->first();
        static::assertInstanceOf(ChannelEntity::class, $channel);

        $parentCategoryId = Uuid::randomHex();
        $categoryId = Uuid::randomHex();

        static::getContainer()->get('category.repository')->create([
            [
                'id' => $parentCategoryId,
                'name' => 'Women',
                'parentId' => $channel->getNavigationCategoryId(),
            ],
            [
                'id' => $categoryId,
                'name' => 'Dresses',
                'parentId' => $parentCategoryId,
            ],
        ], Context::createDefaultContext());

        $blogId = $this->createBlog([
            'ean' => '00123456',
            'categories' => [['id' => $categoryId]],
            'mainCategories' => [[
                'id' => Uuid::randomHex(),
                'categoryId' => $categoryId,
                'channelId' => $this->getChannelId(),
            ]],
            'price' => [[
                'currencyId' => Defaults::CURRENCY,
                'gross' => 10,
                'net' => 9,
                'listPrice' => ['gross' => 15, 'net' => 13.5, 'linked' => false],
                'linked' => false,
            ]],
        ]);

        $response = $this->request('GET', '/my-blog/' . $blogId, []);
        $this->checkStatusCode($response);

        $crawler = new Crawler((string) $response->getContent());
        $blogJsonLd = null;

        foreach ($crawler->filter('script[type="application/ld+json"]') as $script) {
            $data = \json_decode($script->textContent, true, 512, \JSON_THROW_ON_ERROR);
            if (($data['@type'] ?? null) === 'Blog') {
                $blogJsonLd = $data;
                break;
            }
        }

        static::assertIsArray($blogJsonLd);
        static::assertSame('00123456', $blogJsonLd['gtin8']);
        static::assertSame(['Women > Dresses'], $blogJsonLd['category']);
        static::assertSame(15.0, $blogJsonLd['offers']['priceSpecification']['price']);
        static::assertSame('https://schema.org/StrikethroughPrice', $blogJsonLd['offers']['priceSpecification']['priceType']);
        static::assertSame('EUR', $blogJsonLd['offers']['priceSpecification']['priceCurrency']);
    }

    #[DataProvider('jsonLdDeliveryTimeProvider')]
    public function testBlogJsonLdConvertsDeliveryTimeToWholeDays(string $unit, int $min, int $max, int $expectedMin, int $expectedMax): void
    {
        Feature::skipTestIfInActive('JSON_LD_DATA', $this);

        $deliveryTimeId = Uuid::randomHex();
        static::getContainer()->get('delivery_time.repository')->create([[
            'id' => $deliveryTimeId,
            'name' => 'Test delivery time',
            'min' => $min,
            'max' => $max,
            'unit' => $unit,
        ]], Context::createDefaultContext());

        $blogId = $this->createBlog(['deliveryTimeId' => $deliveryTimeId]);
        $response = $this->request('GET', '/my-blog/' . $blogId, []);
        $this->checkStatusCode($response);

        $blogJsonLd = $this->extractBlogJsonLd(new Crawler((string) $response->getContent()));
        $handlingTime = $blogJsonLd['offers']['shippingDetails']['deliveryTime']['handlingTime'];

        static::assertSame($expectedMin, $handlingTime['minValue']);
        static::assertSame($expectedMax, $handlingTime['maxValue']);
        static::assertSame('DAY', $handlingTime['unitCode']);
    }

    /**
     * @return iterable<string, array{0: string, 1: int, 2: int, 3: int, 4: int}>
     */
    public static function jsonLdDeliveryTimeProvider(): iterable
    {
        yield 'days remain unchanged' => [DeliveryTimeEntity::DELIVERY_TIME_DAY, 1, 3, 1, 3];
        yield 'weeks are converted to days' => [DeliveryTimeEntity::DELIVERY_TIME_WEEK, 1, 3, 7, 21];
        yield 'months use a thirty day approximation' => [DeliveryTimeEntity::DELIVERY_TIME_MONTH, 1, 2, 30, 60];
        yield 'years use a three hundred sixty-five day approximation' => [DeliveryTimeEntity::DELIVERY_TIME_YEAR, 1, 1, 365, 365];
        yield 'hours are rounded up to whole days' => [DeliveryTimeEntity::DELIVERY_TIME_HOUR, 1, 2, 1, 1];
    }

    public function testBlogJsonLdOmitsShippingDetailsWithoutDeliveryTime(): void
    {
        Feature::skipTestIfInActive('JSON_LD_DATA', $this);

        $blogId = $this->createBlog();
        $response = $this->request('GET', '/my-blog/' . $blogId, []);
        $this->checkStatusCode($response);

        $blogJsonLd = $this->extractBlogJsonLd(new Crawler((string) $response->getContent()));

        static::assertArrayNotHasKey('shippingDetails', $blogJsonLd['offers']);
    }

    public function testBlogPageDepthMicrodataUsesDepthItemProp(): void
    {
        Feature::skipTestIfActive('JSON_LD_DATA', $this);

        $blogId = $this->createBlog(['length' => 12.0]);

        $response = $this->request(
            'GET',
            '/my-blog/' . $blogId,
            []
        );

        $this->checkStatusCode($response);

        $content = $response->getContent();
        static::assertIsString($content);
        static::assertStringContainsString('<meta itemprop="depth"', $content);
        static::assertStringContainsString('content="12 mm"', $content);
        static::assertStringNotContainsString('itemprop="length"', $content);
    }

    public function testSeparateBlogGalleryCmsElementRendersImageMicrodata(): void
    {
        Feature::skipTestIfActive('JSON_LD_DATA', $this);

        $cmsPageId = Uuid::randomHex();

        static::getContainer()->get('cms_page.repository')->create([
            [
                'id' => $cmsPageId,
                'type' => 'blog_detail',
                'sections' => [
                    [
                        'id' => Uuid::randomHex(),
                        'type' => 'default',
                        'position' => 0,
                        'blocks' => [
                            [
                                'id' => Uuid::randomHex(),
                                'type' => 'image-gallery',
                                'position' => 0,
                                'sectionPosition' => 'main',
                                'slots' => [
                                    [
                                        'id' => Uuid::randomHex(),
                                        'type' => 'image-gallery',
                                        'slot' => 'imageGallery',
                                        'config' => [
                                            'sliderItems' => ['source' => 'mapped', 'value' => 'blog.media'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], Context::createDefaultContext());

        $blogId = $this->createBlog([
            'cmsPageId' => $cmsPageId,
            'media' => [
                [
                    'id' => Uuid::randomHex(),
                    'position' => 0,
                    'media' => [
                        'fileName' => 'gallery-image',
                    ],
                ],
            ],
        ]);

        $response = $this->request(
            'GET',
            '/my-blog/' . $blogId,
            []
        );

        $this->checkStatusCode($response);

        $content = $response->getContent();
        static::assertIsString($content);

        $crawler = new Crawler();
        $crawler->addHtmlContent($content);

        static::assertCount(1, $crawler->filter('img.gallery-slider-image[itemprop="image"]'));
    }

    public function testReferencePriceIsRenderedWithSingleCalculatedPrice(): void
    {
        $unitId = Uuid::randomHex();
        $ruleId = Uuid::randomHex();
        $blogId = $this->createBlog([
            'unitId' => $unitId,
            'unit' => [
                'id' => $unitId,
                'shortCode' => 'ml',
                'name' => 'Milliliter',
            ],
            'purchaseUnit' => 500.0,
            'referenceUnit' => 1000.0,
            'prices' => [
                [
                    'quantityStart' => 1,
                    'rule' => [
                        'id' => $ruleId,
                        'priority' => 1,
                        'name' => 'Reference price rule',
                        'conditions' => [
                            [
                                'type' => 'orContainer',
                                'position' => 0,
                                'children' => [
                                    [
                                        'type' => 'andContainer',
                                        'position' => 0,
                                        'children' => [
                                            ['type' => 'alwaysValid', 'position' => 0],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'price' => [
                        ['currencyId' => Defaults::CURRENCY, 'gross' => 4.0, 'net' => 3.36, 'linked' => false],
                    ],
                ],
            ],
        ]);

        $response = $this->request(
            'GET',
            '/my-blog/' . $blogId,
            []
        );

        $this->checkStatusCode($response);

        $crawler = new Crawler();
        $crawler->addHtmlContent((string) $response->getContent());

        $referencePrice = $crawler->filter('.price-unit-reference-content');

        static::assertCount(1, $referencePrice);
        static::assertStringContainsString('/ 1000 Milliliter', $referencePrice->text());
    }

    public function testBlogQuickViewWidgetLoadedHookScriptsAreExecuted(): void
    {
        $blogId = $this->createBlog();

        $response = $this->request(
            'GET',
            '/quickview/' . $blogId,
            []
        );

        $this->checkStatusCode($response);

        $traces = $this->getFrontendRequestContainer()->get(ScriptTraces::class)->getTraces();

        static::assertArrayHasKey(BlogQuickViewWidgetLoadedHook::HOOK_NAME, $traces);
    }

    public function testBlogQuickViewManufacturerIsNotLinkedWithoutUrl(): void
    {
        $blogId = $this->createBlog(['manufacturer' => ['name' => 'no-link-manufacturer']]);

        $response = $this->request('GET', '/quickview/' . $blogId, []);

        $this->checkStatusCode($response);

        $crawler = new Crawler();
        $crawler->addHtmlContent((string) $response->getContent());

        $manufacturerLink = $crawler->filter('a.quickview-minimal-blog-manufacturer');
        static::assertCount(0, $manufacturerLink);

        $manufacturer = $crawler->filter('span.quickview-minimal-blog-manufacturer');
        static::assertCount(1, $manufacturer);
        static::assertStringContainsString('no-link-manufacturer', $manufacturer->text());
    }

    #[DataProvider('manufacturerLinkProvider')]
    public function testBlogQuickViewManufacturerIsLinkedWithUrl(string $manufacturerUrl, string $expectedUrl): void
    {
        $blogId = $this->createBlog(['manufacturer' => ['name' => 'linked-manufacturer', 'link' => $manufacturerUrl]]);

        $response = $this->request('GET', '/quickview/' . $blogId, []);

        $this->checkStatusCode($response);

        $crawler = new Crawler();
        $crawler->addHtmlContent((string) $response->getContent());

        $manufacturerLink = $crawler->filter('a.quickview-minimal-blog-manufacturer');
        static::assertCount(1, $manufacturerLink);
        static::assertSame($expectedUrl, $manufacturerLink->attr('href'));
        static::assertStringContainsString('linked-manufacturer', $manufacturerLink->text());

        static::assertCount(0, $crawler->filter('span.quickview-minimal-blog-manufacturer'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function manufacturerLinkProvider(): iterable
    {
        yield 'host without scheme gets https scheme' => ['contena.cn', 'https://contena.cn'];
        yield 'absolute internal path stays relative' => ['/manufacturer-test/', '/manufacturer-test/'];
    }

    public function testBlogReviewsLoadedScriptsAreExecuted(): void
    {
        $blogId = $this->createBlog();

        $response = $this->request(
            'GET',
            '/blog/' . $blogId . '/reviews',
            []
        );

        $this->checkStatusCode($response);

        $traces = $this->getFrontendRequestContainer()->get(ScriptTraces::class)->getTraces();

        static::assertArrayHasKey(BlogReviewsWidgetLoadedHook::HOOK_NAME, $traces);

        $content = $response->getContent();
        static::assertIsString($content);

        if (Feature::isActive('JSON_LD_DATA')) {
            static::assertStringContainsString('class="blog-detail-review-item-content"', $content);
        } else {
            static::assertStringContainsString('class="blog-detail-review-item-content"', $content);
            static::assertStringContainsString('itemprop="description"', $content);
        }

        static::assertStringContainsString(self::TEST_CONTENT, $content);
    }

    private function createDetailRequest(ChannelContext $context, string $blogId): Request
    {
        $request = Request::create((string) EnvironmentHelper::getVariable('APP_URL'));
        $request->attributes->add([
            RequestTransformer::FRONTEND_URL => $_SERVER['APP_URL'],
            PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT => $context,
            PlatformRequest::ATTRIBUTE_CHANNEL_ID => $context->getChannelId(),
            'blogId' => $blogId,
            ChannelRequest::ATTRIBUTE_IS_CHANNEL_REQUEST => true,
        ]);

        static::getContainer()->get('request_stack')->push($request);

        return $request;
    }

    /**
     * @return array<string, mixed>
     */
    private function extractBlogJsonLd(Crawler $crawler): array
    {
        foreach ($crawler->filter('script[type="application/ld+json"]') as $script) {
            $data = \json_decode($script->textContent, true, 512, \JSON_THROW_ON_ERROR);
            if (($data['@type'] ?? null) === 'Blog') {
                static::assertIsArray($data);

                return $data;
            }
        }

        static::fail('Blog JSON-LD was not found.');
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function createBlog(array $overrides = []): string
    {
        $id = Uuid::randomHex();

        $ids = static::getContainer()->get(Connection::class)
            ->fetchFirstColumn('SELECT LOWER(HEX(id)) FROM channel');

        $blog = [
            'id' => $id,
            'blogNumber' => $id,
            'stock' => 5,
            'name' => 'my-blog',
            'isCloseout' => true,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
            'tax' => ['id' => Uuid::randomHex(), 'name' => 'test', 'taxRate' => 19],
            'manufacturer' => ['name' => 'test'],
            'visibilities' => array_map(static fn ($id) => ['channelId' => $id, 'visibility' => BlogVisibilityDefinition::VISIBILITY_ALL], $ids),
            'blogReviews' => [
                [
                    'id' => Uuid::randomHex(),
                    'blogId' => $id,
                    'userId' => $this->createUser()->getId(),
                    'channelId' => $this->getChannelId(),
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'status' => true,
                    'title' => 'Test',
                    'content' => self::TEST_CONTENT,
                    'externalUser' => 'Test',
                    'points' => 5,
                ],
            ],
        ];

        $blog = array_replace_recursive($blog, $overrides);

        $repository = static::getContainer()->get('blog.repository');

        $repository->create([$blog], Context::createDefaultContext());

        return $id;
    }

    private function createUser(): UserEntity
    {
        $userId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $user = [
            'id' => $userId,
            'channelId' => TestDefaults::CHANNEL,
            'defaultShippingAddress' => [
                'id' => $addressId,
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'street' => 'Musterstraße 1',
                'city' => 'Schöppingen',
                'zipcode' => '12345',
                'salutationId' => $this->getValidSalutationId(),
                'countryId' => $this->getValidCountryId(),
            ],
            'defaultBillingAddressId' => $addressId,
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'email' => $userId . '@example.com',
            'password' => TestDefaults::HASHED_PASSWORD,
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'salutationId' => $this->getValidSalutationId(),
            'userNumber' => $userId,
        ];

        $repo = static::getContainer()->get('user.repository');

        $repo->create([$user], Context::createDefaultContext());

        $entity = $repo->search(new Criteria([$userId]), Context::createDefaultContext())->getEntities()->first();

        static::assertInstanceOf(UserEntity::class, $entity);

        return $entity;
    }

    private function login(): KernelBrowser
    {
        $user = $this->createUser();

        $browser = KernelLifecycleManager::createBrowser(static::getKernel());
        $browser->request(
            'POST',
            $_SERVER['APP_URL'] . '/account/login',
            $this->tokenize('frontend.account.login', [
                'username' => $user->getEmail(),
                'password' => 'contena',
            ])
        );
        $response = $browser->getResponse();
        $this->checkStatusCode($response);

        return $browser;
    }

    private function checkStatusCode(Response $response): void
    {
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), print_r($response->getContent(), true));
    }
}
