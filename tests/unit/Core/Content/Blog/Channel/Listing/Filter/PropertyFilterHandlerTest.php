<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\Channel\Listing\Filter;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\Channel\Listing\BlogListingResult;
use Contena\Core\Content\Blog\Channel\Listing\Filter;
use Contena\Core\Content\Blog\Channel\Listing\Filter\PropertyListingFilterHandler;
use Contena\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
use Contena\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionDefinition;
use Contena\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionEntity;
use Contena\Core\Content\Property\PropertyGroupCollection;
use Contena\Core\Content\Property\PropertyGroupDefinition;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\FilterAggregation;
use Contena\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\TermsAggregation;
use Contena\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Contena\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\Bucket;
use Contena\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\TermsResult;
use Contena\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\EntityResult;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\AndFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Context\LanguageInfo;
use Contena\Core\Test\Generator;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(PropertyListingFilterHandler::class)]
class PropertyFilterHandlerTest extends TestCase
{
    public function testDeactivateFilter(): void
    {
        $request = new Request([], ['property-filter' => false]);
        $request->setMethod(Request::METHOD_POST);
        $context = static::createStub(ChannelContext::class);
        $connection = $this->createMock(Connection::class);

        $connection->expects($this->never())
            ->method('fetchAllAssociative');

        $handler = $this->getHandlerWithConnection($connection);

        $result = $handler->create($request, $context);

        static::assertNull($result);
    }

    public function testEmptyRequest(): void
    {
        $request = new Request([], ['properties' => '']);
        $request->setMethod(Request::METHOD_POST);
        $context = static::createStub(ChannelContext::class);
        $connection = $this->createMock(Connection::class);

        $connection->expects($this->never())
            ->method('fetchAllAssociative');

        $handler = $this->getHandlerWithConnection($connection);

        $result = $handler->create($request, $context);

        $expected = new Filter(
            'properties',
            false,
            [
                new TermsAggregation('properties', 'blog.properties.id'),
                new TermsAggregation('options', 'blog.options.id'),
            ],
            new AndFilter(),
            [],
            false
        );

        static::assertEquals($expected, $result);
    }

    /**
     * @param array<string> $input
     * @param array<string> $expectedIds
     * @param array<array<string, string>> $mapping
     */
    #[DataProvider('createProvider')]
    public function testCreate(array $input, AndFilter $expectedFilter, array $expectedIds, array $mapping): void
    {
        $request = new Request([], ['properties' => implode('|', $input)]);

        $request->setMethod(Request::METHOD_POST);

        $context = static::createStub(ChannelContext::class);

        $connection = $this->createMock(Connection::class);

        $connection->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn($mapping);

        $handler = $this->getHandlerWithConnection($connection);

        $result = $handler->create($request, $context);

        $expected = new Filter(
            'properties',
            true,
            [
                new TermsAggregation('properties', 'blog.properties.id'),
                new TermsAggregation('options', 'blog.options.id'),
            ],
            $expectedFilter,
            $expectedIds,
            false
        );

        static::assertEquals($expected, $result);
    }

    public function testCreateWithInvalidIds(): void
    {
        $request = new Request([], ['properties' => 'foo|bar']);

        $request->setMethod(Request::METHOD_POST);

        $context = static::createStub(ChannelContext::class);

        $connection = static::createStub(Connection::class);

        $handler = $this->getHandlerWithConnection($connection);

        $result = $handler->create($request, $context);

        $expected = new Filter(
            'properties',
            false,
            [
                new TermsAggregation('properties', 'blog.properties.id'),
                new TermsAggregation('options', 'blog.options.id'),
            ],
            new AndFilter([]),
            [],
            false
        );

        static::assertEquals($expected, $result);
    }

    public function testPreFilteredGroups(): void
    {
        $request = new Request([], [PropertyListingFilterHandler::PROPERTY_GROUP_IDS_REQUEST_PARAM => ['color', 'size']]);
        $request->setMethod(Request::METHOD_POST);

        $context = static::createStub(ChannelContext::class);
        $connection = $this->createMock(Connection::class);

        $connection->expects($this->never())
            ->method('fetchAllAssociative');

        $handler = $this->getHandlerWithConnection($connection);

        $result = $handler->create($request, $context);

        $expected = new Filter(
            'properties',
            false,
            [
                new FilterAggregation(
                    'properties-filter',
                    new TermsAggregation('properties', 'blog.properties.id'),
                    [new EqualsAnyFilter('blog.properties.groupId', ['color', 'size'])]
                ),
                new FilterAggregation(
                    'options-filter',
                    new TermsAggregation('options', 'blog.options.id'),
                    [new EqualsAnyFilter('blog.options.groupId', ['color', 'size'])],
                ),
            ],
            new AndFilter(),
            [],
            false
        );

        static::assertEquals($expected, $result);
    }

    public function testProcess(): void
    {
        $request = new Request();
        $request->setMethod(Request::METHOD_POST);

        $languageInfo = new LanguageInfo(Generator::LANGUAGE_INFO_NAME, Generator::LANGUAGE_INFO_LOCALE_CODE);
        $context = Generator::generateChannelContext(languageInfo: $languageInfo);

        $groupRepository = new StaticEntityRepository([
            static function (Criteria $criteria) {
                static::assertContains('color', $criteria->getIds());
                static::assertContains('size', $criteria->getIds());

                return new PropertyGroupCollection([
                ]);
            },
            new PropertyGroupCollection(),
        ], new PropertyGroupDefinition());

        $repository = new StaticEntityRepository([
            static function (Criteria $criteria) {
                static::assertContains('red', $criteria->getIds());
                static::assertContains('green', $criteria->getIds());
                static::assertContains('xl', $criteria->getIds());
                static::assertContains('l', $criteria->getIds());

                return new PropertyGroupOptionCollection([
                    (new PropertyGroupOptionEntity())->assign([
                        'id' => 'red',
                        'groupId' => 'color',
                        'position' => 1,
                    ]),
                    (new PropertyGroupOptionEntity())->assign([
                        'id' => 'green',
                        'groupId' => 'color',
                        'position' => 2,
                    ]),
                    (new PropertyGroupOptionEntity())->assign([
                        'id' => 'xl',
                        'groupId' => 'size',
                        'position' => 2,
                    ]),
                    (new PropertyGroupOptionEntity())->assign([
                        'id' => 'l',
                        'groupId' => 'size',
                        'position' => 1,
                    ]),
                ]);
            },
            new PropertyGroupOptionCollection(),
        ], new PropertyGroupOptionDefinition());

        $handler = new PropertyListingFilterHandler(
            $groupRepository,
            $repository,
            static::createStub(Connection::class)
        );

        $result = new BlogListingResult(
            'test',
            1,
            new BlogCollection(),
            new AggregationResultCollection([
                new TermsResult('properties', [
                    new Bucket('red', 1, null),
                    new Bucket('green', 1, null),
                ]),
                new TermsResult('options', [
                    new Bucket('xl', 1, null),
                    new Bucket('l', 1, null),
                ]),
            ]),
            new Criteria(),
            Context::createDefaultContext()
        );

        $handler->process($request, $result, $context);

        static::assertTrue($result->getAggregations()->has('properties'));
        static::assertFalse($result->getAggregations()->has('options'));

        $properties = $result->getAggregations()->get('properties');

        static::assertInstanceOf(EntityResult::class, $properties);
        static::assertCount(2, $properties->getEntities());

        $color = $properties->getEntities()->first();
        static::assertInstanceOf(Entity::class, $color);
        static::assertSame('color', $color->get('id'));

        $options = $color->get('options');
        static::assertInstanceOf(EntityCollection::class, $options);
        static::assertCount(2, $options);

        static::assertInstanceOf(Entity::class, $options->first());
        static::assertSame('red', $options->first()->get('id'));
        static::assertInstanceOf(Entity::class, $options->last());
        static::assertSame('green', $options->last()->get('id'));

        $size = $properties->getEntities()->last();
        static::assertInstanceOf(Entity::class, $size);
        static::assertSame('size', $size->get('id'));

        $options = $size->get('options');
        static::assertInstanceOf(EntityCollection::class, $options);
        static::assertCount(2, $options);
        static::assertInstanceOf(Entity::class, $options->first());
        static::assertSame('l', $options->first()->get('id'));
        static::assertInstanceOf(Entity::class, $options->last());
        static::assertSame('xl', $options->last()->get('id'));
    }

    public static function createProvider(): \Generator
    {
        $ids = new IdsCollection();

        yield 'Test two groups and single option' => [
            // input for the request
            [$ids->get('XL'), $ids->get('green')],

            // expected filter
            new AndFilter([
                // each "group" should be an OR filter (e.g. size OR color)
                new OrFilter([
                    new EqualsAnyFilter('blog.optionIds', [$ids->get('XL')]),
                    new EqualsAnyFilter('blog.propertyIds', [$ids->get('XL')]),
                ]),
                new OrFilter([
                    new EqualsAnyFilter('blog.optionIds', [$ids->get('green')]),
                    new EqualsAnyFilter('blog.propertyIds', [$ids->get('green')]),
                ]),
            ]),

            // expected ids
            [$ids->get('XL'), $ids->get('green')],

            // mapping from the storage
            [
                ['property_group_id' => $ids->get('size'), 'id' => $ids->get('XL')],
                ['property_group_id' => $ids->get('color'), 'id' => $ids->get('green')],
            ],
        ];

        yield 'Test with single group and multiple options' => [
            [$ids->get('green'), $ids->get('red')],

            // expected filter
            new AndFilter([
                new OrFilter([
                    new EqualsAnyFilter('blog.optionIds', [$ids->get('green'), $ids->get('red')]),
                    new EqualsAnyFilter('blog.propertyIds', [$ids->get('green'), $ids->get('red')]),
                ]),
            ]),

            // expected ids
            [$ids->get('green'), $ids->get('red')],

            // mapping from the storage
            [
                ['property_group_id' => $ids->get('color'), 'id' => $ids->get('green')],
                ['property_group_id' => $ids->get('color'), 'id' => $ids->get('red')],
            ],
        ];

        yield 'Test with multiple groups and multiple options' => [
            [
                $ids->get('green'),
                $ids->get('red'),
                $ids->get('XL'),
                $ids->get('L'),
            ],

            // expected filter
            new AndFilter([
                new OrFilter([
                    new EqualsAnyFilter('blog.optionIds', [$ids->get('green'), $ids->get('red')]),
                    new EqualsAnyFilter('blog.propertyIds', [$ids->get('green'), $ids->get('red')]),
                ]),
                new OrFilter([
                    new EqualsAnyFilter('blog.optionIds', [$ids->get('XL'), $ids->get('L')]),
                    new EqualsAnyFilter('blog.propertyIds', [$ids->get('XL'), $ids->get('L')]),
                ]),
            ]),

            // expected ids
            [
                $ids->get('green'),
                $ids->get('red'),
                $ids->get('XL'),
                $ids->get('L'),
            ],

            // mapping from the storage
            [
                ['property_group_id' => $ids->get('color'), 'id' => $ids->get('green')],
                ['property_group_id' => $ids->get('color'), 'id' => $ids->get('red')],
                ['property_group_id' => $ids->get('size'), 'id' => $ids->get('XL')],
                ['property_group_id' => $ids->get('size'), 'id' => $ids->get('L')],
            ],
        ];

        yield 'Test two groups and single option with invalid id' => [
            // input for the request
            [$ids->get('XL'), $ids->get('green'), 'foo', 'bar'],

            // expected filter
            new AndFilter([
                // each "group" should be an OR filter (e.g. size OR color)
                new OrFilter([
                    new EqualsAnyFilter('blog.optionIds', [$ids->get('XL')]),
                    new EqualsAnyFilter('blog.propertyIds', [$ids->get('XL')]),
                ]),
                new OrFilter([
                    new EqualsAnyFilter('blog.optionIds', [$ids->get('green')]),
                    new EqualsAnyFilter('blog.propertyIds', [$ids->get('green')]),
                ]),
            ]),

            // expected ids
            [$ids->get('XL'), $ids->get('green')],

            // mapping from the storage
            [
                ['property_group_id' => $ids->get('size'), 'id' => $ids->get('XL')],
                ['property_group_id' => $ids->get('color'), 'id' => $ids->get('green')],
            ],
        ];
    }

    private function getHandlerWithConnection(Connection $connection): PropertyListingFilterHandler
    {
        $groupRepository = new StaticEntityRepository([], new PropertyGroupDefinition());
        $optionRepository = new StaticEntityRepository([], new PropertyGroupOptionDefinition());

        return new PropertyListingFilterHandler(
            $groupRepository,
            $optionRepository,
            $connection
        );
    }
}
