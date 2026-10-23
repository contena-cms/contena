<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\BlogExport\Tracking;

use PHPUnit\Framework\TestCase;
use Contena\Core\System\User\UserCollection;
use Contena\Core\Checkout\Order\OrderCollection;
use Contena\Core\Content\BlogExport\Tracking\ChannelTrackingUserCollection;
use Contena\Core\Content\BlogExport\Tracking\ChannelTrackingUserEntity;
use Contena\Core\Content\BlogExport\Tracking\ChannelTrackingListener;
use Contena\Core\Content\BlogExport\Tracking\ChannelTrackingOrderCollection;
use Contena\Core\Content\BlogExport\Tracking\ChannelTrackingOrderEntity;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\ChannelApiTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\PlatformRequest;
use Contena\Core\Test\Integration\Builder\User\UserBuilder;
use Contena\Core\Test\Integration\Builder\Order\OrderBuilder;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * @internal
 */
class ChannelTrackingListenerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use ChannelApiTestBehaviour;

    private Context $context;

    /**
     * @var EntityRepository<OrderCollection>
     */
    private EntityRepository $orderRepository;

    /**
     * @var EntityRepository<UserCollection>
     */
    private EntityRepository $userRepository;

    /**
     * @var EntityRepository<ChannelTrackingOrderCollection>
     */
    private EntityRepository $trackingOrderRepository;

    /**
     * @var EntityRepository<ChannelTrackingUserCollection>
     */
    private EntityRepository $trackingUserRepository;

    private ChannelTrackingListener $listener;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
        $this->orderRepository = static::getContainer()->get('order.repository');
        $this->userRepository = static::getContainer()->get('user.repository');

        $this->trackingOrderRepository = static::getContainer()->get('channel_tracking_order.repository');

        $this->trackingUserRepository = static::getContainer()->get('channel_tracking_customer.repository');

        $listener = static::getContainer()->get(ChannelTrackingListener::class);
        static::assertInstanceOf(ChannelTrackingListener::class, $listener);
        $this->listener = $listener;
    }

    public function testOrderTrackingRecordIsWrittenOnOrderInsert(): void
    {
        $agenticChannelId = $this->createAgenticCommerceChannel();

        $this->pushRequestWithReferralCode($agenticChannelId);

        $ids = new IdsCollection();
        $order = (new OrderBuilder($ids, 'order-1'))
            ->add('channelId', TestDefaults::CHANNEL)
            ->build();

        $this->orderRepository->create([$order], $this->context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderId', $ids->get('order-1')));

        $result = $this->trackingOrderRepository->search($criteria, $this->context)->getEntities();

        static::assertCount(1, $result);

        $tracking = $result->first();
        static::assertInstanceOf(ChannelTrackingOrderEntity::class, $tracking);
        static::assertSame($agenticChannelId, $tracking->getChannelId());
    }

    public function testUserTrackingRecordIsWrittenOnUserInsert(): void
    {
        $agenticChannelId = $this->createAgenticCommerceChannel();

        $this->pushRequestWithReferralCode($agenticChannelId);

        $ids = new IdsCollection();
        $user = (new UserBuilder($ids, 'user-1'))->build();

        $this->userRepository->create([$user], $this->context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('userId', $ids->get('user-1')));

        $result = $this->trackingUserRepository->search($criteria, $this->context)->getEntities();

        static::assertCount(1, $result);

        $tracking = $result->first();
        static::assertInstanceOf(ChannelTrackingUserEntity::class, $tracking);
        static::assertSame($agenticChannelId, $tracking->getChannelId());
    }

    public function testNoTrackingWrittenWithoutReferralCodeInSession(): void
    {
        $ids = new IdsCollection();
        $user = (new UserBuilder($ids, 'user-1'))->build();

        $this->userRepository->create([$user], $this->context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('userId', $ids->get('user-1')));

        $result = $this->trackingUserRepository->search($criteria, $this->context)->getEntities();

        static::assertCount(0, $result);
    }

    public function testNoTrackingWrittenForNonAgenticChannel(): void
    {
        $frontendChannelId = $this->createChannel(['typeId' => Defaults::CHANNEL_TYPE_FRONTEND])['id'];

        $session = $this->getSession();
        $request = new Request([ChannelTrackingListener::QUERY_PARAM => $frontendChannelId]);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, ['frontend']);
        $request->setSession($session);

        static::getContainer()->get(RequestStack::class)->push($request);

        $event = new ControllerEvent(
            static::createStub(HttpKernelInterface::class),
            static fn () => new \stdClass(),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
        );

        $this->listener->storeReferralCode($event);

        static::assertFalse($session->has(ChannelTrackingListener::SESSION_KEY_REFERRAL_CODE));
    }

    public function testUpdateDoesNotCreateTrackingRecord(): void
    {
        $agenticChannelId = $this->createAgenticCommerceChannel();

        $ids = new IdsCollection();
        $user = (new UserBuilder($ids, 'user-1'))->build();

        $this->userRepository->create([$user], $this->context);

        $this->pushRequestWithReferralCode($agenticChannelId);

        $this->userRepository->update([
            ['id' => $ids->get('user-1'), 'firstName' => 'Updated'],
        ], $this->context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('userId', $ids->get('user-1')));

        $result = $this->trackingUserRepository->search($criteria, $this->context)->getEntities();

        static::assertCount(0, $result);
    }

    private function createAgenticCommerceChannel(): string
    {
        $channelId = Uuid::randomHex();

        $this->createChannel([
            'id' => $channelId,
            'typeId' => Defaults::CHANNEL_TYPE_AGENTIC_COMMERCE,
        ]);

        return $channelId;
    }

    private function pushRequestWithReferralCode(string $referralCode): void
    {
        $session = $this->getSession();
        $session->set(ChannelTrackingListener::SESSION_KEY_REFERRAL_CODE, $referralCode);

        $request = new Request();
        $request->setSession($session);

        static::getContainer()->get(RequestStack::class)->push($request);
    }
}
