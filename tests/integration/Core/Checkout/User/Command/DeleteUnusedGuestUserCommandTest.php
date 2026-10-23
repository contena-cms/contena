<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\User\Command;

use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Contena\Core\Checkout\Cart\Price\Struct\CartPrice;
use Contena\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Contena\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Contena\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Contena\Core\System\User\Command\DeleteUnusedGuestUsersCommand;
use Contena\Core\System\User\UserEntity;
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
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
class DeleteUnusedGuestUsersCommandTest extends TestCase
{
    use IntegrationTestBehaviour;

    private IdsCollection $ids;

    private DeleteUnusedGuestUsersCommand $command;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->command = static::getContainer()->get(DeleteUnusedGuestUsersCommand::class);
        static::getContainer()
            ->get(SystemConfigService::class)
            ->set('core.loginRegistration.unusedGuestUserLifetime', 86400);
    }

    public function testExecuteWithoutUnusedGuestUsers(): void
    {
        $commandTester = new CommandTester($this->command);
        $commandTester->execute([]);

        $string = $commandTester->getDisplay();
        static::assertIsInt(\mb_strpos($string, 'No unused guest users found.'));
    }

    public function testExecuteWithoutConfirm(): void
    {
        $userRepository = static::getContainer()->get('user.repository');

        $userGuestWithOrder = (new UserBuilder($this->ids, '10000'))
            ->add('guest', true)
            ->add('createdAt', new \DateTime('- 25 hours'));

        $userGuest = (new UserBuilder($this->ids, '10001'))
            ->add('guest', true)
            ->add('createdAt', new \DateTime('- 25 hours'));

        $user = (new UserBuilder($this->ids, '10002'))
            ->add('guest', false)
            ->add('createdAt', new \DateTime('- 25 hours'));

        $userRepository->create([
            $userGuestWithOrder->build(),
            $userGuest->build(),
            $user->build(),
        ], Context::createDefaultContext());

        $this->createOrderForUser($userGuestWithOrder->build());

        $commandTester = new CommandTester($this->command);
        $commandTester->setInputs(['no']);
        $commandTester->execute([]);

        $string = $commandTester->getDisplay();
        static::assertIsInt(\mb_strpos($string, 'Aborting due to admin_user input.'));

        $users = $userRepository->search(
            (new Criteria([
                $this->ids->get('10000'),
                $this->ids->get('10001'),
                $this->ids->get('10002'),
            ]))->addAssociation('orderUsers'),
            Context::createDefaultContext()
        )->getEntities();

        static::assertContains($this->ids->get('10000'), $users->getIds());
        static::assertContains($this->ids->get('10001'), $users->getIds());
        static::assertContains($this->ids->get('10002'), $users->getIds());

        $userGuestWithOrder = $users->get($this->ids->get('10000'));
        $userGuest = $users->get($this->ids->get('10001'));
        $user = $users->get($this->ids->get('10002'));

        static::assertInstanceOf(UserEntity::class, $userGuestWithOrder);
        static::assertNotNull($userGuestWithOrder->getOrderUsers());
        static::assertCount(1, $userGuestWithOrder->getOrderUsers());
        static::assertTrue($userGuestWithOrder->getGuest());

        static::assertInstanceOf(UserEntity::class, $userGuest);
        static::assertTrue($userGuest->getGuest());

        static::assertInstanceOf(UserEntity::class, $user);
        static::assertFalse($user->getGuest());
    }

    public function testExecuteWithConfirm(): void
    {
        $userRepository = static::getContainer()->get('user.repository');

        $userGuestWithOrder = (new UserBuilder($this->ids, '10000'))
            ->add('guest', true)
            ->add('createdAt', new \DateTime('- 25 hours'));

        $userGuest = (new UserBuilder($this->ids, '10001'))
            ->add('guest', true)
            ->add('createdAt', new \DateTime('- 25 hours'));

        $user = (new UserBuilder($this->ids, '10002'))
            ->add('guest', false)
            ->add('createdAt', new \DateTime('- 25 hours'));

        $userRepository->create([
            $userGuestWithOrder->build(),
            $userGuest->build(),
            $user->build(),
        ], Context::createDefaultContext());

        $this->createOrderForUser($userGuestWithOrder->build());

        $commandTester = new CommandTester($this->command);
        $commandTester->setInputs(['yes']);
        $commandTester->execute([]);

        $users = $userRepository->search(
            (new Criteria([
                $this->ids->get('10000'),
                $this->ids->get('10001'),
                $this->ids->get('10002'),
            ]))->addAssociation('orderUsers'),
            Context::createDefaultContext()
        )->getEntities();

        static::assertContains($this->ids->get('10000'), $users->getIds());
        static::assertNotContains($this->ids->get('10001'), $users->getIds());
        static::assertContains($this->ids->get('10002'), $users->getIds());

        $userGuestWithOrder = $users->get($this->ids->get('10000'));
        $user = $users->get($this->ids->get('10002'));

        static::assertInstanceOf(UserEntity::class, $userGuestWithOrder);
        static::assertNotNull($userGuestWithOrder->getOrderUsers());
        static::assertCount(1, $userGuestWithOrder->getOrderUsers());
        static::assertTrue($userGuestWithOrder->getGuest());

        static::assertInstanceOf(UserEntity::class, $user);
        static::assertFalse($user->getGuest());
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
            'orderDateTime' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'itemRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
            'totalRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
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
