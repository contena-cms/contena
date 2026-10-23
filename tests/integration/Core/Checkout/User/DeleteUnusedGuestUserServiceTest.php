<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\User;

use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Contena\Core\Checkout\Cart\Price\Struct\CartPrice;
use Contena\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Contena\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Contena\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Contena\Core\System\User\UserEntity;
use Contena\Core\System\User\DeleteUnusedGuestUserService;
use Contena\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryStates;
use Contena\Core\Checkout\Order\OrderStates;
use Contena\Core\Content\Test\Blog\BlogBuilder;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\Test\Integration\Builder\User\UserBuilder;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Test\TestDefaults;

/**
 * @internal
 */
class DeleteUnusedGuestUserServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    private IdsCollection $ids;

    private DeleteUnusedGuestUserService $service;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->service = static::getContainer()->get(DeleteUnusedGuestUserService::class);

        static::getContainer()
            ->get(SystemConfigService::class)
            ->set('core.loginRegistration.unusedGuestUserLifetime', 86400);
    }

    public function testItDeletesUnusedGuestUser(): void
    {
        $context = Context::createDefaultContext();
        $userRepository = static::getContainer()->get('user.repository');

        $user = (new UserBuilder($this->ids, '10000'))
            ->add('guest', true)
            ->add('createdAt', new \DateTime('- 25 hours'));

        $userRepository->create([$user->build()], $context);

        static::assertSame(1, $this->service->countUnusedUsers($context));

        $this->service->deleteUnusedUsers($context);

        static::assertSame(0, $this->service->countUnusedUsers($context));

        $result = $userRepository->search(new Criteria([$this->ids->get('10000')]), $context)->getEntities();

        static::assertCount(0, $result);
    }

    public function testItDoesOnlyDeleteGuestUsers(): void
    {
        $context = Context::createDefaultContext();
        $userRepository = static::getContainer()->get('user.repository');

        $user = (new UserBuilder($this->ids, '10000'))
            ->add('guest', false)
            ->add('createdAt', new \DateTime('- 25 hours'));

        $guestUser = (new UserBuilder($this->ids, '10001'))
            ->add('guest', true)
            ->add('createdAt', new \DateTime('- 25 hours'));

        $userRepository->create([
            $user->build(),
            $guestUser->build(),
        ], $context);

        static::assertSame(1, $this->service->countUnusedUsers($context));

        $this->service->deleteUnusedUsers($context);

        static::assertSame(0, $this->service->countUnusedUsers($context));

        $result = $userRepository->search(new Criteria([
            $this->ids->get('10000'),
            $this->ids->get('10001'),
        ]), $context)->getEntities();

        static::assertCount(1, $result);

        /** @var UserEntity $entity */
        $entity = $result->first();

        // expect the non-guest user to still exist
        static::assertSame('10000', $entity->getUserNumber());
    }

    public function testItDeletesOnlyExpiredUserAccounts(): void
    {
        $context = Context::createDefaultContext();
        $userRepository = static::getContainer()->get('user.repository');

        $nonExpiredUser = (new UserBuilder($this->ids, '10000'))
            ->add('guest', true)
            // guest account is not expired as max lifetime is 86400s = 24 hours
            ->add('createdAt', new \DateTime('- 2 hours'));

        $expiredUser = (new UserBuilder($this->ids, '10001'))
            ->add('guest', true)
            ->add('createdAt', new \DateTime('- 25 hours'));

        $userRepository->create([
            $nonExpiredUser->build(),
            $expiredUser->build(),
        ], $context);

        static::assertSame(1, $this->service->countUnusedUsers($context));

        $this->service->deleteUnusedUsers($context);

        static::assertSame(0, $this->service->countUnusedUsers($context));

        $result = $userRepository->search(new Criteria([
            $this->ids->get('10000'),
            $this->ids->get('10001'),
        ]), $context)->getEntities();

        static::assertCount(1, $result);

        /** @var UserEntity $entity */
        $entity = $result->first();

        // expect the non expired user to still exist
        static::assertSame('10000', $entity->getUserNumber());
    }

    public function testItDoesOnlyDeleteUsersWithoutOrders(): void
    {
        $context = Context::createDefaultContext();
        $userRepository = static::getContainer()->get('user.repository');

        $userWithOrder = (new UserBuilder($this->ids, '10000'))
            ->add('guest', true)
            ->add('createdAt', new \DateTime('- 25 hours'));

        $userWithoutOrder = (new UserBuilder($this->ids, '10001'))
            ->add('guest', true)
            ->add('createdAt', new \DateTime('- 25 hours'));

        $userRepository->create([
            $userWithOrder->build(),
            $userWithoutOrder->build(),
        ], $context);

        $this->createOrderForUser($userWithOrder->build());

        static::assertSame(1, $this->service->countUnusedUsers($context));

        $this->service->deleteUnusedUsers($context);

        static::assertSame(0, $this->service->countUnusedUsers($context));

        $result = $userRepository->search(new Criteria([
            $this->ids->get('10000'),
            $this->ids->get('10001'),
        ]), $context)->getEntities();

        static::assertCount(1, $result);

        /** @var UserEntity $entity */
        $entity = $result->first();

        // expect the user with an order to still exist
        static::assertSame('10000', $entity->getUserNumber());
    }

    public function testItCancelsWhenMaxLifeTimeIsZero(): void
    {
        static::getContainer()
            ->get(SystemConfigService::class)
            ->set('core.loginRegistration.unusedGuestUserLifetime', 0);

        $context = Context::createDefaultContext();
        $userRepository = static::getContainer()->get('user.repository');

        $user = (new UserBuilder($this->ids, '10000'))
            ->add('guest', true)
            ->add('createdAt', new \DateTime('- 25 hours'));

        $userRepository->create([$user->build()], $context);

        static::assertSame(0, $this->service->countUnusedUsers($context));

        $this->service->deleteUnusedUsers($context);

        static::assertSame(0, $this->service->countUnusedUsers($context));

        $result = $userRepository->search(new Criteria([$this->ids->get('10000')]), $context)->getEntities();

        static::assertCount(1, $result);

        /** @var UserEntity $entity */
        $entity = $result->first();

        // expect the user to still exist
        static::assertSame('10000', $entity->getUserNumber());
    }

    public function testItCancelsWhenMaxLifeTimeIsNull(): void
    {
        static::getContainer()
            ->get(SystemConfigService::class)
            ->set('core.loginRegistration.unusedGuestUserLifetime', null);

        $context = Context::createDefaultContext();
        $userRepository = static::getContainer()->get('user.repository');

        $user = (new UserBuilder($this->ids, '10000'))
            ->add('guest', true)
            ->add('createdAt', new \DateTime('- 25 hours'));

        $userRepository->create([$user->build()], $context);

        static::assertSame(0, $this->service->countUnusedUsers($context));

        $this->service->deleteUnusedUsers($context);

        static::assertSame(0, $this->service->countUnusedUsers($context));

        $result = $userRepository->search(new Criteria([$this->ids->get('10000')]), $context)->getEntities();

        static::assertCount(1, $result);

        /** @var UserEntity $entity */
        $entity = $result->first();

        // expect the user to still exist
        static::assertSame('10000', $entity->getUserNumber());
    }

    /**
     * @param array<mixed> $user
     */
    private function createOrderForUser(array $user): string
    {
        $blogRepository = static::getContainer()->get('blog.repository');
        $orderRepository = static::getContainer()->get('order.repository');

        $blog = (new BlogBuilder($this->ids, 'Blog-1'))
            ->price(10)
            ->build();

        $blogRepository->create([$blog], Context::createDefaultContext());

        $orderId = Uuid::randomHex();

        $order = [
            'id' => $orderId,
            'itemRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
            'totalRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
            'orderDateTime' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'price' => new CartPrice(10, 10, 10, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_NET),
            'shippingCosts' => new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()),
            'stateId' => static::getContainer()->get(InitialStateIdLoader::class)->get(OrderStates::STATE_MACHINE),
            'paymentMethodId' => $this->getValidPaymentMethodId(),
            'currencyId' => Defaults::CURRENCY,
            'currencyFactor' => 1,
            'channelId' => TestDefaults::CHANNEL,
            'addresses' => [
                array_merge(
                    $user['addresses']['default-address'],
                    ['id' => $user['defaultShippingAddressId']]
                ),
            ],
            'deliveries' => [
                [
                    'stateId' => static::getContainer()->get(InitialStateIdLoader::class)->get(OrderDeliveryStates::STATE_MACHINE),
                    'shippingMethodId' => $this->getValidShippingMethodId(),
                    'shippingCosts' => new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()),
                    'shippingDateEarliest' => date(\DATE_ATOM),
                    'shippingDateLatest' => date(\DATE_ATOM),
                    'shippingOrderAddressId' => $user['defaultShippingAddressId'],
                    'positions' => [
                        [
                            'price' => new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()),
                            'orderLineItemId' => $this->ids->get('Blog-1'),
                        ],
                    ],
                ],
            ],
            'lineItems' => [
                [
                    'id' => $this->ids->get('Blog-1'),
                    'identifier' => 'test',
                    'quantity' => 1,
                    'type' => 'test',
                    'label' => 'test',
                    'price' => new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()),
                    'priceDefinition' => new QuantityPriceDefinition(10, new TaxRuleCollection()),
                    'good' => true,
                ],
            ],
            'deepLinkCode' => 'BwvdEInxOHBbwfRw6oHF1Q_orfYeo9RY',
            'orderUser' => [
                'email' => $user['email'],
                'firstName' => $user['firstName'],
                'lastName' => $user['lastName'],
                'userNumber' => $user['userNumber'],
                'salutationId' => $user['salutationId'] ?? $this->getValidSalutationId(),
                'userId' => $user['id'],
            ],
            'billingAddressId' => $user['defaultBillingAddressId'],
        ];

        $orderRepository->create([$order], Context::createDefaultContext());

        return $orderId;
    }
}
