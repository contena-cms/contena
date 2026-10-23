<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Checkout\Cart\Order;

use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Order\RecalculationService;
use Contena\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Contena\Core\Checkout\Cart\Price\Struct\CartPrice;
use Contena\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Contena\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Contena\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Contena\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Contena\Core\System\User\Aggregate\UserAddress\UserAddressCollection;
use Contena\Core\System\User\UserEntity;
use Contena\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryStates;
use Contena\Core\Checkout\Order\OrderCollection;
use Contena\Core\Checkout\Order\OrderEntity;
use Contena\Core\Checkout\Order\OrderStates;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Contena\Core\Test\TestDefaults;

/**
 * @internal
 */
class RecalculationTaxStateTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Context $context;

    /**
     * @var EntityRepository<OrderCollection>
     */
    private EntityRepository $orderRepository;

    private string $germanyId;

    private string $austriaId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->context = Context::createDefaultContext();
        $this->orderRepository = static::getContainer()->get('order.repository');
        $this->germanyId = $this->getCountryIdByIso('DE');
        $this->austriaId = $this->getCountryIdByIso('AT');

        // Companies are tax-free in Austria
        static::getContainer()->get('country.repository')->update([[
            'id' => $this->austriaId,
            'companyTax' => ['enabled' => true, 'currencyId' => Defaults::CURRENCY, 'amount' => 0.0],
            'checkVatIdPattern' => false,
        ]], $this->context);
    }

    public function testGermanOrderStaysTaxableAfterTheMatchingUserAddressWasEdited(): void
    {
        $austrianAddressId = Uuid::randomHex();
        $germanAddressId = Uuid::randomHex();
        $userId = $this->createBusinessUser(
            defaultAddress: $this->getAddressData($austrianAddressId, $this->austriaId, 'Getreidegasse 9', '5020', 'Salzburg'),
            otherAddress: $this->getAddressData($germanAddressId, $this->germanyId, 'Ebbinghoff 10', '48624', 'Schöppingen'),
        );
        $orderId = $this->createOrder(
            $userId,
            $this->getAddressData(Uuid::randomHex(), $this->germanyId, 'Ebbinghoff 10', '48624', 'Schöppingen'),
        );

        // The user's German address no longer matches the order address
        static::getContainer()->get('user_address.repository')->update([[
            'id' => $germanAddressId,
            'street' => 'Ebbinghoff 11',
        ]], $this->context);

        $versionContext = $this->context->createWithVersionId($this->orderRepository->createVersion($orderId, $this->context));
        static::getContainer()->get(RecalculationService::class)->recalculate($orderId, $versionContext);

        $order = $this->orderRepository->search(new Criteria([$orderId]), $versionContext)->getEntities()->first();
        static::assertInstanceOf(OrderEntity::class, $order);
        static::assertNotSame(CartPrice::TAX_STATE_FREE, $order->getTaxStatus());
        static::assertGreaterThan(0.0, $order->getPrice()->getCalculatedTaxes()->getAmount());
    }

    public function testRecalculatingTwoOrdersWithTheSameAddressKeepsTheUserAddressesUntouched(): void
    {
        $austrianAddressId = Uuid::randomHex();
        $germanAddressId = Uuid::randomHex();
        $userId = $this->createBusinessUser(
            defaultAddress: $this->getAddressData($austrianAddressId, $this->austriaId, 'Getreidegasse 9', '5020', 'Salzburg'),
            otherAddress: $this->getAddressData($germanAddressId, $this->germanyId, 'Ebbinghoff 10', '48624', 'Schöppingen'),
        );
        $firstOrderAddressId = Uuid::randomHex();
        $secondOrderAddressId = Uuid::randomHex();
        $orderIds = [
            $this->createOrder($userId, $this->getAddressData($firstOrderAddressId, $this->germanyId, 'Ebbinghoff 10', '48624', 'Schöppingen')),
            $this->createOrder($userId, $this->getAddressData($secondOrderAddressId, $this->germanyId, 'Ebbinghoff 10', '48624', 'Schöppingen')),
        ];

        static::getContainer()->get('user_address.repository')->update([[
            'id' => $germanAddressId,
            'street' => 'Ebbinghoff 11',
        ]], $this->context);

        foreach ($orderIds as $orderId) {
            $versionContext = $this->context->createWithVersionId($this->orderRepository->createVersion($orderId, $this->context));
            static::getContainer()->get(RecalculationService::class)->recalculate($orderId, $versionContext);

            $criteria = (new Criteria([$orderId]))->addAssociation('billingAddress');
            $order = $this->orderRepository->search($criteria, $versionContext)->getEntities()->first();
            static::assertInstanceOf(OrderEntity::class, $order);
            static::assertNotSame(CartPrice::TAX_STATE_FREE, $order->getTaxStatus());
            static::assertSame('Ebbinghoff 10', $order->getBillingAddress()?->getStreet());
        }

        /** @var EntityRepository<UserAddressCollection> $userAddressRepository */
        $userAddressRepository = static::getContainer()->get('user_address.repository');
        $userAddresses = $userAddressRepository
            ->search((new Criteria())->addFilter(new EqualsFilter('userId', $userId)), $this->context)
            ->getEntities();
        static::assertEqualsCanonicalizing([$austrianAddressId, $germanAddressId], array_values($userAddresses->getIds()));
        static::assertSame('Ebbinghoff 11', $userAddresses->get($germanAddressId)?->getStreet());
        static::assertSame('Getreidegasse 9', $userAddresses->get($austrianAddressId)?->getStreet());
    }

    /**
     * @param array<string, string> $defaultAddress
     * @param array<string, string> $otherAddress
     */
    private function createBusinessUser(array $defaultAddress, array $otherAddress): string
    {
        $userId = Uuid::randomHex();

        static::getContainer()->get('user.repository')->create([[
            'id' => $userId,
            'channelId' => TestDefaults::CHANNEL,
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'defaultBillingAddressId' => $defaultAddress['id'],
            'defaultShippingAddressId' => $defaultAddress['id'],
            'userNumber' => 'CUSTOMER-1',
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'email' => $userId . '@example.com',
            'password' => TestDefaults::HASHED_PASSWORD,
            'accountType' => UserEntity::ACCOUNT_TYPE_BUSINESS,
            'company' => 'Contena',
            'vatIds' => ['ATU12345678'],
            'addresses' => [
                ['userId' => $userId, ...$defaultAddress],
                ['userId' => $userId, ...$otherAddress],
            ],
        ]], $this->context);

        return $userId;
    }

    /**
     * @param array<string, string> $address
     */
    private function createOrder(string $userId, array $address): string
    {
        $orderId = Uuid::randomHex();
        $deliveryId = Uuid::randomHex();
        $lineItemId = Uuid::randomHex();
        $stateIdLoader = static::getContainer()->get(InitialStateIdLoader::class);
        $rounding = ['decimals' => 2, 'interval' => 0.01, 'roundForNet' => true];
        $taxRules = new TaxRuleCollection([new TaxRule(19)]);

        $this->orderRepository->create([[
            'id' => $orderId,
            'orderNumber' => Uuid::randomHex(),
            'price' => new CartPrice(100, 119, 100, new CalculatedTaxCollection(), $taxRules, CartPrice::TAX_STATE_NET),
            'shippingCosts' => new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()),
            'stateId' => $stateIdLoader->get(OrderStates::STATE_MACHINE),
            'currencyId' => Defaults::CURRENCY,
            'currencyFactor' => 1,
            'channelId' => TestDefaults::CHANNEL,
            'orderDateTime' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'itemRounding' => $rounding,
            'totalRounding' => $rounding,
            'billingAddressId' => $address['id'],
            'addresses' => [$address],
            'orderUser' => [
                'userId' => $userId,
                'email' => 'test@example.com',
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'salutationId' => $this->getValidSalutationId(),
            ],
            'lineItems' => [[
                'id' => $lineItemId,
                'identifier' => $lineItemId,
                'quantity' => 1,
                'type' => 'custom',
                'label' => 'Test',
                'price' => new CalculatedPrice(100, 100, new CalculatedTaxCollection(), $taxRules),
                'priceDefinition' => new QuantityPriceDefinition(100, $taxRules),
            ]],
            'deliveries' => [[
                'id' => $deliveryId,
                'stateId' => $stateIdLoader->get(OrderDeliveryStates::STATE_MACHINE),
                'shippingMethodId' => $this->getValidShippingMethodId(),
                'shippingOrderAddressId' => $address['id'],
                'shippingCosts' => new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()),
                'shippingDateEarliest' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_FORMAT),
                'shippingDateLatest' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_FORMAT),
            ]],
        ]], $this->context);

        $this->orderRepository->update([['id' => $orderId, 'primaryOrderDeliveryId' => $deliveryId]], $this->context);

        return $orderId;
    }

    /**
     * @return array<string, string>
     */
    private function getAddressData(string $id, string $countryId, string $street, string $zipcode, string $city): array
    {
        return [
            'id' => $id,
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'company' => 'Contena',
            'street' => $street,
            'zipcode' => $zipcode,
            'city' => $city,
            'countryId' => $countryId,
        ];
    }

    private function getCountryIdByIso(string $iso): string
    {
        $countryId = static::getContainer()->get('country.repository')
            ->searchIds((new Criteria())->addFilter(new EqualsFilter('iso', $iso)), $this->context)
            ->firstId();
        static::assertIsString($countryId);

        return $countryId;
    }
}
