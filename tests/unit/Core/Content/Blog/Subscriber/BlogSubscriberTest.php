<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\Subscriber;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\AbstractPropertyGroupSorter;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Content\Blog\BlogMaxPurchaseCalculator;
use Contena\Core\Content\Blog\BlogVariationBuilder;
use Contena\Core\Content\Blog\Channel\ChannelBlogDefinition;
use Contena\Core\Content\Blog\Channel\ChannelBlogEntity;
use Contena\Core\Content\Blog\Channel\Price\AbstractBlogPriceCalculator;
use Contena\Core\Content\Blog\DataAbstractionLayer\CheapestPrice\CheapestPriceContainer;
use Contena\Core\Content\Blog\IsNewDetector;
use Contena\Core\Content\Blog\Subscriber\BlogSubscriber;
use Contena\Core\Content\Category\CategoryEntity;
use Contena\Core\Content\MeasurementSystem\BlogMeasurement\BlogMeasurementEnum;
use Contena\Core\Content\MeasurementSystem\BlogMeasurement\BlogMeasurementUnitBuilder;
use Contena\Core\Content\MeasurementSystem\Unit\AbstractMeasurementUnitConverter;
use Contena\Core\Content\MeasurementSystem\Unit\ConvertedUnit;
use Contena\Core\Content\MeasurementSystem\Unit\ConvertedUnitSet;
use Contena\Core\Defaults;
use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityDeleteEvent;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWriteEvent;
use Contena\Core\Framework\DataAbstractionLayer\PartialEntity;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Context\LanguageInfo;
use Contena\Core\System\Channel\Entity\ChannelEntityLoadedEvent;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\Test\Generator;
use Contena\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[CoversClass(BlogSubscriber::class)]
class BlogSubscriberTest extends TestCase
{
    private const CONFIG = BlogDefinition::CONFIG_KEY_DEFAULT_CMS_PAGE_BLOG;

    #[DataProvider('resolveCmsPageIdProviderWithLoadedEventProvider')]
    public function testResolveCmsPageIdProviderWithLoadedEvent(Entity $entity, SystemConfigService $config, ?string $expected): void
    {
        $subscriber = new BlogSubscriber(
            static::createStub(BlogVariationBuilder::class),
            static::createStub(AbstractBlogPriceCalculator::class),
            static::createStub(AbstractPropertyGroupSorter::class),
            static::createStub(BlogMaxPurchaseCalculator::class),
            static::createStub(IsNewDetector::class),
            $config,
            static::createStub(BlogMeasurementUnitBuilder::class),
            static::createStub(AbstractMeasurementUnitConverter::class),
            new RequestStack(),
            static::createStub(Connection::class)
        );

        /** @var EntityLoadedEvent<BlogEntity|PartialEntity> $event */
        $event = new EntityLoadedEvent(
            static::createStub(BlogDefinition::class),
            [$entity],
            Context::createDefaultContext()
        );

        $subscriber->loaded($event);

        static::assertSame($expected, $entity->get('cmsPageId'));
    }

    #[DataProvider('resolveCmsPageIdProviderWithChannelLoadedEventProvider')]
    public function testResolveCmsPageIdProviderWithChannelLoadedEvent(Entity $entity, SystemConfigService $config, ?string $expected): void
    {
        $subscriber = new BlogSubscriber(
            static::createStub(BlogVariationBuilder::class),
            static::createStub(AbstractBlogPriceCalculator::class),
            static::createStub(AbstractPropertyGroupSorter::class),
            static::createStub(BlogMaxPurchaseCalculator::class),
            static::createStub(IsNewDetector::class),
            $config,
            static::createStub(BlogMeasurementUnitBuilder::class),
            static::createStub(AbstractMeasurementUnitConverter::class),
            new RequestStack(),
            static::createStub(Connection::class)
        );

        /** @var ChannelEntityLoadedEvent<BlogEntity|PartialEntity> $event */
        $event = new ChannelEntityLoadedEvent(
            static::createStub(ChannelBlogDefinition::class),
            [$entity],
            static::createStub(ChannelContext::class)
        );

        $subscriber->channelLoaded($event);

        static::assertSame($expected, $entity->get('cmsPageId'));
    }

    public static function resolveCmsPageIdProviderWithLoadedEventProvider(): \Generator
    {
        yield 'It does not set cms page id if no blog entity given' => [
            (new CategoryEntity())->assign(['id' => Uuid::randomHex(), 'cmsPageId' => 'own-id']),
            new StaticSystemConfigService([self::CONFIG => 'config-id']),
            'own-id',
        ];

        yield 'It does not set cms page id if already given' => [
            (new BlogEntity())->assign(['id' => Uuid::randomHex(), 'cmsPageId' => 'own-id']),
            new StaticSystemConfigService([self::CONFIG => 'config-id']),
            'own-id',
        ];

        yield 'It does not set if no default is given' => [
            (new BlogEntity())->assign(['id' => Uuid::randomHex(), 'cmsPageId' => null]),
            new StaticSystemConfigService(),
            null,
        ];

        yield 'It sets cms page id if none is given and default is provided' => [
            (new BlogEntity())->assign(['id' => Uuid::randomHex(), 'cmsPageId' => null]),
            new StaticSystemConfigService([self::CONFIG => 'config-id']),
            'config-id',
        ];

        yield 'It does not set cms page id if already given with partial entity' => [
            (new PartialEntity())->assign(['id' => Uuid::randomHex(), 'cmsPageId' => 'own-id']),
            new StaticSystemConfigService([self::CONFIG => 'config-id']),
            'own-id',
        ];

        yield 'It does not set if no default is given with partial entity' => [
            (new PartialEntity())->assign(['id' => Uuid::randomHex(), 'cmsPageId' => null]),
            new StaticSystemConfigService(),
            null,
        ];

        yield 'It sets cms page id if none is given and default is provided with partial entity' => [
            (new PartialEntity())->assign(['id' => Uuid::randomHex(), 'cmsPageId' => null]),
            new StaticSystemConfigService([self::CONFIG => 'config-id']),
            'config-id',
        ];
    }

    public static function resolveCmsPageIdProviderWithChannelLoadedEventProvider(): \Generator
    {
        yield 'It does not set cms page id if already given' => [
            (new ChannelBlogEntity())->assign(['id' => Uuid::randomHex(), 'cmsPageId' => 'own-id']),
            new StaticSystemConfigService([self::CONFIG => 'config-id']),
            'own-id',
        ];

        yield 'It does not set if no default is given' => [
            (new ChannelBlogEntity())->assign(['id' => Uuid::randomHex(), 'cmsPageId' => null]),
            new StaticSystemConfigService(),
            null,
        ];

        yield 'It sets cms page id if none is given and default is provided' => [
            (new ChannelBlogEntity())->assign(['id' => Uuid::randomHex(), 'cmsPageId' => null]),
            new StaticSystemConfigService([self::CONFIG => 'config-id']),
            'config-id',
        ];

        yield 'It does not set cms page id if already given with partial entity' => [
            (new PartialEntity())->assign(['id' => Uuid::randomHex(), 'cmsPageId' => 'own-id']),
            new StaticSystemConfigService([self::CONFIG => 'config-id']),
            'own-id',
        ];

        yield 'It does not set if no default is given with partial entity' => [
            (new PartialEntity())->assign(['id' => Uuid::randomHex(), 'cmsPageId' => null]),
            new StaticSystemConfigService(),
            null,
        ];

        yield 'It sets cms page id if none is given and default is provided with partial entity' => [
            (new PartialEntity())->assign(['id' => Uuid::randomHex(), 'cmsPageId' => null]),
            new StaticSystemConfigService([self::CONFIG => 'config-id']),
            'config-id',
        ];
    }

    public function testEnsureServicesAreCalled(): void
    {
        $isNewDetector = $this->createMock(IsNewDetector::class);
        $isNewDetector->expects($this->once())->method('isNew');

        $maxPurchaseCalculator = $this->createMock(BlogMaxPurchaseCalculator::class);
        $maxPurchaseCalculator->expects($this->once())->method('calculate');

        $calculator = $this->createMock(AbstractBlogPriceCalculator::class);
        $calculator->expects($this->once())->method('calculate');

        $blogVariationBuilder = $this->createMock(BlogVariationBuilder::class);
        $blogVariationBuilder->expects($this->once())->method('build');

        $propertyGroupSorter = $this->createMock(AbstractPropertyGroupSorter::class);
        $propertyGroupSorter->expects($this->once())->method('sortUsingLocaleCode');

        $subscriber = new BlogSubscriber(
            $blogVariationBuilder,
            $calculator,
            $propertyGroupSorter,
            $maxPurchaseCalculator,
            $isNewDetector,
            static::createStub(SystemConfigService::class),
            static::createStub(BlogMeasurementUnitBuilder::class),
            static::createStub(AbstractMeasurementUnitConverter::class),
            new RequestStack(),
            static::createStub(Connection::class)
        );

        $cheapestPrice = new CheapestPriceContainer([]);

        $entity = (new PartialEntity())->assign([
            'id' => Uuid::randomHex(),
            'properties' => new EntityCollection(),
            'cheapestPrice' => $cheapestPrice,
        ]);

        $languageInfo = new LanguageInfo('English', 'en-GB');
        $channelContext = Generator::generateChannelContext(languageInfo: $languageInfo);

        /** @var ChannelEntityLoadedEvent<BlogEntity|PartialEntity> $event */
        $event = new ChannelEntityLoadedEvent(
            static::createStub(BlogDefinition::class),
            [$entity],
            $channelContext
        );

        $subscriber->channelLoaded($event);
    }

    public function testEnsurePartialsEventsConsidered(): void
    {
        $events = BlogSubscriber::getSubscribedEvents();
        static::assertArrayHasKey('blog.loaded', $events);
        static::assertArrayHasKey('blog.partial_loaded', $events);
        static::assertArrayHasKey('channel.blog.loaded', $events);
        static::assertArrayHasKey('channel.blog.partial_loaded', $events);
    }

    public function testLoadedWithAdminContextConvertsUnits(): void
    {
        $measurementUnitConverter = static::createStub(AbstractMeasurementUnitConverter::class);

        $measurementBuilder = $this->createMock(BlogMeasurementUnitBuilder::class);

        $requestStack = new RequestStack();
        $request = new Request();
        $request->headers = new HeaderBag([
            PlatformRequest::HEADER_MEASUREMENT_LENGTH_UNIT => 'ft',
            PlatformRequest::HEADER_MEASUREMENT_WEIGHT_UNIT => 'lb',
        ]);
        $requestStack->push($request);

        $subscriber = new BlogSubscriber(
            static::createStub(BlogVariationBuilder::class),
            static::createStub(AbstractBlogPriceCalculator::class),
            static::createStub(AbstractPropertyGroupSorter::class),
            static::createStub(BlogMaxPurchaseCalculator::class),
            static::createStub(IsNewDetector::class),
            new StaticSystemConfigService(),
            $measurementBuilder,
            $measurementUnitConverter,
            $requestStack,
            static::createStub(Connection::class)
        );

        $blog = (new BlogEntity())->assign([
            'id' => Uuid::randomHex(),
            'width' => 10.0,
            'height' => 20.0,
            'length' => 30.0,
            'weight' => 5.0,
        ]);

        $measurementBuilder->expects($this->exactly(1))
            ->method('build')
            ->with($blog, 'ft', 'lb')
            ->willReturnCallback(static function (BlogEntity $blog, $from, $to) {
                // Simulate conversion logic
                // For the sake of this example, we will just double the value

                $converted = new ConvertedUnitSet();
                $converted->addUnit(BlogMeasurementEnum::WEIGHT->value, new ConvertedUnit((float) $blog->getWeight() * 2.0, $to));
                $converted->addUnit(BlogMeasurementEnum::WIDTH->value, new ConvertedUnit((float) $blog->getWidth() * 2.0, $to));
                $converted->addUnit(BlogMeasurementEnum::LENGTH->value, new ConvertedUnit((float) $blog->getLength() * 2.0, $to));
                $converted->addUnit(BlogMeasurementEnum::HEIGHT->value, new ConvertedUnit((float) $blog->getHeight() * 2.0, $to));

                return $converted;
            });

        $context = Context::createDefaultContext(new AdminApiSource('admin_user-id', 'integration-id'));

        /** @var EntityLoadedEvent<BlogEntity|PartialEntity> $event */
        $event = new EntityLoadedEvent(
            static::createStub(BlogDefinition::class),
            [$blog],
            $context
        );

        $subscriber->loaded($event);

        static::assertSame(20.0, $blog->get('width'));
        static::assertSame(40.0, $blog->get('height'));
        static::assertSame(60.0, $blog->get('length'));
        static::assertSame(10.0, $blog->get('weight'));
    }

    public function testLoadedWithNonAdminContextDoesNotConvertUnits(): void
    {
        $measurementUnitConverter = static::createStub(AbstractMeasurementUnitConverter::class);
        $measurementUnitBuilder = $this->createMock(BlogMeasurementUnitBuilder::class);
        $measurementUnitBuilder->expects($this->never())->method('build');

        $requestStack = new RequestStack();
        $request = new Request();
        $request->headers = new HeaderBag([
            PlatformRequest::HEADER_MEASUREMENT_LENGTH_UNIT => 'ft',
            PlatformRequest::HEADER_MEASUREMENT_WEIGHT_UNIT => 'lb',
        ]);
        $requestStack->push($request);

        $subscriber = new BlogSubscriber(
            static::createStub(BlogVariationBuilder::class),
            static::createStub(AbstractBlogPriceCalculator::class),
            static::createStub(AbstractPropertyGroupSorter::class),
            static::createStub(BlogMaxPurchaseCalculator::class),
            static::createStub(IsNewDetector::class),
            new StaticSystemConfigService(),
            $measurementUnitBuilder,
            $measurementUnitConverter,
            $requestStack,
            static::createStub(Connection::class)
        );

        $blog = (new BlogEntity())->assign([
            'id' => Uuid::randomHex(),
            'width' => 10.0,
            'height' => 20.0,
            'length' => 30.0,
            'weight' => 5.0,
        ]);

        $context = Context::createDefaultContext(); // Non-admin context

        /** @var EntityLoadedEvent<BlogEntity|PartialEntity> $event */
        $event = new EntityLoadedEvent(
            static::createStub(BlogDefinition::class),
            [$blog],
            $context
        );

        $subscriber->loaded($event);

        // Values should remain unchanged
        static::assertSame(10.0, $blog->get('width'));
        static::assertSame(20.0, $blog->get('height'));
        static::assertSame(30.0, $blog->get('length'));
        static::assertSame(5.0, $blog->get('weight'));
    }

    /**
     * @param array<string, float|string|null> $blogDimensions
     * @param array<string, string> $headers
     * @param array<string, ConvertedUnit> $expectedFinalValues
     */
    #[DataProvider('convertMeasurementUnitProvider')]
    public function testConvertMeasurementUnitWithVariousValues(
        array $blogDimensions,
        array $headers,
        int $expectedConversions,
        array $expectedFinalValues
    ): void {
        $measurementUnitConverter = static::createStub(AbstractMeasurementUnitConverter::class);
        $measurementBuilder = $this->createMock(BlogMeasurementUnitBuilder::class);

        $requestStack = new RequestStack();
        $request = new Request();
        $request->headers = new HeaderBag($headers);
        $requestStack->push($request);

        $blog = (new BlogEntity())->assign(array_merge(['id' => Uuid::randomHex()], $blogDimensions));

        if ($expectedConversions > 0) {
            $measurementBuilder->expects($this->exactly(1))
                ->method('build')
                ->with($blog, $headers[PlatformRequest::HEADER_MEASUREMENT_LENGTH_UNIT] ?? 'mm', $headers[PlatformRequest::HEADER_MEASUREMENT_WEIGHT_UNIT] ?? 'kg')
                ->willReturnCallback(static function (BlogEntity $blog, $from, $to) {
                    // Simulate conversion logic
                    // For the sake of this example, we will just double the value

                    $converted = new ConvertedUnitSet();
                    $converted->addUnit(BlogMeasurementEnum::WEIGHT->value, new ConvertedUnit((float) $blog->getWeight() * 2.0, $to));
                    $converted->addUnit(BlogMeasurementEnum::WIDTH->value, new ConvertedUnit((float) $blog->getWidth() * 2.0, $to));
                    $converted->addUnit(BlogMeasurementEnum::LENGTH->value, new ConvertedUnit((float) $blog->getLength() * 2.0, $to));
                    $converted->addUnit(BlogMeasurementEnum::HEIGHT->value, new ConvertedUnit((float) $blog->getHeight() * 2.0, $to));

                    return $converted;
                });
        } else {
            $measurementBuilder->expects($this->never())->method('build');
        }

        $subscriber = new BlogSubscriber(
            static::createStub(BlogVariationBuilder::class),
            static::createStub(AbstractBlogPriceCalculator::class),
            static::createStub(AbstractPropertyGroupSorter::class),
            static::createStub(BlogMaxPurchaseCalculator::class),
            static::createStub(IsNewDetector::class),
            new StaticSystemConfigService(),
            $measurementBuilder,
            $measurementUnitConverter,
            $requestStack,
            static::createStub(Connection::class)
        );

        $context = Context::createDefaultContext(new AdminApiSource('admin_user-id', 'integration-id'));

        /** @var EntityLoadedEvent<BlogEntity|PartialEntity> $event */
        $event = new EntityLoadedEvent(
            static::createStub(BlogDefinition::class),
            [$blog],
            $context
        );

        $subscriber->loaded($event);

        foreach ($expectedFinalValues as $field => $expectedValue) {
            static::assertSame($expectedValue->value, $blog->get($field), "Field {$field} does not match expected value");
        }
    }

    public static function convertMeasurementUnitProvider(): \Generator
    {
        yield 'No headers provided' => [
            'blogDimensions' => ['width' => 10.0, 'height' => 20.0, 'weight' => 5.0],
            'headers' => [],
            'expectedConversions' => 0,
            'expectedFinalValues' => [],
        ];

        yield 'Only length unit header provided' => [
            'blogDimensions' => ['width' => 10.0, 'height' => 20.0, 'length' => 30.0, 'weight' => 5.0],
            'headers' => [PlatformRequest::HEADER_MEASUREMENT_LENGTH_UNIT => 'ft'],
            'expectedConversions' => 3,
            'expectedFinalValues' => [
                'width' => new ConvertedUnit(20.0, 'mm'),
                'height' => new ConvertedUnit(40.0, 'mm'),
                'length' => new ConvertedUnit(60.0, 'mm'),
            ],
        ];

        yield 'Only weight unit header provided' => [
            'blogDimensions' => ['width' => 10.0, 'height' => 20.0, 'weight' => 5.0],
            'headers' => [PlatformRequest::HEADER_MEASUREMENT_WEIGHT_UNIT => 'lb'],
            'expectedConversions' => 1,
            'expectedFinalValues' => [
                'weight' => new ConvertedUnit(10.0, 'g'),
            ],
        ];

        yield 'Both unit headers provided' => [
            'blogDimensions' => ['width' => 10.0, 'height' => 20.0, 'length' => 30.0, 'weight' => 5.0],
            'headers' => [
                PlatformRequest::HEADER_MEASUREMENT_LENGTH_UNIT => 'ft',
                PlatformRequest::HEADER_MEASUREMENT_WEIGHT_UNIT => 'lb',
            ],
            'expectedConversions' => 4,
            'expectedFinalValues' => [
                'width' => new ConvertedUnit(20.0, 'mm'),
                'height' => new ConvertedUnit(40.0, 'mm'),
                'length' => new ConvertedUnit(60.0, 'mm'),
                'weight' => new ConvertedUnit(10.0, 'kg'),
            ],
        ];

        yield 'Zero values are not converted' => [
            'blogDimensions' => ['width' => 0.0, 'height' => 10.0, 'weight' => 0.0],
            'headers' => [
                PlatformRequest::HEADER_MEASUREMENT_LENGTH_UNIT => 'ft',
                PlatformRequest::HEADER_MEASUREMENT_WEIGHT_UNIT => 'lb',
            ],
            'expectedConversions' => 1,
            'expectedFinalValues' => [
                'height' => new ConvertedUnit(20.0, 'mm'),
            ],
        ];

        yield 'Null values are not converted' => [
            'blogDimensions' => ['width' => null, 'height' => 10.0, 'weight' => null],
            'headers' => [
                PlatformRequest::HEADER_MEASUREMENT_LENGTH_UNIT => 'ft',
                PlatformRequest::HEADER_MEASUREMENT_WEIGHT_UNIT => 'lb',
            ],
            'expectedConversions' => 1,
            'expectedFinalValues' => [
                'height' => new ConvertedUnit(20.0, 'mm'),
            ],
        ];

        yield 'Non-float values are not converted' => [
            'blogDimensions' => ['width' => '10', 'height' => 10.0, 'weight' => '5'],
            'headers' => [
                PlatformRequest::HEADER_MEASUREMENT_LENGTH_UNIT => 'ft',
                PlatformRequest::HEADER_MEASUREMENT_WEIGHT_UNIT => 'lb',
            ],
            'expectedConversions' => 1,
            'expectedFinalValues' => [
                'height' => new ConvertedUnit(20.0, 'mm'),
            ],
        ];

        yield 'Weight headers given but blog has no weight' => [
            'blogDimensions' => ['height' => 10.0],
            'headers' => [
                PlatformRequest::HEADER_MEASUREMENT_LENGTH_UNIT => 'ft',
                PlatformRequest::HEADER_MEASUREMENT_WEIGHT_UNIT => 'lb',
            ],
            'expectedConversions' => 1,
            'expectedFinalValues' => [
                'height' => new ConvertedUnit(20.0, 'mm'),
            ],
        ];
    }

    public function testLoadedWithNoRequestInStack(): void
    {
        $measurementUnitConverter = $this->createMock(AbstractMeasurementUnitConverter::class);
        $measurementUnitConverter->expects($this->never())->method('convert');

        $requestStack = new RequestStack(); // No request pushed

        $subscriber = new BlogSubscriber(
            static::createStub(BlogVariationBuilder::class),
            static::createStub(AbstractBlogPriceCalculator::class),
            static::createStub(AbstractPropertyGroupSorter::class),
            static::createStub(BlogMaxPurchaseCalculator::class),
            static::createStub(IsNewDetector::class),
            new StaticSystemConfigService(),
            static::createStub(BlogMeasurementUnitBuilder::class),
            $measurementUnitConverter,
            $requestStack,
            static::createStub(Connection::class)
        );

        $blog = (new BlogEntity())->assign([
            'id' => Uuid::randomHex(),
            'width' => 10.0,
            'height' => 20.0,
            'weight' => 5.0,
        ]);

        $context = Context::createDefaultContext(new AdminApiSource('admin_user-id', 'integration-id'));

        /** @var EntityLoadedEvent<BlogEntity|PartialEntity> $event */
        $event = new EntityLoadedEvent(
            static::createStub(BlogDefinition::class),
            [$blog],
            $context
        );

        $subscriber->loaded($event);

        // Values should remain unchanged when no request is in stack
        static::assertSame(10.0, $blog->get('width'));
        static::assertSame(20.0, $blog->get('height'));
        static::assertSame(5.0, $blog->get('weight'));
    }

    public function testBeforeWriteBlogWithMeasurementHeaders(): void
    {
        $measurementUnitConverter = $this->createMock(AbstractMeasurementUnitConverter::class);
        $measurementUnitConverter->expects($this->exactly(4))
            ->method('convert')
            ->willReturnCallback(static function ($value, $from, $to) {
                return new ConvertedUnit($value * 2.0, $to);
            });

        $requestStack = new RequestStack();
        $request = new Request();
        $request->headers = new HeaderBag([
            PlatformRequest::HEADER_MEASUREMENT_LENGTH_UNIT => 'ft',
            PlatformRequest::HEADER_MEASUREMENT_WEIGHT_UNIT => 'lb',
        ]);
        $requestStack->push($request);

        $subscriber = new BlogSubscriber(
            static::createStub(BlogVariationBuilder::class),
            static::createStub(AbstractBlogPriceCalculator::class),
            static::createStub(AbstractPropertyGroupSorter::class),
            static::createStub(BlogMaxPurchaseCalculator::class),
            static::createStub(IsNewDetector::class),
            new StaticSystemConfigService(),
            static::createStub(BlogMeasurementUnitBuilder::class),
            $measurementUnitConverter,
            $requestStack,
            static::createStub(Connection::class)
        );

        $command = $this->createMock(WriteCommand::class);

        $command->expects($this->once())
            ->method('getPayload')
            ->willReturn([
                'width' => 10.0,
                'height' => 20.0,
                'length' => 30.0,
                'weight' => 5.0,
            ]);

        $command->expects($this->exactly(4))
            ->method('hasField')
            ->willReturnCallback(static function ($field) {
                return \in_array($field, ['width', 'height', 'length', 'weight'], true);
            });

        $addPayloadCallCount = 0;
        $command->expects($this->exactly(4))
            ->method('addPayload')
            ->willReturnCallback(static function ($field, $value) use (&$addPayloadCallCount): void {
                $expectedValues = [
                    'width' => 20.0,
                    'height' => 40.0,
                    'length' => 60.0,
                    'weight' => 10.0,
                ];
                static::assertArrayHasKey($field, $expectedValues);
                static::assertSame($expectedValues[$field], $value);
                ++$addPayloadCallCount;
            });

        $event = $this->createMock(EntityWriteEvent::class);
        $event->expects($this->once())
            ->method('getCommandsForEntity')
            ->with(BlogDefinition::ENTITY_NAME)
            ->willReturn([$command]);

        $subscriber->beforeWriteBlog($event);
    }

    public function testBeforeWriteBlogWithNoHeaders(): void
    {
        $measurementUnitConverter = $this->createMock(AbstractMeasurementUnitConverter::class);
        $measurementUnitConverter->expects($this->never())->method('convert');

        $requestStack = new RequestStack();
        $request = new Request();
        $request->headers = new HeaderBag([]);
        $requestStack->push($request);

        $subscriber = new BlogSubscriber(
            static::createStub(BlogVariationBuilder::class),
            static::createStub(AbstractBlogPriceCalculator::class),
            static::createStub(AbstractPropertyGroupSorter::class),
            static::createStub(BlogMaxPurchaseCalculator::class),
            static::createStub(IsNewDetector::class),
            new StaticSystemConfigService(),
            static::createStub(BlogMeasurementUnitBuilder::class),
            $measurementUnitConverter,
            $requestStack,
            static::createStub(Connection::class)
        );

        $command = $this->createMock(WriteCommand::class);

        $command->expects($this->never())->method('getPayload');
        $command->expects($this->never())->method('hasField');
        $command->expects($this->never())->method('addPayload');

        $event = $this->createMock(EntityWriteEvent::class);
        $event->expects($this->never())
            ->method('getCommandsForEntity');

        $subscriber->beforeWriteBlog($event);
    }

    public function testBeforeWriteBlogWithNonBlogCommands(): void
    {
        $measurementUnitConverter = $this->createMock(AbstractMeasurementUnitConverter::class);
        $measurementUnitConverter->expects($this->never())->method('convert');

        $requestStack = new RequestStack();
        $request = new Request();
        $request->headers = new HeaderBag([
            PlatformRequest::HEADER_MEASUREMENT_LENGTH_UNIT => 'ft',
            PlatformRequest::HEADER_MEASUREMENT_WEIGHT_UNIT => 'lb',
        ]);
        $requestStack->push($request);

        $subscriber = new BlogSubscriber(
            static::createStub(BlogVariationBuilder::class),
            static::createStub(AbstractBlogPriceCalculator::class),
            static::createStub(AbstractPropertyGroupSorter::class),
            static::createStub(BlogMaxPurchaseCalculator::class),
            static::createStub(IsNewDetector::class),
            new StaticSystemConfigService(),
            static::createStub(BlogMeasurementUnitBuilder::class),
            $measurementUnitConverter,
            $requestStack,
            static::createStub(Connection::class)
        );

        $event = $this->createMock(EntityWriteEvent::class);
        $event->expects($this->once())
            ->method('getCommandsForEntity')
            ->with(BlogDefinition::ENTITY_NAME)
            ->willReturn([]);

        $subscriber->beforeWriteBlog($event);
    }

    /**
     * @param array<string, float|string> $payload
     * @param array<string, string> $headers
     * @param array<string, bool> $hasFieldReturns
     * @param array<string, float> $expectedConversions
     */
    #[DataProvider('beforeWriteBlogFieldProvider')]
    public function testBeforeWriteBlogWithVariousFieldTypes(
        array $payload,
        array $headers,
        array $hasFieldReturns,
        array $expectedConversions
    ): void {
        $measurementUnitConverter = $this->createMock(AbstractMeasurementUnitConverter::class);

        if ($expectedConversions !== []) {
            $measurementUnitConverter->expects($this->exactly(\count($expectedConversions)))
                ->method('convert')
                ->willReturn(new ConvertedUnit(2.0, 'm'));
        } else {
            $measurementUnitConverter->expects($this->never())->method('convert');
        }

        $requestStack = new RequestStack();
        $request = new Request();
        $request->headers = new HeaderBag($headers);
        $requestStack->push($request);

        $subscriber = new BlogSubscriber(
            static::createStub(BlogVariationBuilder::class),
            static::createStub(AbstractBlogPriceCalculator::class),
            static::createStub(AbstractPropertyGroupSorter::class),
            static::createStub(BlogMaxPurchaseCalculator::class),
            static::createStub(IsNewDetector::class),
            new StaticSystemConfigService(),
            static::createStub(BlogMeasurementUnitBuilder::class),
            $measurementUnitConverter,
            $requestStack,
            static::createStub(Connection::class)
        );

        $command = $this->createMock(WriteCommand::class);

        $command->expects($this->once())
            ->method('getPayload')
            ->willReturn($payload);

        $command->method('hasField')
            ->willReturnCallback(static function ($field) use ($hasFieldReturns) {
                return $hasFieldReturns[$field] ?? false;
            });

        if ($expectedConversions !== []) {
            $command->expects($this->exactly(\count($expectedConversions)))
                ->method('addPayload');
        } else {
            $command->expects($this->never())->method('addPayload');
        }

        $event = $this->createMock(EntityWriteEvent::class);
        $event->expects($this->once())
            ->method('getCommandsForEntity')
            ->with(BlogDefinition::ENTITY_NAME)
            ->willReturn([$command]);

        $subscriber->beforeWriteBlog($event);
    }

    public static function beforeWriteBlogFieldProvider(): \Generator
    {
        yield 'Only length header with width field' => [
            'payload' => ['width' => 10.0],
            'headers' => [PlatformRequest::HEADER_MEASUREMENT_LENGTH_UNIT => 'ft'],
            'hasFieldReturns' => ['width' => true],
            'expectedConversions' => ['width' => 20.0],
        ];

        yield 'Only weight header with weight field' => [
            'payload' => ['weight' => 5.0],
            'headers' => [PlatformRequest::HEADER_MEASUREMENT_WEIGHT_UNIT => 'lb'],
            'hasFieldReturns' => ['weight' => true],
            'expectedConversions' => ['weight' => 10.0],
        ];

        yield 'Non-float values are skipped' => [
            'payload' => ['width' => '10', 'height' => 20.0],
            'headers' => [PlatformRequest::HEADER_MEASUREMENT_LENGTH_UNIT => 'ft'],
            'hasFieldReturns' => ['width' => true, 'height' => true],
            'expectedConversions' => ['height' => 40.0],
        ];

        yield 'Missing fields are skipped' => [
            'payload' => ['width' => 10.0],
            'headers' => [PlatformRequest::HEADER_MEASUREMENT_LENGTH_UNIT => 'ft'],
            'hasFieldReturns' => ['width' => true, 'height' => false],
            'expectedConversions' => ['width' => 20.0],
        ];

        yield 'Both headers with mixed fields' => [
            'payload' => ['height' => 15.0, 'weight' => 3.0],
            'headers' => [
                PlatformRequest::HEADER_MEASUREMENT_LENGTH_UNIT => 'ft',
                PlatformRequest::HEADER_MEASUREMENT_WEIGHT_UNIT => 'lb',
            ],
            'hasFieldReturns' => ['height' => true, 'weight' => true],
            'expectedConversions' => ['height' => 30.0, 'weight' => 6.0],
        ];
    }

    public function testBeforeWriteBlogWithNoRequestInStack(): void
    {
        $measurementUnitConverter = $this->createMock(AbstractMeasurementUnitConverter::class);
        $measurementUnitConverter->expects($this->never())->method('convert');

        $requestStack = new RequestStack(); // No request pushed

        $subscriber = new BlogSubscriber(
            static::createStub(BlogVariationBuilder::class),
            static::createStub(AbstractBlogPriceCalculator::class),
            static::createStub(AbstractPropertyGroupSorter::class),
            static::createStub(BlogMaxPurchaseCalculator::class),
            static::createStub(IsNewDetector::class),
            new StaticSystemConfigService(),
            static::createStub(BlogMeasurementUnitBuilder::class),
            $measurementUnitConverter,
            $requestStack,
            static::createStub(Connection::class)
        );

        $command = $this->createMock(WriteCommand::class);

        $command->expects($this->never())->method('getPayload');
        $command->expects($this->never())->method('hasField');
        $command->expects($this->never())->method('addPayload');

        $event = $this->createMock(EntityWriteEvent::class);
        $event->expects($this->never())
            ->method('getCommandsForEntity');

        $subscriber->beforeWriteBlog($event);
    }

    public function testBeforeDeleteBlogWithNoDeletedBlogs(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchFirstColumn');
        $connection->expects($this->never())->method('executeStatement');

        $subscriber = new BlogSubscriber(
            static::createStub(BlogVariationBuilder::class),
            static::createStub(AbstractBlogPriceCalculator::class),
            static::createStub(AbstractPropertyGroupSorter::class),
            static::createStub(BlogMaxPurchaseCalculator::class),
            static::createStub(IsNewDetector::class),
            new StaticSystemConfigService(),
            static::createStub(BlogMeasurementUnitBuilder::class),
            static::createStub(AbstractMeasurementUnitConverter::class),
            new RequestStack(),
            $connection
        );

        $event = $this->createMock(EntityDeleteEvent::class);
        $event->expects($this->once())
            ->method('getIds')
            ->with(BlogDefinition::ENTITY_NAME)
            ->willReturn([]);

        $event->expects($this->never())->method('addSuccess');

        $subscriber->beforeDeleteBlog($event);
    }

    public function testBeforeDeleteBlogWithDeletedBlogsButNoParentIds(): void
    {
        $deletedId = Uuid::randomHex();
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchFirstColumn')
            ->willReturn([]);

        $connection->expects($this->never())->method('executeStatement');

        $subscriber = new BlogSubscriber(
            static::createStub(BlogVariationBuilder::class),
            static::createStub(AbstractBlogPriceCalculator::class),
            static::createStub(AbstractPropertyGroupSorter::class),
            static::createStub(BlogMaxPurchaseCalculator::class),
            static::createStub(IsNewDetector::class),
            new StaticSystemConfigService(),
            static::createStub(BlogMeasurementUnitBuilder::class),
            static::createStub(AbstractMeasurementUnitConverter::class),
            new RequestStack(),
            $connection
        );

        $event = $this->createMock(EntityDeleteEvent::class);
        $event->expects($this->once())
            ->method('getIds')
            ->with(BlogDefinition::ENTITY_NAME)
            ->willReturn([$deletedId]);

        $event->expects($this->never())->method('addSuccess');

        $subscriber->beforeDeleteBlog($event);
    }

    public function testBeforeDeleteBlogWithDeletedBlogsAndParentIds(): void
    {
        $deletedId = Uuid::randomHex();
        $parentIdBytes = Uuid::randomBytes();
        $versionBytes = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchFirstColumn')
            ->willReturn([$parentIdBytes]);

        $connection->expects($this->once())
            ->method('executeStatement')
            ->with(
                static::stringContains('DELETE FROM blog_configurator_setting'),
                [
                    'parentIds' => [$parentIdBytes],
                    'versionId' => $versionBytes,
                ],
                [
                    'parentIds' => ArrayParameterType::BINARY,
                ]
            );

        $subscriber = new BlogSubscriber(
            static::createStub(BlogVariationBuilder::class),
            static::createStub(AbstractBlogPriceCalculator::class),
            static::createStub(AbstractPropertyGroupSorter::class),
            static::createStub(BlogMaxPurchaseCalculator::class),
            static::createStub(IsNewDetector::class),
            new StaticSystemConfigService(),
            static::createStub(BlogMeasurementUnitBuilder::class),
            static::createStub(AbstractMeasurementUnitConverter::class),
            new RequestStack(),
            $connection
        );

        $context = Context::createDefaultContext();

        $event = $this->createMock(EntityDeleteEvent::class);
        $event->expects($this->once())
            ->method('getIds')
            ->with(BlogDefinition::ENTITY_NAME)
            ->willReturn([$deletedId]);

        $event->expects($this->once())
            ->method('getContext')
            ->willReturn($context);

        $successCallback = null;
        $event->expects($this->once())
            ->method('addSuccess')
            ->willReturnCallback(static function ($callback) use (&$successCallback): void {
                $successCallback = $callback;
            });

        $subscriber->beforeDeleteBlog($event);

        static::assertNotNull($successCallback, 'Success callback should be registered');
        $successCallback();
    }

    public function testBeforeDeleteBlogWithMultipleDeletedBlogs(): void
    {
        $deletedId1 = Uuid::randomHex();
        $deletedId2 = Uuid::randomHex();
        $parentIdBytes1 = Uuid::randomBytes();
        $parentIdBytes2 = Uuid::randomBytes();
        $versionBytes = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchFirstColumn')
            ->willReturn([$parentIdBytes1, $parentIdBytes2]);

        $connection->expects($this->once())
            ->method('executeStatement')
            ->with(
                static::stringContains('DELETE FROM blog_configurator_setting'),
                [
                    'parentIds' => [$parentIdBytes1, $parentIdBytes2],
                    'versionId' => $versionBytes,
                ],
                [
                    'parentIds' => ArrayParameterType::BINARY,
                ]
            );

        $subscriber = new BlogSubscriber(
            static::createStub(BlogVariationBuilder::class),
            static::createStub(AbstractBlogPriceCalculator::class),
            static::createStub(AbstractPropertyGroupSorter::class),
            static::createStub(BlogMaxPurchaseCalculator::class),
            static::createStub(IsNewDetector::class),
            new StaticSystemConfigService(),
            static::createStub(BlogMeasurementUnitBuilder::class),
            static::createStub(AbstractMeasurementUnitConverter::class),
            new RequestStack(),
            $connection
        );

        $context = Context::createDefaultContext();

        $event = $this->createMock(EntityDeleteEvent::class);
        $event->expects($this->once())
            ->method('getIds')
            ->with(BlogDefinition::ENTITY_NAME)
            ->willReturn([$deletedId1, $deletedId2]);

        $event->expects($this->once())
            ->method('getContext')
            ->willReturn($context);

        $successCallback = null;
        $event->expects($this->once())
            ->method('addSuccess')
            ->willReturnCallback(static function ($callback) use (&$successCallback): void {
                $successCallback = $callback;
            });

        $subscriber->beforeDeleteBlog($event);

        static::assertNotNull($successCallback);
        $successCallback();
    }
}
