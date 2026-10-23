<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\User\Subscriber;

use PHPUnit\Framework\TestCase;
use Contena\Core\System\User\UserCollection;
use Contena\Core\System\User\UserEntity;
use Contena\Core\Checkout\Order\OrderCollection;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Contena\Core\Framework\Util\Random;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;
use Contena\Core\System\StateMachine\StateMachineRegistry;
use Contena\Core\System\StateMachine\Transition;
use Contena\Core\Test\Integration\Traits\OrderFixture;

/**
 * @internal
 */
class UserMetaFieldSubscriberTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;
    use OrderFixture;

    /**
     * @var EntityRepository<OrderCollection>
     */
    private EntityRepository $orderRepository;

    /**
     * @var EntityRepository<UserCollection>
     */
    private EntityRepository $userRepository;

    private Context $context;

    private StateMachineRegistry $stateMachineRegistry;

    protected function setUp(): void
    {
        $this->orderRepository = static::getContainer()->get('order.repository');
        $this->userRepository = static::getContainer()->get('user.repository');
        $this->context = Context::createDefaultContext();
        $this->stateMachineRegistry = static::getContainer()->get(StateMachineRegistry::class);
    }

    public function testCompletingAndReopeningOrderUpdatesUserMetadata(): void
    {
        [$orderId, $userId] = $this->createOrder();

        $this->transitionOrder($orderId, StateMachineTransitionActions::ACTION_PROCESS);
        $this->transitionOrder($orderId, StateMachineTransitionActions::ACTION_COMPLETE);

        $user = $this->fetchUser($userId);
        static::assertSame(1, $user->getOrderCount());
        static::assertSame(10, (int) $user->getOrderTotalAmount());
        static::assertNotNull($user->getLastOrderDate());

        $this->transitionOrder($orderId, StateMachineTransitionActions::ACTION_REOPEN);

        $user = $this->fetchUser($userId);
        static::assertSame(0, $user->getOrderCount());
        static::assertSame(0, (int) $user->getOrderTotalAmount());
        static::assertNull($user->getLastOrderDate());
    }

    public function testDeletingCompletedOrderUpdatesUserMetadata(): void
    {
        [$orderId, $userId] = $this->createCompletedOrder();

        $user = $this->fetchUser($userId);
        static::assertSame(1, $user->getOrderCount());
        static::assertSame(10, (int) $user->getOrderTotalAmount());
        static::assertNotNull($user->getLastOrderDate());

        $this->orderRepository->delete([['id' => $orderId]], $this->context);

        $user = $this->fetchUser($userId);
        static::assertSame(0, $user->getOrderCount());
        static::assertSame(0, (int) $user->getOrderTotalAmount());
        static::assertNull($user->getLastOrderDate());
    }

    public function testDeletingOrdersOfSeveralUsersAtOnceResetsMetadataOfUsersWithoutRemainingCompletedOrders(): void
    {
        [$onlyOrderOfFirstUser, $firstUserId] = $this->createCompletedOrder();
        [$firstOrderOfSecondUser, $secondUserId] = $this->createCompletedOrder();
        $this->createCompletedOrder($secondUserId);

        static::assertSame(1, $this->fetchUser($firstUserId)->getOrderCount());
        static::assertSame(2, $this->fetchUser($secondUserId)->getOrderCount());

        // one write deletes the only order of the first user and one of the two orders of the second user
        $this->orderRepository->delete([
            ['id' => $onlyOrderOfFirstUser],
            ['id' => $firstOrderOfSecondUser],
        ], $this->context);

        $firstUser = $this->fetchUser($firstUserId);
        static::assertSame(0, $firstUser->getOrderCount());
        static::assertSame(0, (int) $firstUser->getOrderTotalAmount());
        static::assertNull($firstUser->getLastOrderDate());

        $secondUser = $this->fetchUser($secondUserId);
        static::assertSame(1, $secondUser->getOrderCount());
        static::assertSame(10, (int) $secondUser->getOrderTotalAmount());
        static::assertNotNull($secondUser->getLastOrderDate());
    }

    /**
     * @return array{string, string}
     */
    private function createOrder(?string $userId = null): array
    {
        $orderId = Uuid::randomHex();
        $orderData = $this->getOrderData($orderId, $this->context, $userId);
        // the fixture uses a fixed deep link code, which has to be unique per order
        $orderData[0]['deepLinkCode'] = Random::getAlphanumericString(32);
        $userId = $orderData[0]['orderUser']['user']['id'];
        static::assertIsString($userId);

        $this->orderRepository->create($orderData, $this->context);

        return [$orderId, $userId];
    }

    /**
     * @return array{string, string}
     */
    private function createCompletedOrder(?string $userId = null): array
    {
        [$orderId, $userId] = $this->createOrder($userId);
        $this->transitionOrder($orderId, StateMachineTransitionActions::ACTION_PROCESS);
        $this->transitionOrder($orderId, StateMachineTransitionActions::ACTION_COMPLETE);

        return [$orderId, $userId];
    }

    private function fetchUser(string $userId): UserEntity
    {
        $user = $this->userRepository->search(new Criteria([$userId]), $this->context)->getEntities()->first();
        static::assertInstanceOf(UserEntity::class, $user);

        return $user;
    }

    private function transitionOrder(string $orderId, string $action): void
    {
        $this->stateMachineRegistry->transition(
            new Transition('order', $orderId, $action, 'stateId'),
            $this->context,
        );
    }
}
