<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Cms\Channel;

use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Category\CategoryCollection;
use Contena\Core\Content\Cms\DataResolver\FieldConfig;
use Contena\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Contena\Core\Content\Cms\Channel\ChannelCmsPageLoader;
use Contena\Core\Content\Cms\Channel\ChannelCmsPageLoaderInterface;
use Contena\Core\Defaults;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\ChannelFunctionalTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Context\ChannelContextService;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class ChannelCmsPageLoaderTest extends TestCase
{
    use ChannelFunctionalTestBehaviour;

    /**
     * @var EntityRepository<CategoryCollection>
     */
    private EntityRepository $categoryRepository;

    private ChannelCmsPageLoaderInterface $channelCmsPageLoader;

    private ChannelContext $channelContext;

    private static string $firstSlotId;

    private static string $secondSlotId;

    private static string $categoryId;

    /**
     * @var array<string, mixed>
     */
    private static array $category;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$firstSlotId = Uuid::randomHex();
        self::$secondSlotId = Uuid::randomHex();
        self::$categoryId = Uuid::randomHex();

        self::$category = [
            'id' => self::$categoryId,
            'name' => 'test category',
            'cmsPage' => [
                'id' => Uuid::randomHex(),
                'name' => 'test page',
                'type' => 'landingpage',
                'sections' => [
                    [
                        'id' => Uuid::randomHex(),
                        'type' => 'default',
                        'position' => 0,
                        'blocks' => [
                            [
                                'type' => 'text',
                                'position' => 0,
                                'slots' => [
                                    [
                                        'id' => self::$firstSlotId,
                                        'type' => 'text',
                                        'slot' => 'content',
                                        'config' => [
                                            'content' => [
                                                'source' => 'static',
                                                'value' => 'initial',
                                            ],
                                        ],
                                    ],
                                    [
                                        'id' => self::$secondSlotId,
                                        'type' => 'text',
                                        'slot' => 'content',
                                        'config' => null,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'slotConfig' => [
                self::$firstSlotId => [
                    'content' => [
                        'source' => 'static',
                        'value' => 'overwrittenByCategory',
                    ],
                ],
            ],
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->categoryRepository = static::getContainer()->get('category.repository');
        $this->channelCmsPageLoader = static::getContainer()->get(ChannelCmsPageLoader::class);

        $this->channelContext = $this->createChannelContext();

        $this->categoryRepository->create([self::$category], $this->channelContext->getContext());
    }

    public function testSlotOverwrite(): void
    {
        $pages = $this->channelCmsPageLoader->load(
            new Request(),
            new Criteria([self::$category['cmsPage']['id']]),
            $this->channelContext,
            []
        );

        static::assertSame(1, $pages->getTotal());

        $page = $pages->getEntities()->first();
        static::assertNotNull($page);
        $sections = $page->getSections();
        static::assertNotNull($sections);
        $firstSection = $sections->first();
        static::assertNotNull($firstSection);
        $blocks = $firstSection->getBlocks();
        static::assertNotNull($blocks);
        $firstSlot = $blocks->getSlots()->get(self::$firstSlotId);
        static::assertNotNull($firstSlot);

        static::assertEquals(
            self::$category['cmsPage']['sections'][0]['blocks'][0]['slots'][0]['config'],
            $firstSlot->getConfig()
        );

        static::assertEquals(
            new FieldConfigCollection([new FieldConfig('content', 'static', 'initial')]),
            $firstSlot->getFieldConfig()
        );

        $secondSlot = $blocks->getSlots()->get(self::$secondSlotId);
        static::assertNotNull($secondSlot);
        static::assertNull($secondSlot->getConfig());

        // overwrite in category
        $customSlotConfig = [
            (string) self::$category['cmsPage']['sections'][0]['blocks'][0]['slots'][0]['id'] => [
                'content' => [
                    'source' => 'static',
                    'value' => 'overwrite',
                ],
            ],
            (string) self::$category['cmsPage']['sections'][0]['blocks'][0]['slots'][1]['id'] => [
                'content' => [
                    'source' => 'static',
                    'value' => 'overwrite',
                ],
            ],
        ];

        $pages = $this->channelCmsPageLoader->load(
            new Request(),
            new Criteria([self::$category['cmsPage']['id']]),
            $this->channelContext,
            $customSlotConfig
        );

        static::assertGreaterThanOrEqual(1, $pages->getTotal());

        $page = $pages->getEntities()->first();
        static::assertNotNull($page);
        $sections = $page->getSections();
        static::assertNotNull($sections);
        $firstSection = $sections->first();
        static::assertNotNull($firstSection);
        $blocks = $firstSection->getBlocks();
        static::assertNotNull($blocks);
        $firstSlot = $blocks->getSlots()->get(self::$firstSlotId);
        static::assertNotNull($firstSlot);

        static::assertEquals(
            $customSlotConfig[self::$category['cmsPage']['sections'][0]['blocks'][0]['slots'][0]['id']],
            $firstSlot->getConfig()
        );

        static::assertEquals(
            new FieldConfigCollection([new FieldConfig('content', 'static', 'overwrite')]),
            $firstSlot->getFieldConfig()
        );

        $secondSlot = $blocks->getSlots()->get(self::$secondSlotId);
        static::assertNotNull($secondSlot);
        static::assertSame(
            $customSlotConfig[self::$category['cmsPage']['sections'][0]['blocks'][0]['slots'][1]['id']],
            $secondSlot->getConfig()
        );
    }

    public function testInheritSlotConfig(): void
    {
        $channelContextDe = $this->createChannelContext(
            [
                'languages' => [
                    ['id' => Defaults::LANGUAGE_SYSTEM],
                    ['id' => $this->getDeDeLanguageId()],
                ],
                'domains' => [
                    [
                        'languageId' => $this->getDeDeLanguageId(),
                        'currencyId' => Defaults::CURRENCY,
                        'snippetSetId' => $this->getSnippetSetIdForLocale('de-DE'),
                        'url' => 'http://localhost/de',
                    ],
                ],
            ],
            [
                ChannelContextService::LANGUAGE_ID => $this->getDeDeLanguageId(),
            ]
        );

        $pages = $this->channelCmsPageLoader->load(
            new Request(),
            new Criteria([self::$category['cmsPage']['id']]),
            $channelContextDe
        );

        $config = $pages->getEntities()->first()?->getSections()?->getBlocks()->getSlots()->get(self::$firstSlotId)?->getConfig();
        static::assertNotNull($config);
        static::assertNotCount(0, $config);
    }

    public function testInheritSlotConfigOverwriteByCategory(): void
    {
        $channelContextDe = $this->createChannelContext(
            [
                'languages' => [
                    ['id' => Defaults::LANGUAGE_SYSTEM],
                    ['id' => $this->getDeDeLanguageId()],
                ],
                'domains' => [
                    [
                        'languageId' => $this->getDeDeLanguageId(),
                        'currencyId' => Defaults::CURRENCY,
                        'snippetSetId' => $this->getSnippetSetIdForLocale('de-DE'),
                        'url' => 'http://localhost/de',
                    ],
                ],
            ],
            [
                ChannelContextService::LANGUAGE_ID => $this->getDeDeLanguageId(),
            ]
        );

        $criteria = new Criteria([self::$categoryId]);
        $criteria->addAssociation('media');

        $category = $this->categoryRepository->search($criteria, $channelContextDe->getContext())->getEntities()->get(self::$categoryId);
        static::assertNotNull($category);

        $pages = $this->channelCmsPageLoader->load(
            new Request(),
            new Criteria([self::$category['cmsPage']['id']]),
            $channelContextDe,
            $category->getTranslation('slotConfig')
        );

        $config = $pages->getEntities()->first()?->getSections()?->getBlocks()->getSlots()->get(self::$firstSlotId)?->getConfig();
        static::assertIsArray($config);
        static::assertSame('overwrittenByCategory', $config['content']['value']);
    }
}
