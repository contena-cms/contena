<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\BlogExport\Service;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Order\Channel\OrderService;
use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Content\BlogExport\BlogExportCollection;
use Contena\Core\Content\BlogExport\BlogExportEntity;
use Contena\Core\Content\BlogExport\Service\BlogExportGenerator;
use Contena\Core\Content\BlogExport\Struct\ExportBehavior;
use Contena\Core\Content\BlogExport\Tracking\ChannelTrackingListener;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\ChannelApiTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\TranslationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelCollection;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\System\SystemConfig\SystemConfigService;

/**
 * @internal
 */
class AgenticCommerceBlogExportFlowTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;
    use ChannelApiTestBehaviour;
    use TranslationTestBehaviour;

    /**
     * @var EntityRepository<BlogExportCollection>
     */
    private EntityRepository $blogExportRepository;

    private BlogExportGenerator $blogExportGenerator;

    private Context $context;

    private SystemConfigService $systemConfigService;

    protected function setUp(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $this->blogExportRepository = static::getContainer()->get('blog_export.repository');
        $this->blogExportGenerator = static::getContainer()->get(BlogExportGenerator::class);
        $this->systemConfigService = static::getContainer()->get(SystemConfigService::class);
        $this->context = Context::createDefaultContext();
    }

    public function testAgenticCommerceChannelGeneratesOpenAiFeedFromExplicitBlogExport(): void
    {
        $blog = $this->createExportableBlog();
        $contentStreamId = $this->createBlogStreamForBlog($blog['id']);

        $agenticChannel = $this->createChannel([
            'id' => Uuid::randomHex(),
            'typeId' => Defaults::CHANNEL_TYPE_AGENTIC_COMMERCE,
            'name' => 'Agentic Commerce Feed',
            'countries' => [
                ['id' => $this->getDefaultCountryId()],
            ],
            'domains' => [
                [
                    'id' => Uuid::randomHex(),
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'url' => 'http://agentic-commerce.localhost',
                ],
            ],
        ]);

        $blogExport = $this->createBlogExport($agenticChannel['id'], $contentStreamId);

        static::assertSame(BlogExportEntity::FILE_FORMAT_JSONL, $blogExport->getFileFormat());
        static::assertSame('openai-blogs-' . substr($agenticChannel['id'], 0, 8) . '.jsonl', $blogExport->getFileName());

        $result = $this->blogExportGenerator->generate($blogExport, new ExportBehavior());

        static::assertNotNull($result);
        static::assertFalse($result->hasErrors());

        $lines = array_values(array_filter(
            preg_split('/\R/', $result->getContent()) ?: [],
            static fn (string $line): bool => trim($line) !== ''
        ));

        static::assertCount(1, $lines);
        static::assertJson($lines[0]);

        $exportedBlog = json_decode($lines[0], true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($exportedBlog);

        static::assertSame($blog['blogNumber'], $exportedBlog['item_id']);
        static::assertTrue($exportedBlog['is_eligible_search']);
        static::assertFalse($exportedBlog['is_eligible_checkout']);
        static::assertSame('OpenAI Feed Blog', $exportedBlog['title']);
        static::assertSame('Feed description', $exportedBlog['description']);
        static::assertSame('10.99 EUR', $exportedBlog['price']);
        static::assertSame('in_stock', $exportedBlog['availability']);
        static::assertSame('ACME', $exportedBlog['brand']);
        static::assertArrayNotHasKey('condition', $exportedBlog);
        static::assertFalse($exportedBlog['listing_has_variations']);
        static::assertArrayNotHasKey('group_id', $exportedBlog);
        static::assertArrayNotHasKey('item_group_title', $exportedBlog);
        static::assertArrayNotHasKey('offer_id', $exportedBlog);
        static::assertArrayNotHasKey('variant_dict', $exportedBlog);
        static::assertFalse($exportedBlog['is_digital']);
        static::assertSame('DE', $exportedBlog['store_country']);
        static::assertIsArray($exportedBlog['target_countries']);
        static::assertNotCount(0, $exportedBlog['target_countries']);
        static::assertContains('DE', $exportedBlog['target_countries']);
        static::assertSame('1234567890123', $exportedBlog['gtin']);
        static::assertSame('MPN-123', $exportedBlog['mpn']);
        static::assertSame($blogExport->getFrontendChannel()?->getName(), $exportedBlog['seller_name']);
        static::assertSame($blogExport->getChannelDomain()?->getUrl(), $exportedBlog['seller_url']);
        static::assertSame($blogExport->getChannelDomain()?->getUrl(), $exportedBlog['return_policy']);
        static::assertStringContainsString((string) $blogExport->getChannelDomain()?->getUrl(), $exportedBlog['url']);

        $query = parse_url($exportedBlog['url'], \PHP_URL_QUERY);
        parse_str(\is_string($query) ? $query : '', $queryParameters);
        static::assertSame($agenticChannel['id'], $queryParameters[ChannelTrackingListener::QUERY_PARAM] ?? null);

        static::assertStringStartsWith('https://example.com/images/openai-feed-blog.jpg', $exportedBlog['image_url']);
    }

    /**
     * @return array<string, array{config: array<string, string>, expectedAffiliate: ?string, expectedCampaign: ?string}>
     */
    public static function provideTrackingCodeCombinations(): array
    {
        return [
            'no tracking codes' => [
                'config' => [],
                'expectedAffiliate' => null,
                'expectedCampaign' => null,
            ],
            'affiliate code only' => [
                'config' => [OrderService::AFFILIATE_CODE_KEY => 'my-affiliate'],
                'expectedAffiliate' => 'my-affiliate',
                'expectedCampaign' => null,
            ],
            'campaign code only' => [
                'config' => [OrderService::CAMPAIGN_CODE_KEY => 'my-campaign'],
                'expectedAffiliate' => null,
                'expectedCampaign' => 'my-campaign',
            ],
            'both affiliate and campaign codes' => [
                'config' => [
                    OrderService::AFFILIATE_CODE_KEY => 'my-affiliate',
                    OrderService::CAMPAIGN_CODE_KEY => 'my-campaign',
                ],
                'expectedAffiliate' => 'my-affiliate',
                'expectedCampaign' => 'my-campaign',
            ],
        ];
    }

    /**
     * @param array<string, string> $config
     */
    #[DataProvider('provideTrackingCodeCombinations')]
    public function testBlogUrlContainsConfiguredTrackingCodes(
        array $config,
        ?string $expectedAffiliate,
        ?string $expectedCampaign,
    ): void {
        $blog = $this->createExportableBlog();
        $contentStreamId = $this->createBlogStreamForBlog($blog['id']);

        $agenticChannel = $this->createChannel([
            'id' => Uuid::randomHex(),
            'typeId' => Defaults::CHANNEL_TYPE_AGENTIC_COMMERCE,
            'name' => 'Agentic Commerce Feed',
            'configuration' => $config,
            'countries' => [
                ['id' => $this->getDefaultCountryId()],
            ],
            'domains' => [
                [
                    'id' => Uuid::randomHex(),
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'url' => 'http://agentic-commerce.localhost',
                ],
            ],
        ]);

        $blogExport = $this->createBlogExport($agenticChannel['id'], $contentStreamId);
        $result = $this->blogExportGenerator->generate($blogExport, new ExportBehavior());

        static::assertNotNull($result);
        static::assertFalse($result->hasErrors());

        $lines = array_values(array_filter(
            preg_split('/\R/', $result->getContent()) ?: [],
            static fn (string $line): bool => trim($line) !== '',
        ));

        static::assertCount(1, $lines);

        $exportedBlog = json_decode($lines[0], true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($exportedBlog);
        static::assertArrayHasKey('url', $exportedBlog);

        $query = parse_url($exportedBlog['url'], \PHP_URL_QUERY);
        parse_str(\is_string($query) ? $query : '', $queryParameters);

        static::assertSame(
            $agenticChannel['id'],
            $queryParameters[ChannelTrackingListener::QUERY_PARAM] ?? null,
            'referringChannel must always be present in the blog URL',
        );

        if ($expectedAffiliate !== null) {
            static::assertSame($expectedAffiliate, $queryParameters[OrderService::AFFILIATE_CODE_KEY] ?? null);
        } else {
            static::assertArrayNotHasKey(OrderService::AFFILIATE_CODE_KEY, $queryParameters);
        }

        if ($expectedCampaign !== null) {
            static::assertSame($expectedCampaign, $queryParameters[OrderService::CAMPAIGN_CODE_KEY] ?? null);
        } else {
            static::assertArrayNotHasKey(OrderService::CAMPAIGN_CODE_KEY, $queryParameters);
        }
    }

    public function testAgenticCommerceChannelGeneratesMappedVariantFieldsForOpenAiFeed(): void
    {
        $variant = $this->createExportableVariantBlog();
        $contentStreamId = $this->createBlogStreamForBlog($variant['id']);

        $agenticChannel = $this->createChannel([
            'id' => Uuid::randomHex(),
            'typeId' => Defaults::CHANNEL_TYPE_AGENTIC_COMMERCE,
            'name' => 'Agentic Commerce Feed',
            'domains' => [
                [
                    'id' => Uuid::randomHex(),
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'url' => 'http://agentic-commerce.localhost',
                ],
            ],
        ]);

        $channelId = $agenticChannel['id'];
        $this->systemConfigService->set('core.openAiBlogExport.variantColor', [$variant['colorGroupId']], $channelId);
        $this->systemConfigService->set('core.openAiBlogExport.variantSize', [$variant['sizeGroupId']], $channelId);
        $this->systemConfigService->set('core.openAiBlogExport.variantGender', [$variant['genderGroupId']], $channelId);
        $this->systemConfigService->set(
            'core.openAiBlogExport.variantCustom',
            [
                $variant['colorGroupId'], // overlap with specific mapping -> must be ignored
                $variant['custom1GroupId'],
                $variant['custom2GroupId'],
                $variant['custom3GroupId'],
                $variant['custom4GroupId'], // 4th custom value -> must be ignored (max 3)
            ],
            $channelId
        );

        $blogExport = $this->createBlogExport($channelId, $contentStreamId, true);
        $result = $this->blogExportGenerator->generate($blogExport, new ExportBehavior());

        static::assertNotNull($result);
        static::assertFalse($result->hasErrors(), 'The generated feed must be valid JSONL without export errors.');

        $lines = array_values(array_filter(
            preg_split('/\R/', $result->getContent()) ?: [],
            static fn (string $line): bool => trim($line) !== ''
        ));

        static::assertCount(1, $lines);
        static::assertJson($lines[0]);

        $exportedBlog = json_decode($lines[0], true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($exportedBlog);

        static::assertTrue($exportedBlog['listing_has_variations']);
        static::assertSame('SKU-' . $variant['blogNumber'] . '-10.99', $exportedBlog['offer_id']);
        static::assertSame($variant['parentId'], $exportedBlog['group_id']);
        static::assertSame('OpenAI Feed Variant', $exportedBlog['item_group_title']);

        static::assertSame('copper', $exportedBlog['color']);
        static::assertSame('XL', $exportedBlog['size']);
        static::assertSame('unisex', $exportedBlog['gender']);

        static::assertSame('plan', $exportedBlog['custom_variant1_category']);
        static::assertSame('12m', $exportedBlog['custom_variant1_option']);
        static::assertSame('material_custom', $exportedBlog['custom_variant2_category']);
        static::assertSame('silk', $exportedBlog['custom_variant2_option']);
        static::assertSame('finish', $exportedBlog['custom_variant3_category']);
        static::assertSame('matte', $exportedBlog['custom_variant3_option']);

        static::assertArrayNotHasKey('custom_variant4_category', $exportedBlog);
        static::assertArrayNotHasKey('custom_variant4_option', $exportedBlog);

        static::assertIsArray($exportedBlog['variant_dict']);
        static::assertSame('copper', $exportedBlog['variant_dict']['color']);
        static::assertSame('XL', $exportedBlog['variant_dict']['size']);
        static::assertSame('unisex', $exportedBlog['variant_dict']['gender']);
        static::assertSame('12m', $exportedBlog['variant_dict']['plan']);
        static::assertSame('silk', $exportedBlog['variant_dict']['material_custom']);
        static::assertSame('matte', $exportedBlog['variant_dict']['finish']);
        static::assertArrayNotHasKey('color_custom', $exportedBlog['variant_dict']);
    }

    public function testAgenticCommerceChannelGeneratesGoogleFeedFromExplicitBlogExport(): void
    {
        $blog = $this->createExportableBlog();
        $contentStreamId = $this->createBlogStreamForBlog($blog['id']);

        $agenticChannel = $this->createChannel([
            'id' => Uuid::randomHex(),
            'typeId' => Defaults::CHANNEL_TYPE_AGENTIC_COMMERCE,
            'name' => 'Agentic Commerce Feed',
            'countries' => [
                ['id' => $this->getDefaultCountryId()],
            ],
            'domains' => [
                [
                    'id' => Uuid::randomHex(),
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'url' => 'http://agentic-commerce.localhost',
                ],
            ],
        ]);

        $channelId = $agenticChannel['id'];

        $blogExport = $this->createGoogleBlogExport($channelId, $contentStreamId);

        static::assertSame(BlogExportEntity::FILE_FORMAT_XML, $blogExport->getFileFormat());
        static::assertSame('google', $blogExport->getProvider());

        $result = $this->blogExportGenerator->generate($blogExport, new ExportBehavior());

        static::assertNotNull($result);
        static::assertFalse($result->hasErrors());

        $content = $result->getContent();

        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($content);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        static::assertNotFalse($xml, 'Generated Google feed must be valid XML.');

        $items = $xml->xpath('//item');
        static::assertIsArray($items);
        static::assertCount(1, $items);

        $item = $items[0];
        $googleChildren = $item->children('http://base.google.com/ns/1.0');

        static::assertSame($blog['blogNumber'], (string) $googleChildren->id);
        static::assertSame('OpenAI Feed Blog', (string) $item->title);
        static::assertSame('Feed description', (string) $item->description);
        static::assertSame('in_stock', (string) $googleChildren->availability);
        static::assertSame('new', (string) $googleChildren->condition);
        static::assertSame('10.99 EUR', (string) $googleChildren->price);
        static::assertSame('ACME', (string) $googleChildren->brand);
        static::assertSame('1234567890123', (string) $googleChildren->gtin);
        static::assertSame('MPN-123', (string) $googleChildren->mpn);

        $link = (string) $item->link;
        static::assertStringStartsWith('http://agentic-commerce.localhost', $link);
        $query = parse_url($link, \PHP_URL_QUERY);
        parse_str(\is_string($query) ? $query : '', $queryParameters);
        static::assertSame($channelId, $queryParameters[ChannelTrackingListener::QUERY_PARAM] ?? null);
    }

    /**
     * @return array{id: string, blogNumber: string}
     */
    private function createExportableBlog(): array
    {
        $blogRepository = static::getContainer()->get('blog.repository');
        $frontendChannelId = $this->getDefaultFrontendChannelId();

        $blogId = Uuid::randomHex();
        $blogNumber = 'openai-feed-blog';
        $manufacturerId = Uuid::randomHex();
        $taxId = Uuid::randomHex();
        $blogMediaId = Uuid::randomHex();
        $mediaId = Uuid::randomHex();

        $blogRepository->create([
            [
                'id' => $blogId,
                'blogNumber' => $blogNumber,
                'active' => true,
                'stock' => 5,
                'name' => 'OpenAI Feed Blog',
                'description' => 'Feed description',
                'ean' => '1234567890123',
                'manufacturerNumber' => 'MPN-123',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10.99, 'net' => 9.24, 'linked' => false]],
                'manufacturer' => ['id' => $manufacturerId, 'name' => 'ACME'],
                'tax' => ['id' => $taxId, 'taxRate' => 19, 'name' => 'Standard'],
                'coverId' => $blogMediaId,
                'media' => [
                    [
                        'id' => $blogMediaId,
                        'position' => 1,
                        'media' => [
                            'id' => $mediaId,
                            'fileName' => 'openai-feed-blog',
                            'fileExtension' => 'jpg',
                            'mimeType' => 'image/jpeg',
                            'path' => 'https://example.com/images/openai-feed-blog.jpg',
                        ],
                    ],
                ],
                'visibilities' => [
                    ['channelId' => $frontendChannelId, 'visibility' => BlogVisibilityDefinition::VISIBILITY_ALL],
                ],
            ],
        ], $this->context);

        return [
            'id' => $blogId,
            'blogNumber' => $blogNumber,
        ];
    }

    private function createBlogStreamForBlog(string $blogId): string
    {
        $connection = static::getContainer()->get(Connection::class);
        $contentStreamId = '137B079935714281BA80B40F83F8D7EB';

        $connection->executeStatement(
            <<<'SQL'
                INSERT INTO `content_stream` (`id`, `api_filter`, `invalid`, `created_at`, `updated_at`)
                VALUES (
                    UNHEX(:contentStreamId),
                    :apiFilter,
                    0,
                    '2019-08-16 08:43:57.488',
                    NULL
                )
            SQL,
            [
                'contentStreamId' => $contentStreamId,
                'apiFilter' => \sprintf(
                    '[{"type":"multi","queries":[{"type":"multi","queries":[{"type":"equalsAny","field":"blog.id","value":"%s"}],"operator":"AND"}],"operator":"OR"}]',
                    $blogId
                ),
            ]
        );

        $connection->executeStatement(
            <<<'SQL'
                INSERT INTO `content_stream_filter`
                    (`id`, `content_stream_id`, `parent_id`, `type`, `field`, `operator`, `value`, `parameters`, `position`, `custom_fields`, `created_at`, `updated_at`)
                VALUES
                    (UNHEX('DA6CD9776BC84463B25D5B6210DDB57B'), UNHEX(:contentStreamId), NULL, 'multi', NULL, 'OR', NULL, NULL, 0, NULL, '2019-08-16 08:43:57.469', NULL),
                    (UNHEX('0EE60B6A87774E9884A832D601BE6B8F'), UNHEX(:contentStreamId), UNHEX('DA6CD9776BC84463B25D5B6210DDB57B'), 'multi', NULL, 'AND', NULL, NULL, 1, NULL, '2019-08-16 08:43:57.478', NULL),
                    (UNHEX('80B2B90171454467B769A4C161E74B87'), UNHEX(:contentStreamId), UNHEX('0EE60B6A87774E9884A832D601BE6B8F'), 'equalsAny', 'id', NULL, :blogId, NULL, 1, NULL, '2019-08-16 08:43:57.480', NULL)
            SQL,
            [
                'blogId' => $blogId,
                'contentStreamId' => $contentStreamId,
            ]
        );

        return strtolower($contentStreamId);
    }

    private function createBlogExport(string $channelId, string $contentStreamId, bool $includeVariants = false): BlogExportEntity
    {
        $channel = $this->loadChannel($channelId);
        $domain = $channel->getDomains()?->first();

        static::assertNotNull($domain);

        $blogExportId = Uuid::randomHex();

        $this->blogExportRepository->create([
            [
                'id' => $blogExportId,
                'contentStreamId' => $contentStreamId,
                'frontendChannelId' => $this->getDefaultFrontendChannelId(),
                'channelId' => $channelId,
                'channelDomainId' => $domain->getId(),
                'currencyId' => Defaults::CURRENCY,
                'fileName' => 'openai-blogs-' . substr($channelId, 0, 8) . '.jsonl',
                'accessKey' => Uuid::randomHex(),
                'encoding' => BlogExportEntity::ENCODING_UTF8,
                'fileFormat' => BlogExportEntity::FILE_FORMAT_JSONL,
                'provider' => 'open-ai',
                'includeVariants' => $includeVariants,
                'generateByCronjob' => false,
                'interval' => 86400,
                'headerTemplate' => '',
                'bodyTemplate' => $this->getOpenAiBodyTemplate(),
                'footerTemplate' => '',
            ],
        ], $this->context);

        $criteria = new Criteria();
        $criteria->setIds([$blogExportId]);
        $criteria->addAssociations([
            'channel',
            'frontendChannel',
            'channelDomain.language.locale',
        ]);

        $blogExport = $this->blogExportRepository->search($criteria, $this->context)->getEntities()->first();

        static::assertInstanceOf(BlogExportEntity::class, $blogExport);

        return $blogExport;
    }

    private function getDefaultCountryId(): string
    {
        $countryRepository = static::getContainer()->get('country.repository');

        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('iso', 'DE'))
            ->setLimit(1);

        return $countryRepository->searchIds($criteria, $this->context)->firstId() ?? throw new \RuntimeException('Default country not found');
    }

    /**
     * @return array{
     *     id: string,
     *     parentId: string,
     *     blogNumber: string,
     *     colorGroupId: string,
     *     sizeGroupId: string,
     *     genderGroupId: string,
     *     custom1GroupId: string,
     *     custom2GroupId: string,
     *     custom3GroupId: string,
     *     custom4GroupId: string
     * }
     */
    private function createExportableVariantBlog(): array
    {
        $blogRepository = static::getContainer()->get('blog.repository');
        $frontendChannelId = $this->getDefaultFrontendChannelId();

        $parentId = Uuid::randomHex();
        $variantId = Uuid::randomHex();

        $manufacturerId = Uuid::randomHex();
        $taxId = Uuid::randomHex();
        $mediaId = Uuid::randomHex();
        $blogMediaId = Uuid::randomHex();

        $colorGroupId = Uuid::randomHex();
        $sizeGroupId = Uuid::randomHex();
        $genderGroupId = Uuid::randomHex();
        $custom1GroupId = Uuid::randomHex();
        $custom2GroupId = Uuid::randomHex();
        $custom3GroupId = Uuid::randomHex();
        $custom4GroupId = Uuid::randomHex();

        $blogRepository->create([
            [
                'id' => $parentId,
                'blogNumber' => 'openai-feed-parent',
                'active' => true,
                'stock' => 5,
                'name' => 'OpenAI Feed Parent',
                'description' => 'Feed parent description',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10.99, 'net' => 9.24, 'linked' => false]],
                'manufacturer' => ['id' => $manufacturerId, 'name' => 'ACME'],
                'tax' => ['id' => $taxId, 'taxRate' => 19, 'name' => 'Standard'],
                'visibilities' => [
                    ['channelId' => $frontendChannelId, 'visibility' => BlogVisibilityDefinition::VISIBILITY_ALL],
                ],
            ],
            [
                'id' => $variantId,
                'parentId' => $parentId,
                'blogNumber' => 'openai-feed-variant',
                'active' => true,
                'stock' => 5,
                'name' => 'OpenAI Feed Variant',
                'description' => 'Feed variant description',
                'ean' => '1234567890123',
                'manufacturerNumber' => 'MPN-123',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10.99, 'net' => 9.24, 'linked' => false]],
                'manufacturerId' => $manufacturerId,
                'taxId' => $taxId,
                'coverId' => $blogMediaId,
                'media' => [
                    [
                        'id' => $blogMediaId,
                        'position' => 1,
                        'media' => [
                            'id' => $mediaId,
                            'fileName' => 'openai-feed-variant',
                            'fileExtension' => 'jpg',
                            'mimeType' => 'image/jpeg',
                            'path' => 'https://example.com/images/openai-feed-blog.jpg',
                        ],
                    ],
                ],
                'visibilities' => [
                    ['channelId' => $frontendChannelId, 'visibility' => BlogVisibilityDefinition::VISIBILITY_ALL],
                ],
                'options' => [
                    $this->createOptionPayload($colorGroupId, 'color', 'copper'),
                    $this->createOptionPayload($sizeGroupId, 'size', 'XL'),
                    $this->createOptionPayload($genderGroupId, 'gender', 'unisex'),
                    $this->createOptionPayload($custom1GroupId, 'plan', '12m'),
                    $this->createOptionPayload($custom2GroupId, 'material_custom', 'silk'),
                    $this->createOptionPayload($custom3GroupId, 'finish', 'matte'),
                    $this->createOptionPayload($custom4GroupId, 'color_custom', 'rose'),
                ],
            ],
        ], $this->context);

        return [
            'id' => $variantId,
            'parentId' => $parentId,
            'blogNumber' => 'openai-feed-variant',
            'colorGroupId' => $colorGroupId,
            'sizeGroupId' => $sizeGroupId,
            'genderGroupId' => $genderGroupId,
            'custom1GroupId' => $custom1GroupId,
            'custom2GroupId' => $custom2GroupId,
            'custom3GroupId' => $custom3GroupId,
            'custom4GroupId' => $custom4GroupId,
        ];
    }

    /**
     * @return array{
     *     id: string,
     *     position: int,
     *     groupId: string,
     *     group: array{id: string, position: int, name: string},
     *     name: string
     * }
     */
    private function createOptionPayload(string $groupId, string $groupName, string $optionName): array
    {
        return [
            'id' => Uuid::randomHex(),
            'position' => 1,
            'groupId' => $groupId,
            'group' => [
                'id' => $groupId,
                'position' => 1,
                'name' => $groupName,
            ],
            'name' => $optionName,
        ];
    }

    private function getOpenAiBodyTemplate(): string
    {
        $template = file_get_contents(__DIR__ . '/../../../../../../src/Administration/Resources/app/administration/src/module/sw-sales-channel/agentic-blog-export-templates/open-ai/body.json.twig');

        static::assertIsString($template);

        return $template;
    }

    private function getGoogleHeaderTemplate(): string
    {
        $template = file_get_contents(__DIR__ . '/../../../../../../src/Administration/Resources/app/administration/src/module/sw-sales-channel/agentic-blog-export-templates/google/header.xml.twig');

        static::assertIsString($template);

        return $template;
    }

    private function getGoogleBodyTemplate(): string
    {
        $template = file_get_contents(__DIR__ . '/../../../../../../src/Administration/Resources/app/administration/src/module/sw-sales-channel/agentic-blog-export-templates/google/body.xml.twig');

        static::assertIsString($template);

        return $template;
    }

    private function getGoogleFooterTemplate(): string
    {
        $template = file_get_contents(__DIR__ . '/../../../../../../src/Administration/Resources/app/administration/src/module/sw-sales-channel/agentic-blog-export-templates/google/footer.xml.twig');

        static::assertIsString($template);

        return $template;
    }

    private function createGoogleBlogExport(string $channelId, string $contentStreamId, bool $includeVariants = false): BlogExportEntity
    {
        $channel = $this->loadChannel($channelId);
        $domain = $channel->getDomains()?->first();

        static::assertNotNull($domain);

        $blogExportId = Uuid::randomHex();

        $this->blogExportRepository->create([
            [
                'id' => $blogExportId,
                'contentStreamId' => $contentStreamId,
                'frontendChannelId' => $this->getDefaultFrontendChannelId(),
                'channelId' => $channelId,
                'channelDomainId' => $domain->getId(),
                'currencyId' => Defaults::CURRENCY,
                'fileName' => 'google-blogs-' . substr($channelId, 0, 8) . '.xml',
                'accessKey' => Uuid::randomHex(),
                'encoding' => BlogExportEntity::ENCODING_UTF8,
                'fileFormat' => BlogExportEntity::FILE_FORMAT_XML,
                'provider' => 'google',
                'includeVariants' => $includeVariants,
                'generateByCronjob' => false,
                'interval' => 86400,
                'headerTemplate' => $this->getGoogleHeaderTemplate(),
                'bodyTemplate' => $this->getGoogleBodyTemplate(),
                'footerTemplate' => $this->getGoogleFooterTemplate(),
            ],
        ], $this->context);

        $criteria = new Criteria();
        $criteria->setIds([$blogExportId]);
        $criteria->addAssociations([
            'channel',
            'frontendChannel',
            'channelDomain.language.locale',
        ]);

        $blogExport = $this->blogExportRepository->search($criteria, $this->context)->getEntities()->first();

        static::assertInstanceOf(BlogExportEntity::class, $blogExport);

        return $blogExport;
    }

    private function loadChannel(string $channelId): ChannelEntity
    {
        /** @var EntityRepository<ChannelCollection> $repository */
        $repository = static::getContainer()->get('channel.repository');

        $criteria = new Criteria([$channelId]);
        $criteria->addAssociation('domains');

        $channel = $repository->search($criteria, $this->context)->getEntities()->first();

        static::assertInstanceOf(ChannelEntity::class, $channel);

        return $channel;
    }

    private function getDefaultFrontendChannelId(): string
    {
        /** @var EntityRepository<ChannelCollection> $repository */
        $repository = static::getContainer()->get('channel.repository');

        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addFilter(new EqualsFilter('typeId', Defaults::CHANNEL_TYPE_FRONTEND));
        $criteria->addFilter(new EqualsFilter('active', true));

        $channel = $repository->search($criteria, $this->context)->getEntities()->first();

        static::assertInstanceOf(ChannelEntity::class, $channel);

        return $channel->getId();
    }
}
