<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Page\Account;

use PHPUnit\Framework\TestCase;
use Contena\Core\System\User\UserCollection;
use Contena\Core\Checkout\Order\OrderCollection;
use Contena\Core\Checkout\Order\OrderEntity;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Util\Random;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Channel\Context\ChannelContextService;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Frontend\Page\Account\Order\AccountOrderPageLoader;
use Contena\Frontend\Test\Page\FrontendPageTestBehaviour;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class AccountOrderPageLoaderTest extends TestCase
{
    use IntegrationTestBehaviour;
    use FrontendPageTestBehaviour;

    private ChannelContext $channel;

    /**
     * @var EntityRepository<UserCollection>
     */
    private EntityRepository $userRepository;

    /**
     * @var EntityRepository<OrderCollection>
     */
    private EntityRepository $orderRepository;

    protected function setUp(): void
    {
        $this->channel = $this->createChannelContext();
        $this->userRepository = static::getContainer()->get('user.repository');
        $this->orderRepository = static::getContainer()->get('order.repository');
    }

    public function testLogsInGuestById(): void
    {
        $context = Context::createDefaultContext();
        $unexpectedUser = $this->createUser();
        $expectedUser = $this->createUser();

        $unexpectedUser->setEmail('identical@contena.cn');
        $expectedUser->setEmail('identical@contena.cn');
        $expectedUser->setGuest(true);

        $this->userRepository->update([
            [
                'id' => $unexpectedUser->getId(),
                'email' => $unexpectedUser->getEmail(),
            ],
            [
                'id' => $expectedUser->getId(),
                'email' => $expectedUser->getEmail(),
                'guest' => $expectedUser->getGuest(),
            ],
        ], $context);

        $channel = static::getContainer()->get(ChannelContextFactory::class)->create(
            $this->channel->getToken(),
            $this->channel->getChannelId(),
            [ChannelContextService::CUSTOMER_ID => $expectedUser->getId()],
        );
        $orderId = $this->placeRandomOrder($channel);
        $order = $this->orderRepository->search(new Criteria([$orderId]), $context)->getEntities()->first();
        static::assertNotNull($order);
        $this->orderRepository->update([
            [
                'id' => $order->getId(),
                'deepLinkCode' => $deepLinkCode = Random::getBase64UrlString(32),
                'orderUser.userId' => $expectedUser->getId(),
            ],
        ], $context);

        $page = $this->getPageLoader()->load(
            new Request(
                [
                    'email' => $expectedUser->getEmail(),
                    'zipcode' => '12345',
                ],
                [],
                [
                    'deepLinkCode' => $deepLinkCode,
                ]
            ),
            $this->channel
        );

        static::assertSame(
            $expectedUser->getId(),
            $page->getOrders()->getEntities()->first()?->getOrderUser()?->getUserId(),
        );
    }

    public function testLoad(): void
    {
        $channel = $this->createChannelContextWithLoggedInUserAndWithNavigation();

        $orderId = $this->placeRandomOrder($channel);
        $order = $this->orderRepository->search(new Criteria([$orderId]), $channel->getContext())->getEntities()->first();
        static::assertInstanceOf(OrderEntity::class, $order);
        $deepLinkCode = $order->getDeepLinkCode();

        $page = $this->getPageLoader()->load(
            new Request(
                [
                    'email' => $channel->getUser()?->getEmail(),
                    'zipcode' => '12345',
                ],
                [],
                [
                    'deepLinkCode' => $deepLinkCode,
                ]
            ),
            $channel
        );

        $order = $page->getOrders()->getEntities()->first();

        static::assertInstanceOf(OrderEntity::class, $order);
        static::assertNotNull($order->getPrimaryOrderDelivery());
        static::assertNotNull($order->getPrimaryOrderTransaction());
        static::assertNotNull($order->getPrimaryOrderTransactionId());
        static::assertNotNull($order->getPrimaryOrderDeliveryId());

        static::assertNotNull($page->getDeepLinkCode());
        static::assertSame($deepLinkCode, $order->getDeepLinkCode());
    }

    protected function getPageLoader(): AccountOrderPageLoader
    {
        return static::getContainer()->get(AccountOrderPageLoader::class);
    }
}
