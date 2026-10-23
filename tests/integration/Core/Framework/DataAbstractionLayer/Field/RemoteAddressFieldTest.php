<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\DataAbstractionLayer\Field;

use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Contena\Core\Checkout\Cart\Price\Struct\CartPrice;
use Contena\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Contena\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Contena\Core\Checkout\Order\Aggregate\OrderUser\OrderUserEntity;
use Contena\Core\Checkout\Order\OrderStates;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\System\User\Channel\AccountService;
use Contena\Core\System\User\UserEntity;
use Contena\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * @internal
 */
class RemoteAddressFieldTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testRemoteAddressSerializerAnonymize(): void
    {
        $this->setConfig();

        $remoteAddress = '127.0.0.1';
        $orderId = $this->createOrderWithRemoteAddress($remoteAddress);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderId', $orderId));

        $orderUser = static::getContainer()->get('order_customer.repository')
            ->search($criteria, Context::createDefaultContext())
            ->getEntities()->first();

        static::assertInstanceOf(OrderUserEntity::class, $orderUser);
        static::assertNotSame($remoteAddress, $orderUser->getRemoteAddress());
        static::assertSame(IpUtils::anonymize($remoteAddress), $orderUser->getRemoteAddress());
    }

    public function testRemoteAddressSerializerNoAnonymize(): void
    {
        $this->setConfig(true);

        $remoteAddress = '127.0.0.1';
        $orderId = $this->createOrderWithRemoteAddress($remoteAddress);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderId', $orderId));

        $orderUser = static::getContainer()->get('order_customer.repository')
            ->search($criteria, Context::createDefaultContext())
            ->getEntities()->first();

        static::assertInstanceOf(OrderUserEntity::class, $orderUser);
        static::assertSame($remoteAddress, $orderUser->getRemoteAddress());
    }

    public function testSetRemoteAddressByLogin(): void
    {
        $this->setConfig();

        $userId = $this->createUser();

        static::getContainer()->get(AccountService::class)
            ->loginByCredentials('test@example.com', 'contena', $this->createChannelContext());

        $criteria = new Criteria([$userId]);

        $user = static::getContainer()->get('user.repository')
            ->search($criteria, Context::createDefaultContext())
            ->getEntities()->first();

        static::assertInstanceOf(UserEntity::class, $user);
        static::assertNotSame('127.0.0.1', $user->getRemoteAddress());
        static::assertSame(IpUtils::anonymize('127.0.0.1'), $user->getRemoteAddress());
    }

    private function setConfig(bool $value = false): void
    {
        static::getContainer()->get(SystemConfigService::class)
            ->set('core.loginRegistration.userIpAddressesNotAnonymously', $value);
    }

    private function createChannelContext(): ChannelContext
    {
        $channelContextFactory = static::getContainer()->get(ChannelContextFactory::class);

        return $channelContextFactory->create(Uuid::randomHex(), TestDefaults::CHANNEL);
    }

    private function createOrderWithRemoteAddress(string $remoteAddress): string
    {
        $orderId = Uuid::randomHex();
        $addressId = Uuid::randomHex();
        $stateId = static::getContainer()->get(InitialStateIdLoader::class)->get(OrderStates::STATE_MACHINE);

        $userId = $this->createUser();

        $order = [
            'id' => $orderId,
            'itemRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
            'totalRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
            'orderDateTime' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'price' => new CartPrice(10, 10, 10, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_NET),
            'shippingCosts' => new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()),
            'orderUser' => [
                'userId' => $userId,
                'email' => 'test@example.com',
                'salutationId' => $this->getValidSalutationId(),
                'name' => 'Max',
                'lastName' => 'Mustermann',
                'remoteAddress' => $remoteAddress,
            ],
            'stateId' => $stateId,
            'paymentMethodId' => $this->getValidPaymentMethodId(),
            'currencyId' => Defaults::CURRENCY,
            'currencyFactor' => 1.0,
            'channelId' => TestDefaults::CHANNEL,
            'billingAddressId' => $addressId,
            'addresses' => [
                [
                    'id' => $addressId,
                    'salutationId' => $this->getValidSalutationId(),
                    'name' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Ebbinghoff 10',
                    'zipcode' => '48624',
                    'city' => 'Schöppingen',
                    'countryId' => $this->getValidCountryId(),
                ],
            ],
            'lineItems' => [],
            'deliveries' => [],
            'context' => '{}',
            'payload' => '{}',
        ];

        static::getContainer()->get('order.repository')->upsert([$order], Context::createDefaultContext());

        return $orderId;
    }

    private function createUser(): string
    {
        $userId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $user = [
            'id' => $userId,
            'userNumber' => '1337',
            'salutationId' => $this->getValidSalutationId(),
            'name' => 'Max',
            'lastName' => 'Mustermann',
            'email' => 'test@example.com',
            'password' => TestDefaults::HASHED_PASSWORD,
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'channelId' => TestDefaults::CHANNEL,
            'defaultBillingAddressId' => $addressId,
            'defaultShippingAddressId' => $addressId,
            'remoteAddress' => '127.0.0.1',
            'addresses' => [
                [
                    'id' => $addressId,
                    'userId' => $userId,
                    'countryId' => $this->getValidCountryId(),
                    'salutationId' => $this->getValidSalutationId(),
                    'name' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Ebbinghoff 10',
                    'zipcode' => '48624',
                    'city' => 'Schöppingen',
                ],
            ],
        ];

        static::getContainer()->get('user.repository')->upsert([$user], Context::createDefaultContext());

        return $userId;
    }
}
