<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\DataAbstractionLayer\Field;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Contena\Core\Checkout\Cart\Price\Struct\CartPrice;
use Contena\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Contena\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Contena\Core\Checkout\Order\OrderStates;
use Contena\Core\Defaults;
use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\TestDefaults;
use Contena\Core\Framework\Test\DataAbstractionLayer\Field\DataAbstractionLayerFieldTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
class UpdatedByFieldTest extends TestCase
{
    use DataAbstractionLayerFieldTestBehaviour;
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<OrderCollection>
     */
    private EntityRepository $orderRepository;

    protected function setUp(): void
    {
        $this->orderRepository = static::getContainer()->get('order.repository');
    }

    public function testUpdatedByNotUpdateWithWrongScope(): void
    {
        $adminUserId = $this->fetchFirstIdFromTable('admin_user');
        $context = $this->getAdminContext($adminUserId);

        $payload = $this->createOrderPayload();
        $this->orderRepository->create([$payload], $context);

        $this->orderRepository->update([
            [
                'id' => $payload['id'],
                'orderDateTime' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ],
        ], $context);

        $result = $this->orderRepository->search(
            new Criteria([$payload['id']]),
            $context
        )->getEntities()->first();

        static::assertNotNull($result);
        static::assertNull($result->getUpdatedById());
    }

    public function testUpdatedByNotUpdateWithWrongSource(): void
    {
        $orderRepository = $this->orderRepository;
        $context = Context::createDefaultContext();

        $payload = $this->createOrderPayload();
        $orderRepository->create([$payload], $context);

        $context->scope(Context::SYSTEM_SCOPE, static function (Context $context) use ($orderRepository, $payload): void {
            $orderRepository->update([
                [
                    'id' => $payload['id'],
                    'orderDateTime' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ],
            ], $context);
        });

        $result = $orderRepository->search(
            new Criteria([$payload['id']]),
            $context
        )->getEntities()->first();

        static::assertNotNull($result);
        static::assertNull($result->getUpdatedById());
    }

    public function testCreateUpdatedBy(): void
    {
        $orderRepository = $this->orderRepository;
        $adminUserId = $this->fetchFirstIdFromTable('admin_user');
        $context = $this->getAdminContext($adminUserId);

        $payload = $this->createOrderPayload();
        $orderRepository->create([$payload], $context);

        $context->scope(Context::SYSTEM_SCOPE, static function (Context $context) use ($orderRepository, $payload): void {
            $orderRepository->update([
                [
                    'id' => $payload['id'],
                    'orderDateTime' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ],
            ], $context);
        });

        $result = $orderRepository->search(
            new Criteria([$payload['id']]),
            $context
        )->getEntities()->first();

        static::assertNotNull($result);
        static::assertSame($adminUserId, $result->getUpdatedById());
    }

    public function testUpdatedByUpdateWithCrudScope(): void
    {
        $adminUserId = $this->fetchFirstIdFromTable('admin_user');
        $context = $this->getAdminContext($adminUserId);

        $payload = $this->createOrderPayload();
        $this->orderRepository->create([$payload], $context);

        $context->scope(Context::CRUD_API_SCOPE, function (Context $context) use ($payload): void {
            $this->orderRepository->update([
                [
                    'id' => $payload['id'],
                    'orderDateTime' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ],
            ], $context);
        });

        $result = $this->orderRepository->search(
            new Criteria([$payload['id']]),
            $context
        )->getEntities()->first();

        static::assertNotNull($result);
        static::assertSame($adminUserId, $result->getUpdatedById());
    }

    public function testUpdatedByChangesToLastCrudUser(): void
    {
        [$firstUserId, $secondUserId] = $this->fetchFirstTwoUserIds();

        $createContext = $this->getAdminContext($firstUserId);
        $updateContext = $this->getAdminContext($secondUserId);

        $payload = $this->createOrderPayload();

        $createContext->scope(Context::CRUD_API_SCOPE, function (Context $context) use ($payload): void {
            $this->orderRepository->create([$payload], $context);
        });

        $updateContext->scope(Context::CRUD_API_SCOPE, function (Context $context) use ($payload): void {
            $this->orderRepository->update([
                [
                    'id' => $payload['id'],
                    'orderDateTime' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ],
            ], $context);
        });

        $result = $this->orderRepository->search(
            new Criteria([$payload['id']]),
            $updateContext
        )->getEntities()->first();

        static::assertNotNull($result);
        static::assertSame($firstUserId, $result->getCreatedById());
        static::assertSame($secondUserId, $result->getUpdatedById());
    }

    private function getAdminContext(string $adminUserId): Context
    {
        $source = new AdminApiSource($adminUserId);
        $source->setPermissions([
            'order:list',
            'order:create',
            'order:update',
            'order_customer:create',
            'order_address:create',
        ]);

        return new Context($source);
    }

    /**
     * @return array<string, mixed>
     */
    private function createOrderPayload(): array
    {
        $addressId = Uuid::randomHex();

        return [
            'id' => Uuid::randomHex(),
            'itemRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
            'totalRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
            'orderDateTime' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'price' => new CartPrice(10, 10, 10, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_NET),
            'shippingCosts' => new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()),
            'orderUser' => [
                'id' => Uuid::randomHex(),
                'email' => 'test@example.com',
                'salutationId' => $this->fetchFirstIdFromTable('salutation'),
                'name' => 'Max',
                'lastName' => 'Mustermann',
            ],
            'stateId' => $this->fetchOrderStateId(OrderStates::STATE_OPEN),
            'paymentMethodId' => $this->fetchFirstIdFromTable('payment_method'),
            'currencyId' => Defaults::CURRENCY,
            'currencyFactor' => 1.0,
            'channelId' => TestDefaults::CHANNEL,
            'billingAddressId' => $addressId,
            'addresses' => [
                [
                    'id' => $addressId,
                    'salutationId' => $this->fetchFirstIdFromTable('salutation'),
                    'name' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Ebbinghoff 10',
                    'zipcode' => '48624',
                    'city' => 'Schöppingen',
                    'countryId' => $this->fetchFirstIdFromTable('country'),
                ],
            ],
            'lineItems' => [],
            'deliveries' => [],
            'context' => '{}',
            'payload' => '{}',
        ];
    }

    private function fetchFirstIdFromTable(string $table): string
    {
        return Uuid::fromBytesToHex((string) static::getContainer()->get(Connection::class)->fetchOne('SELECT id FROM ' . $table . ' LIMIT 1'));
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function fetchFirstTwoUserIds(): array
    {
        $ids = static::getContainer()->get(Connection::class)->fetchFirstColumn('SELECT id FROM admin_user LIMIT 2');

        if (\count($ids) < 2) {
            $secondUserId = Uuid::randomHex();
            $uniqueSuffix = substr($secondUserId, 0, 8);

            static::getContainer()->get('admin_user.repository')->create([[
                'id' => $secondUserId,
                'email' => \sprintf('second-admin-%s@example.com', $uniqueSuffix),
                'name' => 'Second',
                'lastName' => 'Admin',
                'password' => TestDefaults::HASHED_PASSWORD,
                'username' => \sprintf('second-admin-%s', $uniqueSuffix),
                'localeId' => Uuid::fromBytesToHex((string) static::getContainer()->get(Connection::class)->fetchOne('SELECT id FROM locale LIMIT 1')),
                'aclRoles' => [],
            ]], Context::createDefaultContext());

            $ids[] = Uuid::fromHexToBytes($secondUserId);
        }

        return [
            Uuid::fromBytesToHex((string) $ids[0]),
            Uuid::fromBytesToHex((string) $ids[1]),
        ];
    }

    private function fetchOrderStateId(string $orderStateTechnicalName): string
    {
        $id = static::getContainer()->get(Connection::class)->fetchOne(
            'SELECT state_machine_state.id
            FROM state_machine_state
            JOIN state_machine ON state_machine_state.state_machine_id = state_machine.id
            WHERE
                state_machine.technical_name = :orderStateMachineTechnicalName
                AND state_machine_state.technical_name = :orderStateTechnicalName',
            [
                'orderStateMachineTechnicalName' => OrderStates::STATE_MACHINE,
                'orderStateTechnicalName' => $orderStateTechnicalName,
            ]
        );

        return Uuid::fromBytesToHex((string) $id);
    }
}
