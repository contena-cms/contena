<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Blog\Subscriber;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Contena\Core\Checkout\Cart\Price\Struct\CartPrice;
use Contena\Core\Checkout\Cart\Price\Struct\ListPrice;
use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Content\Blog\BlogEvents;
use Contena\Core\Content\Blog\Channel\ChannelBlogEntity;
use Contena\Core\Content\Blog\DataAbstractionLayer\CheapestPrice\CalculatedCheapestPrice;
use Contena\Core\Content\Blog\DataAbstractionLayer\CheapestPrice\CheapestPrice;
use Contena\Core\Content\Blog\Subscriber\BlogSubscriber;
use Contena\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
use Contena\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionEntity;
use Contena\Core\Content\Property\PropertyGroupCollection;
use Contena\Core\Content\Property\PropertyGroupEntity;
use Contena\Core\Content\Test\Blog\BlogBuilder;
use Contena\Core\Defaults;
use Contena\Core\Framework\Api\Context\SystemSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\PartialEntity;
use Contena\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Test\TestDefaults;

/**
 * @internal
 */
class BlogLoadedSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testExtensionSubscribesToBlogLoaded(): void
    {
        static::assertArrayHasKey(BlogEvents::BLOG_LOADED_EVENT, BlogSubscriber::getSubscribedEvents());
        static::assertArrayHasKey('channel.blog.loaded', BlogSubscriber::getSubscribedEvents());
        static::assertIsString(BlogSubscriber::getSubscribedEvents()[BlogEvents::BLOG_LOADED_EVENT]);
        static::assertIsString(BlogSubscriber::getSubscribedEvents()['channel.blog.loaded']);
    }

    public function testCheapestPriceOnChannelBlogEntity(): void
    {
        $ids = new IdsCollection();

        static::getContainer()->get('blog.repository')
            ->create([
                (new BlogBuilder($ids, 'p.1'))
                    ->price(130)
                    ->prices('rule-a', 150)
                    ->visibility()
                    ->build(),
            ], Context::createDefaultContext());

        $channelContext = static::getContainer()->get(ChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::CHANNEL);

        $blogEntity = static::getContainer()
            ->get('channel.blog.repository')
            ->search(new Criteria([$ids->get('p.1')]), $channelContext)
            ->getEntities()
            ->first();

        static::assertInstanceOf(ChannelBlogEntity::class, $blogEntity);

        static::assertInstanceOf(CheapestPrice::class, $blogEntity->getCheapestPrice());
    }

    public function testCheapestPriceOnChannelBlogEntityPartial(): void
    {
        $ids = new IdsCollection();

        static::getContainer()->get('blog.repository')
            ->create([
                (new BlogBuilder($ids, 'p.1'))
                    ->price(130)
                    ->prices('rule-a', 150)
                    ->visibility()
                    ->build(),
            ], Context::createDefaultContext());

        $channelContext = static::getContainer()->get(ChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::CHANNEL);

        $criteria = new Criteria([$ids->get('p.1')]);
        $criteria->addFields(['id', 'cheapestPrice', 'taxId', 'price']);

        $blogEntity = static::getContainer()
            ->get('channel.blog.repository')
            ->search($criteria, $channelContext)
            ->getEntities()
            ->first();

        static::assertNotNull($blogEntity);
        static::assertInstanceOf(CheapestPrice::class, $blogEntity->get('cheapestPrice'));
        static::assertInstanceOf(CalculatedCheapestPrice::class, $blogEntity->get('calculatedCheapestPrice'));
    }

    /**
     * @param array<mixed> $blog
     * @param array<mixed> $expected
     * @param array<mixed> $unexpected
     */
    #[DataProvider('propertyCases')]
    public function testSortProperties(array $blog, array $expected, array $unexpected, Criteria $criteria): void
    {
        static::getContainer()->get('blog.repository')
            ->create([$blog], Context::createDefaultContext());

        $channelContext = static::getContainer()->get(ChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::CHANNEL);

        $criteria->setIds([$blog['id']])
            ->addAssociation('properties.group');

        $blogEntity = static::getContainer()
            ->get('channel.blog.repository')
            ->search($criteria, $channelContext)
            ->getEntities()
            ->first();

        static::assertInstanceOf(ChannelBlogEntity::class, $blogEntity);

        $sortedPropertiesCollection = $blogEntity->getSortedProperties();

        static::assertInstanceOf(PropertyGroupCollection::class, $sortedPropertiesCollection);

        $sortedProperties = $sortedPropertiesCollection->getElements();

        foreach ($expected as $expectedGroupKey => $expectedGroup) {
            $optionElementsCollection = $sortedProperties[$expectedGroupKey]->getOptions();

            static::assertInstanceOf(PropertyGroupOptionCollection::class, $optionElementsCollection);
            $optionElements = $optionElementsCollection->getElements();

            static::assertSame($expectedGroup['name'], $sortedProperties[$expectedGroupKey]->getName());
            static::assertSame($expectedGroup['id'], $sortedProperties[$expectedGroupKey]->getId());
            static::assertSame(\array_keys($expectedGroup['options']), \array_keys($optionElements));

            foreach ($expectedGroup['options'] as $optionId => $option) {
                static::assertSame($option['id'], $optionElements[$optionId]->getId());
                static::assertSame($option['name'], $optionElements[$optionId]->getName());
            }
        }

        foreach ($unexpected as $unexpectedGroup) {
            static::assertArrayNotHasKey($unexpectedGroup['id'], $sortedProperties);
        }
    }

    /**
     * @param array<mixed> $blog
     * @param array<mixed> $expected
     * @param array<mixed> $unexpected
     */
    #[DataProvider('propertyCases')]
    public function testSortPropertiesPartial(array $blog, array $expected, array $unexpected, Criteria $criteria): void
    {
        static::getContainer()->get('blog.repository')
            ->create([$blog], Context::createDefaultContext());

        $channelContext = static::getContainer()->get(ChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::CHANNEL);

        $criteria->setIds([$blog['id']])
            ->addAssociation('properties.group')
            ->addFields(['properties', 'price']);

        $blogEntity = static::getContainer()
            ->get('channel.blog.repository')
            ->search($criteria, $channelContext)
            ->getEntities()
            ->first();

        static::assertNotNull($blogEntity);

        $sortedProperties = $blogEntity->get('sortedProperties');
        static::assertInstanceOf(PropertyGroupCollection::class, $sortedProperties);
        $sortedProperties = $sortedProperties->getElements();

        foreach ($expected as $expectedGroupKey => $expectedGroup) {
            $sortedProperty = $sortedProperties[$expectedGroupKey];
            static::assertInstanceOf(PropertyGroupEntity::class, $sortedProperty);

            $optionElements = $sortedProperty->get('options');
            static::assertInstanceOf(PropertyGroupOptionCollection::class, $optionElements);
            $optionElements = $optionElements->getElements();

            static::assertSame($expectedGroup['name'], $sortedProperty->get('name'));
            static::assertSame($expectedGroup['id'], $sortedProperty->getId());
            static::assertSame(\array_keys($expectedGroup['options']), \array_keys($optionElements));

            foreach ($expectedGroup['options'] as $optionId => $option) {
                $optionElement = $optionElements[$optionId];
                static::assertInstanceOf(PropertyGroupOptionEntity::class, $optionElement);

                static::assertSame($option['id'], $optionElement->getId());
                static::assertSame($option['name'], $optionElement->get('name'));
            }
        }

        foreach ($unexpected as $unexpectedGroup) {
            static::assertArrayNotHasKey($unexpectedGroup['id'], $sortedProperties);
        }
    }

    /**
     * @return array<mixed>
     */
    public static function propertyCases(): array
    {
        $ids = new IdsCollection();

        $defaults = [
            'id' => $ids->get('blog'),
            'name' => 'test-blog',
            'blogNumber' => $ids->get('blog'),
            'stock' => 10,
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false],
            ],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'visibilities' => [
                [
                    'channelId' => TestDefaults::CHANNEL,
                    'visibility' => BlogVisibilityDefinition::VISIBILITY_ALL,
                ],
            ],
        ];

        return [
            [
                array_merge($defaults, [
                    'properties' => [
                        [
                            'id' => $ids->get('bitter'),
                            'name' => 'bitter',
                            'groupId' => $ids->get('taste'),
                            'group' => ['id' => $ids->get('taste'), 'name' => 'taste'],
                        ],
                        [
                            'id' => $ids->get('sweet'),
                            'name' => 'sweet',
                            'groupId' => $ids->get('taste'),
                            'group' => ['id' => $ids->get('taste'), 'name' => 'taste'],
                        ],
                        [
                            'id' => $ids->get('hiddenValue'),
                            'name' => 'hiddenValue',
                            'groupId' => $ids->get('hidden'),
                            'group' => ['id' => $ids->get('hidden'), 'name' => 'hidden', 'visibleOnBlogDetailPage' => false],
                        ],
                    ],
                ]),
                [
                    $ids->get('taste') => [
                        'id' => $ids->get('taste'),
                        'name' => 'taste',
                        'options' => [
                            $ids->get('bitter') => [
                                'id' => $ids->get('bitter'),
                                'name' => 'bitter',
                            ],
                            $ids->get('sweet') => [
                                'id' => $ids->get('sweet'),
                                'name' => 'sweet',
                            ],
                        ],
                    ],
                ],
                [
                    [
                        'id' => $ids->get('hidden'),
                        'name' => 'hidden',
                        'visibleOnBlogDetailPage' => false,
                        'options' => [
                            $ids->get('hiddenValue') => [
                                'id' => $ids->get('hiddenValue'),
                                'name' => 'hiddenValue',
                            ],
                        ],
                    ],
                ],
                new Criteria(),
            ],
            [
                array_merge($defaults, [
                    'properties' => [
                        [
                            'id' => $ids->get('bitter'),
                            'name' => 'bitter',
                            'groupId' => $ids->get('taste'),
                            'group' => ['id' => $ids->get('taste'), 'name' => 'taste'],
                        ],
                        [
                            'id' => $ids->get('sweet'),
                            'name' => 'sweet',
                            'groupId' => $ids->get('taste'),
                            'group' => ['id' => $ids->get('taste'), 'name' => 'taste'],
                        ],
                        [
                            'id' => $ids->get('red'),
                            'name' => 'red',
                            'groupId' => $ids->get('color'),
                            'group' => ['id' => $ids->get('color'), 'name' => 'color'],
                        ],
                        [
                            'id' => $ids->get('leather'),
                            'name' => 'leather',
                            'groupId' => $ids->get('material'),
                            'group' => ['id' => $ids->get('material'), 'name' => 'material'],
                        ],
                    ],
                ]),
                [
                    $ids->get('color') => [
                        'id' => $ids->get('color'),
                        'name' => 'color',
                        'options' => [
                            $ids->get('red') => [
                                'id' => $ids->get('red'),
                                'name' => 'red',
                            ],
                        ],
                    ],
                    $ids->get('material') => [
                        'id' => $ids->get('material'),
                        'name' => 'material',
                        'options' => [
                            $ids->get('leather') => [
                                'id' => $ids->get('leather'),
                                'name' => 'leather',
                            ],
                        ],
                    ],
                    $ids->get('taste') => [
                        'id' => $ids->get('taste'),
                        'name' => 'taste',
                        'options' => [
                            $ids->get('bitter') => [
                                'id' => $ids->get('bitter'),
                                'name' => 'bitter',
                            ],
                            $ids->get('sweet') => [
                                'id' => $ids->get('sweet'),
                                'name' => 'sweet',
                            ],
                        ],
                    ],
                ],
                [],
                new Criteria(),
            ],
        ];
    }

    /**
     * @param array<mixed> $blog
     * @param array<mixed> $expected
     * @param non-empty-list<string> $languageChain
     */
    #[DataProvider('variationCases')]
    public function testVariation(array $blog, array $expected, array $languageChain, Criteria $criteria, bool $sort, string $languageId): void
    {
        static::getContainer()
            ->get('language.repository')
            ->create([
                [
                    'id' => $languageId,
                    'name' => 'sub_en',
                    'parentId' => Defaults::LANGUAGE_SYSTEM,
                    'active' => true,
                    'localeId' => $this->getLocaleIdOfSystemLanguage(),
                ],
            ], Context::createDefaultContext());

        foreach ($languageChain as &$language) {
            if ($language === 'de-DE') {
                $language = $this->getDeDeLanguageId();
            }
        }

        $blogId = $blog['id'];
        $context = Context::createDefaultContext();

        static::getContainer()->get('blog.repository')
            ->create([$blog], $context);

        $context = new Context(
            new SystemSource(),
            [],
            Defaults::CURRENCY,
            $languageChain
        );

        $criteria->setIds([$blogId]);

        $blogEntity = static::getContainer()
            ->get('blog.repository')
            ->search($criteria, $context)
            ->getEntities()
            ->first();

        static::assertInstanceOf(BlogEntity::class, $blogEntity);

        $variation = $blogEntity->getVariation();

        if ($sort) {
            sort($variation);
            sort($expected);
        }

        static::assertSame($expected, $variation);
    }

    /**
     * @return array<mixed>
     */
    public static function variationCases(): array
    {
        $ids = new IdsCollection();

        $defaults = [
            'id' => $ids->get('blog'),
            'name' => 'test-blog',
            'blogNumber' => $ids->get('blog'),
            'stock' => 10,
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false],
            ],
            'tax' => ['name' => 'test', 'taxRate' => 15],
        ];

        return [
            0 => [
                array_merge($defaults, [
                    'options' => [
                        [
                            'id' => $ids->get('red'),
                            'name' => 'red',
                            'group' => ['id' => $ids->get('color'), 'name' => 'color'],
                        ],
                        [
                            'id' => $ids->get('xl'),
                            'name' => 'xl',
                            'group' => ['id' => $ids->get('size'), 'name' => 'size'],
                        ],
                        [
                            'id' => $ids->get('slim-fit'),
                            'name' => 'slim fit',
                            'group' => ['id' => $ids->get('fit'), 'name' => 'fit'],
                        ],
                    ],
                ]),
                [],
                [Defaults::LANGUAGE_SYSTEM],
                new Criteria(),
                false,
                $ids->get('language'),
            ],
            1 => [
                array_merge($defaults, [
                    'options' => [
                        [
                            'id' => $ids->get('red'),
                            'name' => 'red',
                            'group' => ['id' => $ids->get('color'), 'name' => 'color'],
                        ],
                        [
                            'id' => $ids->get('xl'),
                            'name' => 'xl',
                            'group' => ['id' => $ids->get('size'), 'name' => 'size'],
                        ],
                        [
                            'id' => $ids->get('slim-fit'),
                            'name' => 'slim fit',
                            'group' => ['id' => $ids->get('fit'), 'name' => 'fit'],
                        ],
                    ],
                ]),
                [
                    ['group' => 'color', 'option' => 'red'],
                    ['group' => 'size', 'option' => 'xl'],
                    ['group' => 'fit', 'option' => 'slim fit'],
                ],
                [Defaults::LANGUAGE_SYSTEM],
                (new Criteria())->addAssociation('options.group'),
                true,
                $ids->get('language'),
            ],
            2 => [
                array_merge($defaults, [
                    'options' => [
                        [
                            'id' => $ids->get('red'),
                            'group' => [
                                'id' => $ids->get('color'),
                                'translations' => [
                                    Defaults::LANGUAGE_SYSTEM => ['name' => 'color'],
                                    'de-DE' => ['name' => 'farbe'],
                                ],
                            ],
                            'translations' => [
                                Defaults::LANGUAGE_SYSTEM => ['name' => 'red'],
                                'de-DE' => ['name' => 'rot'],
                            ],
                        ],
                        [
                            'id' => $ids->get('xl'),
                            'group' => [
                                'id' => $ids->get('size'),
                                'translations' => [
                                    Defaults::LANGUAGE_SYSTEM => ['name' => 'size'],
                                    'de-DE' => ['name' => 'größe'],
                                ],
                            ],
                            'translations' => [
                                Defaults::LANGUAGE_SYSTEM => ['name' => 'xl'],
                                'de-DE' => ['name' => 'extra gross'],
                            ],
                        ],
                        [
                            'id' => $ids->get('slim-fit'),
                            'group' => [
                                'id' => $ids->get('fit'),
                                'translations' => [
                                    Defaults::LANGUAGE_SYSTEM => ['name' => 'fit'],
                                    'de-DE' => ['name' => 'passform'],
                                ],
                            ],
                            'translations' => [
                                Defaults::LANGUAGE_SYSTEM => ['name' => 'slim fit'],
                                'de-DE' => ['name' => 'schmal'],
                            ],
                        ],
                    ],
                ]),
                [
                    ['group' => 'farbe', 'option' => 'rot'],
                    ['group' => 'größe', 'option' => 'extra gross'],
                    ['group' => 'passform', 'option' => 'schmal'],
                ],
                ['de-DE', Defaults::LANGUAGE_SYSTEM],
                (new Criteria())->addAssociation('options.group'),
                true,
                $ids->get('language'),
            ],
            3 => [
                array_merge($defaults, [
                    'options' => [
                        [
                            'id' => $ids->get('red'),
                            'group' => [
                                'id' => $ids->get('color'),
                                'translations' => [
                                    Defaults::LANGUAGE_SYSTEM => ['name' => 'color'],
                                ],
                            ],
                            'translations' => [
                                Defaults::LANGUAGE_SYSTEM => ['name' => 'red'],
                            ],
                        ],
                        [
                            'id' => $ids->get('xl'),
                            'group' => [
                                'id' => $ids->get('size'),
                                'translations' => [
                                    Defaults::LANGUAGE_SYSTEM => ['name' => 'size'],
                                ],
                            ],
                            'translations' => [
                                Defaults::LANGUAGE_SYSTEM => ['name' => 'xl'],
                            ],
                        ],
                        [
                            'id' => $ids->get('slim-fit'),
                            'group' => [
                                'id' => $ids->get('fit'),
                                'translations' => [
                                    Defaults::LANGUAGE_SYSTEM => ['name' => 'fit'],
                                ],
                            ],
                            'translations' => [
                                Defaults::LANGUAGE_SYSTEM => ['name' => 'slim fit'],
                            ],
                        ],
                    ],
                ]),
                [
                    ['group' => 'color', 'option' => 'red'],
                    ['group' => 'size', 'option' => 'xl'],
                    ['group' => 'fit', 'option' => 'slim fit'],
                ],
                ['de-DE', Defaults::LANGUAGE_SYSTEM],
                (new Criteria())->addAssociation('options.group'),
                true,
                $ids->get('language'),
            ],
            4 => [
                array_merge($defaults, [
                    'options' => [
                        [
                            'id' => $ids->get('red'),
                            'group' => [
                                'id' => $ids->get('color'),
                                'translations' => [
                                    Defaults::LANGUAGE_SYSTEM => ['name' => 'color'],
                                ],
                            ],
                            'translations' => [
                                Defaults::LANGUAGE_SYSTEM => ['name' => 'red'],
                            ],
                        ],
                        [
                            'id' => $ids->get('xl'),
                            'group' => [
                                'id' => $ids->get('size'),
                                'translations' => [
                                    Defaults::LANGUAGE_SYSTEM => ['name' => 'size'],
                                ],
                            ],
                            'translations' => [
                                Defaults::LANGUAGE_SYSTEM => ['name' => 'xl'],
                            ],
                        ],
                        [
                            'id' => $ids->get('slim-fit'),
                            'group' => [
                                'id' => $ids->get('fit'),
                                'translations' => [
                                    Defaults::LANGUAGE_SYSTEM => ['name' => 'fit'],
                                ],
                            ],
                            'translations' => [
                                Defaults::LANGUAGE_SYSTEM => ['name' => 'slim fit'],
                            ],
                        ],
                    ],
                ]),
                [
                    ['group' => 'color', 'option' => 'red'],
                    ['group' => 'size', 'option' => 'xl'],
                    ['group' => 'fit', 'option' => 'slim fit'],
                ],
                [$ids->get('language'), 'de-DE', Defaults::LANGUAGE_SYSTEM],
                (new Criteria())->addAssociation('options.group'),
                true,
                $ids->get('language'),
            ],
            5 => [
                array_merge($defaults, [
                    'options' => [
                        [
                            'id' => $ids->get('red'),
                            'group' => [
                                'id' => $ids->get('color'),
                                'translations' => [
                                    Defaults::LANGUAGE_SYSTEM => ['name' => 'color'],
                                    'de-DE' => ['name' => 'farbe'],
                                ],
                            ],
                            'translations' => [
                                Defaults::LANGUAGE_SYSTEM => ['name' => 'red'],
                                $ids->get('language') => ['name' => 'der'],
                            ],
                        ],
                        [
                            'id' => $ids->get('xl'),
                            'group' => [
                                'id' => $ids->get('size'),
                                'translations' => [
                                    Defaults::LANGUAGE_SYSTEM => ['name' => 'size'],
                                    'de-DE' => ['name' => 'größe'],
                                ],
                            ],
                            'translations' => [
                                Defaults::LANGUAGE_SYSTEM => ['name' => 'xl'],
                                $ids->get('language') => ['name' => 'lx'],
                            ],
                        ],
                        [
                            'id' => $ids->get('slim-fit'),
                            'group' => [
                                'id' => $ids->get('fit'),
                                'translations' => [
                                    Defaults::LANGUAGE_SYSTEM => ['name' => 'fit'],
                                    'de-DE' => ['name' => 'passform'],
                                ],
                            ],
                            'translations' => [
                                Defaults::LANGUAGE_SYSTEM => ['name' => 'slim fit'],
                                $ids->get('language') => ['name' => 'tif mils'],
                            ],
                        ],
                    ],
                ]),
                [
                    ['group' => 'farbe', 'option' => 'der'],
                    ['group' => 'größe', 'option' => 'lx'],
                    ['group' => 'passform', 'option' => 'tif mils'],
                ],
                [$ids->get('language'), 'de-DE', Defaults::LANGUAGE_SYSTEM],
                (new Criteria())->addAssociation('options.group'),
                true,
                $ids->get('language'),
            ],
            6 => [
                array_merge($defaults, [
                    'options' => [
                        [
                            'id' => $ids->get('red'),
                            'name' => 'red',
                            'group' => ['id' => $ids->get('color'), 'name' => 'color'],
                        ],
                        [
                            'id' => $ids->get('xl'),
                            'name' => 'xl',
                            'group' => ['id' => $ids->get('size'), 'name' => 'size'],
                        ],
                        [
                            'id' => $ids->get('slim-fit'),
                            'name' => 'slim fit',
                            'group' => ['id' => $ids->get('fit'), 'name' => 'fit'],
                        ],
                    ],
                    'configuratorGroupConfig' => [
                        [
                            'id' => $ids->get('color'),
                            'representation' => 'box',
                            'expressionForListings' => true,
                        ],
                        [
                            'id' => $ids->get('size'),
                            'representation' => 'box',
                            'expressionForListings' => true,
                        ],
                        [
                            'id' => $ids->get('fit'),
                            'representation' => 'box',
                            'expressionForListings' => true,
                        ],
                    ],
                ]),
                [
                    ['group' => 'color', 'option' => 'red'],
                    ['group' => 'fit', 'option' => 'slim fit'],
                    ['group' => 'size', 'option' => 'xl'],
                ],
                [Defaults::LANGUAGE_SYSTEM],
                (new Criteria())->addAssociation('options.group'),
                false,
                $ids->get('language'),
            ],
            7 => [
                array_merge($defaults, [
                    'options' => [
                        [
                            'id' => $ids->get('red'),
                            'group' => [
                                'id' => $ids->get('color'),
                                'translations' => [
                                    Defaults::LANGUAGE_SYSTEM => ['name' => 'color'],
                                    'de-DE' => ['name' => 'farbe'],
                                ],
                            ],
                            'translations' => [
                                Defaults::LANGUAGE_SYSTEM => ['name' => 'red'],
                                'de-DE' => ['name' => 'rot'],
                            ],
                        ],
                        [
                            'id' => $ids->get('xl'),
                            'group' => [
                                'id' => $ids->get('size'),
                                'translations' => [
                                    Defaults::LANGUAGE_SYSTEM => ['name' => 'size'],
                                    'de-DE' => ['name' => 'größe'],
                                ],
                            ],
                            'translations' => [
                                Defaults::LANGUAGE_SYSTEM => ['name' => 'xl'],
                                'de-DE' => ['name' => 'extra gross'],
                            ],
                        ],
                        [
                            'id' => $ids->get('slim-fit'),
                            'group' => [
                                'id' => $ids->get('fit'),
                                'translations' => [
                                    Defaults::LANGUAGE_SYSTEM => ['name' => 'fit'],
                                    'de-DE' => ['name' => 'passform'],
                                ],
                            ],
                            'translations' => [
                                Defaults::LANGUAGE_SYSTEM => ['name' => 'slim fit'],
                                'de-DE' => ['name' => 'schmal'],
                            ],
                        ],
                    ],
                    'configuratorGroupConfig' => [
                        [
                            'id' => $ids->get('color'),
                            'representation' => 'box',
                            'expressionForListings' => true,
                        ],
                        [
                            'id' => $ids->get('size'),
                            'representation' => 'box',
                            'expressionForListings' => true,
                        ],
                        [
                            'id' => $ids->get('fit'),
                            'representation' => 'box',
                            'expressionForListings' => true,
                        ],
                    ],
                ]),
                [
                    ['group' => 'farbe', 'option' => 'rot'],
                    ['group' => 'größe', 'option' => 'extra gross'],
                    ['group' => 'passform', 'option' => 'schmal'],
                ],
                ['de-DE', Defaults::LANGUAGE_SYSTEM],
                (new Criteria())->addAssociation('options.group'),
                false,
                $ids->get('language'),
            ],
            8 => [
                array_merge($defaults, [
                    'options' => [
                        [
                            'id' => $ids->get('red'),
                            'group' => [
                                'id' => $ids->get('color'),
                                'translations' => [
                                    Defaults::LANGUAGE_SYSTEM => ['name' => 'color'],
                                ],
                            ],
                            'translations' => [
                                Defaults::LANGUAGE_SYSTEM => ['name' => 'red'],
                            ],
                        ],
                        [
                            'id' => $ids->get('xl'),
                            'group' => [
                                'id' => $ids->get('size'),
                                'translations' => [
                                    Defaults::LANGUAGE_SYSTEM => ['name' => 'size'],
                                ],
                            ],
                            'translations' => [
                                Defaults::LANGUAGE_SYSTEM => ['name' => 'xl'],
                            ],
                        ],
                        [
                            'id' => $ids->get('slim-fit'),
                            'group' => [
                                'id' => $ids->get('fit'),
                                'translations' => [
                                    Defaults::LANGUAGE_SYSTEM => ['name' => 'fit'],
                                ],
                            ],
                            'translations' => [
                                Defaults::LANGUAGE_SYSTEM => ['name' => 'slim fit'],
                            ],
                        ],
                    ],
                    'configuratorGroupConfig' => [
                        [
                            'id' => $ids->get('color'),
                            'representation' => 'box',
                            'expressionForListings' => true,
                        ],
                        [
                            'id' => $ids->get('size'),
                            'representation' => 'box',
                            'expressionForListings' => true,
                        ],
                        [
                            'id' => $ids->get('fit'),
                            'representation' => 'box',
                            'expressionForListings' => true,
                        ],
                    ],
                ]),
                [
                    ['group' => 'color', 'option' => 'red'],
                    ['group' => 'fit', 'option' => 'slim fit'],
                    ['group' => 'size', 'option' => 'xl'],
                ],
                ['de-DE', Defaults::LANGUAGE_SYSTEM],
                (new Criteria())->addAssociation('options.group'),
                false,
                $ids->get('language'),
            ],
            9 => [
                array_merge($defaults, [
                    'options' => [
                        [
                            'id' => $ids->get('red'),
                            'group' => [
                                'id' => $ids->get('color'),
                                'translations' => [
                                    Defaults::LANGUAGE_SYSTEM => ['name' => 'color'],
                                ],
                            ],
                            'translations' => [
                                Defaults::LANGUAGE_SYSTEM => ['name' => 'red'],
                            ],
                        ],
                        [
                            'id' => $ids->get('xl'),
                            'group' => [
                                'id' => $ids->get('size'),
                                'translations' => [
                                    Defaults::LANGUAGE_SYSTEM => ['name' => 'size'],
                                ],
                            ],
                            'translations' => [
                                Defaults::LANGUAGE_SYSTEM => ['name' => 'xl'],
                            ],
                        ],
                        [
                            'id' => $ids->get('slim-fit'),
                            'group' => [
                                'id' => $ids->get('fit'),
                                'translations' => [
                                    Defaults::LANGUAGE_SYSTEM => ['name' => 'fit'],
                                ],
                            ],
                            'translations' => [
                                Defaults::LANGUAGE_SYSTEM => ['name' => 'slim fit'],
                            ],
                        ],
                    ],
                    'configuratorGroupConfig' => [
                        [
                            'id' => $ids->get('color'),
                            'representation' => 'box',
                            'expressionForListings' => true,
                        ],
                        [
                            'id' => $ids->get('size'),
                            'representation' => 'box',
                            'expressionForListings' => true,
                        ],
                        [
                            'id' => $ids->get('fit'),
                            'representation' => 'box',
                            'expressionForListings' => true,
                        ],
                    ],
                ]),
                [
                    ['group' => 'color', 'option' => 'red'],
                    ['group' => 'fit', 'option' => 'slim fit'],
                    ['group' => 'size', 'option' => 'xl'],
                ],
                [$ids->get('language'), 'de-DE', Defaults::LANGUAGE_SYSTEM],
                (new Criteria())->addAssociation('options.group'),
                false,
                $ids->get('language'),
            ],
            10 => [
                array_merge($defaults, [
                    'options' => [
                        [
                            'id' => $ids->get('red'),
                            'group' => [
                                'id' => $ids->get('color'),
                                'translations' => [
                                    Defaults::LANGUAGE_SYSTEM => ['name' => 'color'],
                                    $ids->get('language') => ['name' => 'foo'],
                                ],
                            ],
                            'translations' => [
                                Defaults::LANGUAGE_SYSTEM => ['name' => 'red'],
                                $ids->get('language') => ['name' => 'der'],
                            ],
                        ],
                        [
                            'id' => $ids->get('xl'),
                            'group' => [
                                'id' => $ids->get('size'),
                                'translations' => [
                                    Defaults::LANGUAGE_SYSTEM => ['name' => 'size'],
                                    $ids->get('language') => ['name' => 'bar'],
                                ],
                            ],
                            'translations' => [
                                Defaults::LANGUAGE_SYSTEM => ['name' => 'xl'],
                                $ids->get('language') => ['name' => 'lx'],
                            ],
                        ],
                        [
                            'id' => $ids->get('slim-fit'),
                            'group' => [
                                'id' => $ids->get('fit'),
                                'translations' => [
                                    Defaults::LANGUAGE_SYSTEM => ['name' => 'fit'],
                                    $ids->get('language') => ['name' => 'baz'],
                                ],
                            ],
                            'translations' => [
                                Defaults::LANGUAGE_SYSTEM => ['name' => 'slim fit'],
                                $ids->get('language') => ['name' => 'tif mils'],
                            ],
                        ],
                    ],
                    'configuratorGroupConfig' => [
                        [
                            'id' => $ids->get('color'),
                            'representation' => 'box',
                            'expressionForListings' => true,
                        ],
                        [
                            'id' => $ids->get('size'),
                            'representation' => 'box',
                            'expressionForListings' => true,
                        ],
                        [
                            'id' => $ids->get('fit'),
                            'representation' => 'box',
                            'expressionForListings' => true,
                        ],
                    ],
                ]),
                [
                    ['group' => 'bar', 'option' => 'lx'],
                    ['group' => 'baz', 'option' => 'tif mils'],
                    ['group' => 'foo', 'option' => 'der'],
                ],
                [$ids->get('language'), 'de-DE', Defaults::LANGUAGE_SYSTEM],
                (new Criteria())->addAssociation('options.group'),
                false,
                $ids->get('language'),
            ],
            11 => [
                array_merge($defaults, [
                    'options' => [
                        [
                            'id' => $ids->get('red'),
                            'name' => 'red',
                            'group' => ['id' => $ids->get('color'), 'name' => 'color'],
                        ],
                        [
                            'id' => $ids->get('xl'),
                            'name' => 'xl',
                            'group' => ['id' => $ids->get('size'), 'name' => 'size'],
                        ],
                        [
                            'id' => $ids->get('slim-fit'),
                            'name' => 'slim fit',
                            'group' => ['id' => $ids->get('fit'), 'name' => 'fit'],
                        ],
                    ],
                    'configuratorGroupConfig' => [
                        [
                            'id' => $ids->get('fit'),
                            'representation' => 'box',
                            'expressionForListings' => true,
                        ],
                        [
                            'id' => $ids->get('color'),
                            'representation' => 'box',
                            'expressionForListings' => true,
                        ],
                        [
                            'id' => $ids->get('size'),
                            'representation' => 'box',
                            'expressionForListings' => true,
                        ],
                    ],
                ]),
                [
                    ['group' => 'color', 'option' => 'red'],
                    ['group' => 'fit', 'option' => 'slim fit'],
                    ['group' => 'size', 'option' => 'xl'],
                ],
                [Defaults::LANGUAGE_SYSTEM],
                (new Criteria())->addAssociation('options.group'),
                false,
                $ids->get('language'),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $blog
     * @param list<array<string, string>> $expected
     */
    #[DataProvider('optionCases')]
    public function testOptionSorting(array $blog, array $expected, Criteria $criteria): void
    {
        $blogId = $blog['id'];
        $context = Context::createDefaultContext();

        static::getContainer()->get('blog.repository')
            ->create([$blog], $context);

        $context = new Context(
            new SystemSource(),
            [],
            Defaults::CURRENCY,
            [Defaults::LANGUAGE_SYSTEM]
        );

        $criteria->setIds([$blogId]);

        /** @var BlogEntity $blogEntity */
        $blogEntity = static::getContainer()
            ->get('blog.repository')
            ->search($criteria, $context)
            ->getEntities()
            ->first();

        /** @var PropertyGroupOptionCollection $options */
        $options = $blogEntity->getOptions();

        static::assertInstanceOf(PropertyGroupOptionCollection::class, $options);

        $names = $options->map(static fn (PropertyGroupOptionEntity $option) => [
            'name' => $option->getName(),
        ]);

        static::assertSame($expected, \array_values($names));
    }

    /**
     * @return list<array{array<string, mixed>, list<array<string, string>>, Criteria}>
     */
    public static function optionCases(): array
    {
        $ids = new IdsCollection();

        $defaults = [
            'id' => $ids->get('blog'),
            'name' => 'test-blog',
            'blogNumber' => $ids->get('blog'),
            'stock' => 10,
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false],
            ],
            'tax' => ['name' => 'test', 'taxRate' => 15],
        ];

        $optionsAscCriteria = (new Criteria())->addAssociation('options.group');
        $optionsAscCriteria->getAssociation('options')->addSorting(new FieldSorting('name', 'ASC'));

        $optionsDescCriteria = (new Criteria())->addAssociation('options.group');
        $optionsDescCriteria->getAssociation('options')->addSorting(new FieldSorting('name', 'DESC'));

        return [
            [
                array_merge($defaults, [
                    'options' => [
                        [
                            'id' => $ids->get('red'),
                            'name' => 'red',
                            'group' => ['id' => $ids->get('color'), 'name' => 'color'],
                        ],
                        [
                            'id' => $ids->get('xl'),
                            'name' => 'xl',
                            'group' => ['id' => $ids->get('size'), 'name' => 'size'],
                        ],
                        [
                            'id' => $ids->get('slim-fit'),
                            'name' => 'slim fit',
                            'group' => ['id' => $ids->get('fit'), 'name' => 'fit'],
                        ],
                    ],
                ]),
                [
                    ['name' => 'red'],
                    ['name' => 'slim fit'],
                    ['name' => 'xl'],
                ],
                $optionsAscCriteria,
            ],
            [
                array_merge($defaults, [
                    'options' => [
                        [
                            'id' => $ids->get('red'),
                            'name' => 'red',
                            'group' => ['id' => $ids->get('color'), 'name' => 'color'],
                        ],
                        [
                            'id' => $ids->get('xl'),
                            'name' => 'xl',
                            'group' => ['id' => $ids->get('size'), 'name' => 'size'],
                        ],
                        [
                            'id' => $ids->get('slim-fit'),
                            'name' => 'slim fit',
                            'group' => ['id' => $ids->get('fit'), 'name' => 'fit'],
                        ],
                    ],
                ]),
                [
                    ['name' => 'xl'],
                    ['name' => 'slim fit'],
                    ['name' => 'red'],
                ],
                $optionsDescCriteria,
            ],
        ];
    }

    public function testListPrices(): void
    {
        $ids = new IdsCollection();

        $taxId = static::getContainer()->get(Connection::class)
            ->fetchOne('SELECT LOWER(HEX(id)) FROM tax LIMIT 1');

        static::getContainer()->get('currency.repository')
            ->create([
                [
                    'id' => $ids->create('currency'),
                    'name' => 'test',
                    'shortName' => 'test',
                    'factor' => 1.5,
                    'symbol' => 'XXX',
                    'isoCode' => 'XX',
                    'decimalPrecision' => 3,
                    'itemRounding' => $this->objectToArray(new CashRoundingConfig(3, 0.01, true)),
                    'totalRounding' => $this->objectToArray(new CashRoundingConfig(3, 0.01, true)),
                ],
            ], Context::createDefaultContext());

        $defaults = [
            'id' => 1,
            'name' => 'test',
            'stock' => 10,
            'taxId' => $taxId,
            'visibilities' => [
                ['channelId' => TestDefaults::CHANNEL, 'visibility' => BlogVisibilityDefinition::VISIBILITY_ALL],
            ],
        ];

        $cases = [
            new ListPriceTestCase(100, 90, 200, 90, 50, CartPrice::TAX_STATE_GROSS, -100, 100, 200),
            new ListPriceTestCase(100, 90, 200, 135, 33.33, CartPrice::TAX_STATE_NET, -45, 90, 135),
            new ListPriceTestCase(100, 90, 200, 135, 33.33, CartPrice::TAX_STATE_FREE, -45, 90, 135),

            new ListPriceTestCase(100, 90, 200, 90, 50, CartPrice::TAX_STATE_GROSS, -100, 100, 200, $ids->get('currency'), $ids->get('currency')),
            new ListPriceTestCase(100, 90, 200, 135, 33.33, CartPrice::TAX_STATE_NET, -45, 90, 135, $ids->get('currency'), $ids->get('currency')),
            new ListPriceTestCase(100, 90, 200, 135, 33.33, CartPrice::TAX_STATE_FREE, -45, 90, 135, $ids->get('currency'), $ids->get('currency')),

            new ListPriceTestCase(100, 90, 200, 90, 50, CartPrice::TAX_STATE_GROSS, -150, 150, 300, Defaults::CURRENCY, $ids->get('currency')),
            new ListPriceTestCase(100, 90, 200, 135, 33.33, CartPrice::TAX_STATE_NET, -67.5, 135, 202.5, Defaults::CURRENCY, $ids->get('currency')),
            new ListPriceTestCase(100, 90, 200, 135, 33.33, CartPrice::TAX_STATE_FREE, -67.5, 135, 202.5, Defaults::CURRENCY, $ids->get('currency')),
        ];

        $context = static::getContainer()->get(ChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::CHANNEL);

        foreach ($cases as $i => $case) {
            // prepare currency factor calculation
            $factor = 1;
            if ($case->usedCurrency !== Defaults::CURRENCY) {
                $factor = 1.5;
            }

            $context->getContext()->assign(['currencyFactor' => $factor]);
            $context->getCurrency()->setId($case->usedCurrency);

            // test different tax states
            $context->setTaxState($case->taxState);

            // create a new blog for this case
            $id = $ids->create('blog-' . $i);

            $price = [
                [
                    'currencyId' => $case->currencyId,
                    'gross' => $case->gross,
                    'net' => $case->net,
                    'linked' => false,
                    'listPrice' => [
                        'gross' => $case->wasGross,
                        'net' => $case->wasNet,
                        'linked' => false,
                    ],
                ],
            ];
            if ($case->currencyId !== Defaults::CURRENCY) {
                $price[] = [
                    'currencyId' => Defaults::CURRENCY,
                    'gross' => 1,
                    'net' => 1,
                    'linked' => false,
                ];
            }

            $data = array_merge($defaults, [
                'id' => $id,
                'blogNumber' => $id,
                'price' => $price,
            ]);

            static::getContainer()->get('blog.repository')
                ->create([$data], Context::createDefaultContext());

            $blog = static::getContainer()->get('channel.blog.repository')
                ->search(new Criteria([$id]), $context)
                ->getEntities()
                ->get($id);

            static::assertInstanceOf(ChannelBlogEntity::class, $blog);

            $price = $blog->getCalculatedPrice();

            static::assertInstanceOf(ListPrice::class, $price->getListPrice());

            static::assertSame($case->expectedPrice, $price->getUnitPrice());
            static::assertSame($case->expectedWas, $price->getListPrice()->getPrice());

            static::assertSame($case->percentage, $price->getListPrice()->getPercentage());
            static::assertSame($case->discount, $price->getListPrice()->getDiscount());

            $partialCriteria = new Criteria([$id]);
            $partialCriteria->addFields(['price', 'taxId']);
            $blog = static::getContainer()->get('channel.blog.repository')
                ->search($partialCriteria, $context)
                ->getEntities()
                ->get($id);

            static::assertInstanceOf(PartialEntity::class, $blog);

            $price = $blog->get('calculatedPrice');

            static::assertInstanceOf(CalculatedPrice::class, $price);
            static::assertInstanceOf(ListPrice::class, $price->getListPrice());

            static::assertSame($case->expectedPrice, $price->getUnitPrice());
            static::assertSame($case->expectedWas, $price->getListPrice()->getPrice());

            static::assertSame($case->percentage, $price->getListPrice()->getPercentage());
            static::assertSame($case->discount, $price->getListPrice()->getDiscount());
        }
    }

    /**
     * @throws \JsonException
     *
     * @return array<mixed>
     */
    private function objectToArray(object $obj): array
    {
        $jsonString = \json_encode($obj, \JSON_THROW_ON_ERROR);

        return \json_decode($jsonString, true, 512, \JSON_THROW_ON_ERROR);
    }
}

/**
 * @internal
 */
class ListPriceTestCase
{
    public function __construct(
        public float $gross,
        public float $net,
        public float $wasGross,
        public float $wasNet,
        public float $percentage,
        public string $taxState,
        public float $discount,
        public float $expectedPrice,
        public float $expectedWas,
        public string $currencyId = Defaults::CURRENCY,
        public string $usedCurrency = Defaults::CURRENCY
    ) {
    }
}
