<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Checkout\Customer\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Customer\CustomerCollection;
use Contena\Core\Checkout\Customer\CustomerDefinition;
use Contena\Core\Checkout\Customer\CustomerEntity;
use Contena\Core\Checkout\Customer\Event\CustomerDeletedEvent;
use Contena\Core\Checkout\Customer\Subscriber\CustomerBeforeDeleteSubscriber;
use Contena\Core\Framework\Api\Context\SalesChannelApiSource;
use Contena\Core\Framework\Api\Serializer\JsonEntityEncoder;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityDeleteEvent;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Struct\Serializer\StructNormalizer;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Language\LanguageCollection;
use Contena\Core\System\Language\LanguageEntity;
use Contena\Core\System\SalesChannel\Context\SalesChannelContextService;
use Contena\Core\System\SalesChannel\SalesChannelCollection;
use Contena\Core\System\SalesChannel\SalesChannelEntity;
use Contena\Core\Test\Generator;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Serializer\Serializer;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CustomerBeforeDeleteSubscriber::class)]
class CustomerBeforeDeleteSubscriberTest extends TestCase
{
    public function testEventsDispatched(): void
    {
        $customerId = Uuid::randomBytes();
        $customer = (new CustomerEntity())
            ->assign([
                'id' => Uuid::fromBytesToHex($customerId),
                'salesChannelId' => Uuid::randomHex(),
                'languageId' => Uuid::randomHex(),
                'customerNumber' => 'SW1000',
                'email' => 'foo@bar.com',
                'firstName' => 'foo',
                'lastName' => 'bar',
            ]);

        $definitionInstanceRegistry = static::createStub(DefinitionInstanceRegistry::class);

        $customerDefinition = new CustomerDefinition();
        $customerDefinition->compile($definitionInstanceRegistry);

        $customerRepository = new StaticEntityRepository([
            new EntitySearchResult(
                'customer',
                1,
                new CustomerCollection([$customer]),
                null,
                new Criteria([$customerId]),
                Context::createDefaultContext()
            ),
        ], $customerDefinition);

        $salesChannelId = $customer->getSalesChannelId();
        $languageId = $customer->getLanguageId();
        $language = (new LanguageEntity())->assign(['id' => $languageId]);
        $salesChannel = (new SalesChannelEntity())->assign([
            'id' => $salesChannelId,
            'languages' => new LanguageCollection([$language]),
        ]);
        $salesChannelRepository = new StaticEntityRepository([
            new SalesChannelCollection([$salesChannel]),
        ]);

        $salesChannelContextService = static::createStub(SalesChannelContextService::class);
        $salesChannelContextService->method('get')->willReturn(Generator::generateSalesChannelContext());

        $eventDispatcher = new EventDispatcher();

        $structNormalizer = new StructNormalizer();

        $jsonEntityEncoder = new JsonEntityEncoder(new Serializer([$structNormalizer], []));

        $subscriber = new CustomerBeforeDeleteSubscriber(
            $customerRepository,
            $salesChannelRepository,
            $salesChannelContextService,
            $eventDispatcher,
            $jsonEntityEncoder
        );
        $eventDispatcher->addSubscriber($subscriber);

        $entityDeleteEvent = EntityDeleteEvent::create(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [
                new DeleteCommand(
                    $customerDefinition,
                    ['id' => $customerId],
                    new EntityExistence(
                        'customer',
                        ['id' => $customerId],
                        true,
                        false,
                        false,
                        [
                            'exists' => true,
                            'id' => $customerId,
                        ]
                    )
                ),
            ]
        );

        $customerDeletedEventCount = 0;

        $serializedCustomer = $jsonEntityEncoder->encode(
            new Criteria(),
            $customerDefinition,
            $customer,
            '/api/customer'
        );

        $eventDispatcher->addListener(
            CustomerDeletedEvent::class,
            static function (CustomerDeletedEvent $event) use (&$customerDeletedEventCount, $customer, $serializedCustomer): void {
                ++$customerDeletedEventCount;
                static::assertSame($customer, $event->getCustomer());
                $values = $event->getValues();
                static::assertArrayHasKey('customer', $values);
                static::assertSame($serializedCustomer, $values['customer']);
            }
        );

        $eventDispatcher->dispatch($entityDeleteEvent);
        $entityDeleteEvent->success();

        static::assertSame(1, $customerDeletedEventCount);
    }

    public function testBeforeDeleteWithEmptyCustomerIdsDoesNotDispatch(): void
    {
        $definitionInstanceRegistry = static::createStub(DefinitionInstanceRegistry::class);
        $customerDefinition = new CustomerDefinition();
        $customerDefinition->compile($definitionInstanceRegistry);

        $customerRepository = new StaticEntityRepository([], $customerDefinition);
        $salesChannelRepository = new StaticEntityRepository([]);
        $salesChannelContextService = static::createStub(SalesChannelContextService::class);
        $eventDispatcher = new EventDispatcher();
        $jsonEntityEncoder = static::createStub(JsonEntityEncoder::class);

        $subscriber = new CustomerBeforeDeleteSubscriber(
            $customerRepository,
            $salesChannelRepository,
            $salesChannelContextService,
            $eventDispatcher,
            $jsonEntityEncoder
        );
        $eventDispatcher->addSubscriber($subscriber);

        $caughtEvents = 0;
        $eventDispatcher->addListener(
            CustomerDeletedEvent::class,
            static function () use (&$caughtEvents): void {
                ++$caughtEvents;
            }
        );

        $entityDeleteEvent = EntityDeleteEvent::create(
            WriteContext::createFromContext(Context::createDefaultContext()),
            []
        );
        $eventDispatcher->dispatch($entityDeleteEvent);
        $entityDeleteEvent->success();

        static::assertSame(0, $caughtEvents);
    }

    public function testBeforeDeleteWithSalesChannelApiSourceUsesSourceSalesChannelId(): void
    {
        $customerId = Uuid::randomBytes();
        $salesChannelIdFromSource = Uuid::randomHex();
        $languageId = Uuid::randomHex();
        $customer = (new CustomerEntity())
            ->assign([
                'id' => Uuid::fromBytesToHex($customerId),
                'salesChannelId' => Uuid::randomHex(),
                'languageId' => $languageId,
                'customerNumber' => 'SW1001',
                'email' => 'bar@baz.com',
                'firstName' => 'bar',
                'lastName' => 'baz',
            ]);

        $definitionInstanceRegistry = static::createStub(DefinitionInstanceRegistry::class);
        $customerDefinition = new CustomerDefinition();
        $customerDefinition->compile($definitionInstanceRegistry);

        $customerRepository = new StaticEntityRepository([
            new EntitySearchResult(
                'customer',
                1,
                new CustomerCollection([$customer]),
                null,
                new Criteria([$customerId]),
                Context::createDefaultContext()
            ),
        ], $customerDefinition);

        $language = (new LanguageEntity())->assign(['id' => $languageId]);
        $salesChannel = (new SalesChannelEntity())->assign([
            'id' => $salesChannelIdFromSource,
            'languages' => new LanguageCollection([$language]),
        ]);
        $salesChannelRepository = new StaticEntityRepository([
            new SalesChannelCollection([$salesChannel]),
        ]);

        $salesChannelContextService = static::createStub(SalesChannelContextService::class);
        $salesChannelContextService->method('get')->willReturn(Generator::generateSalesChannelContext());
        $eventDispatcher = new EventDispatcher();
        $jsonEntityEncoder = new JsonEntityEncoder(new Serializer([new StructNormalizer()], []));

        $subscriber = new CustomerBeforeDeleteSubscriber(
            $customerRepository,
            $salesChannelRepository,
            $salesChannelContextService,
            $eventDispatcher,
            $jsonEntityEncoder
        );
        $eventDispatcher->addSubscriber($subscriber);

        $dispatchedCount = 0;
        $eventDispatcher->addListener(
            CustomerDeletedEvent::class,
            static function () use (&$dispatchedCount): void {
                ++$dispatchedCount;
            }
        );

        $context = Context::createDefaultContext(new SalesChannelApiSource($salesChannelIdFromSource));
        $entityDeleteEvent = EntityDeleteEvent::create(
            WriteContext::createFromContext($context),
            [
                new DeleteCommand(
                    $customerDefinition,
                    ['id' => $customerId],
                    new EntityExistence(
                        'customer',
                        ['id' => $customerId],
                        true,
                        false,
                        false,
                        ['exists' => true, 'id' => $customerId]
                    )
                ),
            ]
        );
        $eventDispatcher->dispatch($entityDeleteEvent);
        $entityDeleteEvent->success();

        static::assertSame(1, $dispatchedCount);
    }

    public function testBeforeDeleteWhenSalesChannelDoesNotHaveCustomerLanguageUsesNullLanguageId(): void
    {
        $customerId = Uuid::randomBytes();
        $salesChannelId = Uuid::randomHex();
        $customerLanguageId = Uuid::randomHex();
        $otherLanguageId = Uuid::randomHex();
        $customer = (new CustomerEntity())
            ->assign([
                'id' => Uuid::fromBytesToHex($customerId),
                'salesChannelId' => $salesChannelId,
                'languageId' => $customerLanguageId,
                'customerNumber' => 'SW1002',
                'email' => 'nolang@test.com',
                'firstName' => 'No',
                'lastName' => 'Lang',
            ]);

        $definitionInstanceRegistry = static::createStub(DefinitionInstanceRegistry::class);
        $customerDefinition = new CustomerDefinition();
        $customerDefinition->compile($definitionInstanceRegistry);

        $customerRepository = new StaticEntityRepository([
            new EntitySearchResult(
                'customer',
                1,
                new CustomerCollection([$customer]),
                null,
                new Criteria([$customerId]),
                Context::createDefaultContext()
            ),
        ], $customerDefinition);

        $salesChannelHasOnlyOtherLanguage = (new LanguageEntity())->assign(['id' => $otherLanguageId]);
        $salesChannel = (new SalesChannelEntity())->assign([
            'id' => $salesChannelId,
            'languages' => new LanguageCollection([$salesChannelHasOnlyOtherLanguage]),
        ]);
        $salesChannelRepository = new StaticEntityRepository([
            new SalesChannelCollection([$salesChannel]),
        ]);

        $salesChannelContextService = static::createStub(SalesChannelContextService::class);
        $salesChannelContextService->method('get')->willReturn(Generator::generateSalesChannelContext());
        $eventDispatcher = new EventDispatcher();
        $jsonEntityEncoder = new JsonEntityEncoder(new Serializer([new StructNormalizer()], []));

        $subscriber = new CustomerBeforeDeleteSubscriber(
            $customerRepository,
            $salesChannelRepository,
            $salesChannelContextService,
            $eventDispatcher,
            $jsonEntityEncoder
        );
        $eventDispatcher->addSubscriber($subscriber);

        $dispatchedCount = 0;
        $eventDispatcher->addListener(
            CustomerDeletedEvent::class,
            static function () use (&$dispatchedCount): void {
                ++$dispatchedCount;
            }
        );

        $entityDeleteEvent = EntityDeleteEvent::create(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [
                new DeleteCommand(
                    $customerDefinition,
                    ['id' => $customerId],
                    new EntityExistence(
                        'customer',
                        ['id' => $customerId],
                        true,
                        false,
                        false,
                        ['exists' => true, 'id' => $customerId]
                    )
                ),
            ]
        );
        $eventDispatcher->dispatch($entityDeleteEvent);
        $entityDeleteEvent->success();

        static::assertSame(1, $dispatchedCount);
    }
}
