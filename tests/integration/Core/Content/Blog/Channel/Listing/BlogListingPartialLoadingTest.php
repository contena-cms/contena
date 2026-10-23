<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Blog\Channel\Listing;

use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Media\MediaEntity;
use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Content\Blog\Channel\Listing\BlogListingResult;
use Contena\Core\Content\Blog\Channel\Listing\BlogListingRoute;
use Contena\Core\Content\Blog\Channel\ChannelBlogEntity;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\PartialEntity;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\ChannelApiTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Frontend\Framework\Twig\Extension\UrlEncodingTwigFilter;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class BlogListingPartialLoadingTest extends TestCase
{
    use IntegrationTestBehaviour;
    use ChannelApiTestBehaviour;

    private const CONFIG_KEY = 'core.listing.partialDataLoading';

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
    }

    protected function tearDown(): void
    {
        static::getContainer()->get(SystemConfigService::class)->delete(self::CONFIG_KEY);
    }

    public function testListingExcludesHeavyFieldsButKeepsFullEntity(): void
    {
        $this->createData();
        static::getContainer()->get(SystemConfigService::class)->set(self::CONFIG_KEY, true);

        $blog = $this->loadListing()->getEntities()->get($this->ids->get('blog0'));

        // Reduced loading drops only the heavy columns and keeps a full, typed entity (no PartialEntity).
        static::assertInstanceOf(ChannelBlogEntity::class, $blog);

        $translated = $blog->getTranslated();
        // Excluded heavy columns are not loaded ...
        static::assertNull($translated['description'] ?? null, 'description must not be loaded');
        static::assertNull($translated['keywords'] ?? null, 'keywords must not be loaded');
        // ... but the teaser and everything else (incl. customFields) load as usual.
        $descriptionTeaser = $translated['descriptionTeaser'] ?? null;
        static::assertIsString($descriptionTeaser);
        static::assertNotSame('', $descriptionTeaser);
        static::assertSame(['probe' => 'value'], $translated['customFields'] ?? null, 'customFields must still load');

        // Typed getters work because it stays a real ChannelBlogEntity.
        static::assertCount(1, $blog->getCalculatedPrices(), 'advanced rule prices must survive reduced loading');
        static::assertSame('probe-manufacturer', $blog->getManufacturer()?->getTranslation('name'));
    }

    public function testListingLoadsAllDataByDefault(): void
    {
        $this->createData();

        // No config set: reduced loading is opt-in, so full entities incl. description are loaded.
        $blog = $this->loadListing()->getEntities()->get($this->ids->get('blog0'));
        static::assertInstanceOf(BlogEntity::class, $blog);
        $description = $blog->getTranslation('description');
        static::assertIsString($description);
        static::assertNotSame('', $description);
        $descriptionTeaser = $blog->getTranslation('descriptionTeaser');
        static::assertIsString($descriptionTeaser);
        static::assertNotSame('', $descriptionTeaser);
    }

    public function testListingLoadsAllDataWhenDisabled(): void
    {
        $this->createData();
        static::getContainer()->get(SystemConfigService::class)->set(self::CONFIG_KEY, false);

        $blog = $this->loadListing()->getEntities()->get($this->ids->get('blog0'));
        static::assertInstanceOf(BlogEntity::class, $blog);
        $description = $blog->getTranslation('description');
        static::assertIsString($description);
        static::assertNotSame('', $description);
    }

    public function testExplicitCriteriaFieldsAreNotOverridden(): void
    {
        $this->createData();
        static::getContainer()->get(SystemConfigService::class)->set(self::CONFIG_KEY, true);

        $criteria = new Criteria();
        $criteria->addFields(['id', 'name', 'description']);

        $blog = $this->loadListing($criteria)->getEntities()->get($this->ids->get('blog0'));

        // An explicit allowlist wins; reduced loading is not applied on top (it would conflict with addFields()).
        static::assertInstanceOf(PartialEntity::class, $blog);
        $translated = $blog->get('translated');
        static::assertIsArray($translated);
        $description = $translated['description'] ?? null;
        static::assertIsString($description, 'explicitly requested description must stay loaded');
        static::assertNotSame('', $description, 'explicitly requested description must stay loaded');
    }

    public function testCoverMediaIsLoadedAsFullEntity(): void
    {
        $this->createData();
        static::getContainer()->get(SystemConfigService::class)->set(self::CONFIG_KEY, true);

        static::getContainer()->get('media.repository')->create([[
            'id' => $this->ids->create('media'),
            'fileName' => 'probe-image',
            'fileExtension' => 'png',
            'mimeType' => 'image/png',
            'path' => 'media/probe-image.png',
            'private' => false,
            'alt' => 'Probe alt text',
        ]], Context::createDefaultContext());

        static::getContainer()->get('blog.repository')->update([[
            'id' => $this->ids->get('blog0'),
            'cover' => ['id' => $this->ids->create('blog-media'), 'mediaId' => $this->ids->get('media')],
        ]], Context::createDefaultContext());

        $blog = $this->loadListing()->getEntities()->get($this->ids->get('blog0'));
        static::assertInstanceOf(ChannelBlogEntity::class, $blog);

        $media = $blog->getCover()?->getMedia();
        static::assertInstanceOf(MediaEntity::class, $media);
        static::assertNotSame('', $media->getUrl());

        $encoded = static::getContainer()->get(UrlEncodingTwigFilter::class)->encodeMediaUrl($media);
        static::assertIsString($encoded);
        static::assertStringContainsString('probe-image.png', $encoded);
    }

    private function loadListing(?Criteria $criteria = null): BlogListingResult
    {
        $context = $this->buildChannelContext();

        return static::getContainer()->get(BlogListingRoute::class)
            ->load($this->ids->get('category'), new Request(), $context, $criteria ?? new Criteria())
            ->getResult();
    }

    private function buildChannelContext(): ChannelContext
    {
        $context = static::getContainer()->get(ChannelContextFactory::class)
            ->create(Uuid::randomHex(), $this->ids->get('sales-channel'));
        $context->setRuleIds([$this->ids->get('rule')]);

        return $context;
    }

    private function createData(): void
    {
        static::getContainer()->get('rule.repository')->create([[
            'id' => $this->ids->create('rule'),
            'name' => 'probe-rule',
            'priority' => 1,
            'conditions' => [['type' => 'alwaysValid']],
        ]], Context::createDefaultContext());

        $blogs = [];
        for ($i = 0; $i < 3; ++$i) {
            $blogs[] = [
                'id' => $this->ids->create('blog' . $i),
                'blogNumber' => $this->ids->get('blog' . $i),
                'name' => 'Probe blog ' . $i,
                'description' => '<p style="color: red;">' . str_repeat('Lorem ipsum dolor sit amet. ', 500) . '</p>',
                'keywords' => 'probe keywords',
                'customFields' => ['probe' => 'value'],
                'manufacturer' => ['id' => $this->ids->create('manufacturer'), 'name' => 'probe-manufacturer'],
                'stock' => 10,
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
                'prices' => [
                    [
                        'quantityStart' => 1,
                        'ruleId' => $this->ids->get('rule'),
                        'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 99, 'net' => 90, 'linked' => false]],
                    ],
                ],
                'tax' => ['name' => 'probe-tax', 'taxRate' => 15],
                'active' => true,
            ];
        }

        static::getContainer()->get('category.repository')->create([[
            'id' => $this->ids->create('category'),
            'name' => 'Probe',
            'blogAssignmentType' => 'blog',
            'blogs' => $blogs,
        ]], Context::createDefaultContext());

        $this->createCustomChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
            'navigationCategoryId' => $this->ids->get('category'),
        ]);

        $visibilities = array_map(fn (array $blog) => [
            'id' => $blog['id'],
            'visibilities' => [
                ['channelId' => $this->ids->get('sales-channel'), 'visibility' => BlogVisibilityDefinition::VISIBILITY_ALL],
            ],
        ], $blogs);

        static::getContainer()->get('blog.repository')->update($visibilities, Context::createDefaultContext());
    }
}
