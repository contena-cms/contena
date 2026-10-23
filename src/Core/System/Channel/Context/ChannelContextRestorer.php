<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\Context;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Event\ChannelContextRestorerOrderCriteriaEvent;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\ChannelException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class ChannelContextRestorer
{
    /**
     * @internal
     *
     * @param EntityRepository<OrderCollection> $orderRepository
     */
    public function __construct(
        private readonly AbstractChannelContextFactory $factory,
        private readonly CartRuleLoader $cartRuleLoader,
        private readonly OrderConverter $orderConverter,
        private readonly EntityRepository $orderRepository,
        private readonly Connection $connection,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed, load the order and use `OrderConverter::assembleChannelContext()` instead. Pass the result to `CartRuleLoader::loadByCart()` if the rules have to be re-evaluated.
     *
     * @param array<string, string|array<string,bool>|null> $overrideOptions
     *
     * @throws InconsistentCriteriaIdsException
     */
    public function restoreByOrder(string $orderId, Context $context, array $overrideOptions = []): ChannelContext
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', OrderConverter::class . '::assembleChannelContext')
        );

        $order = $this->getOrderById($orderId, $context);
        if ($order === null) {
            throw ChannelException::orderNotFound($orderId);
        }

        if ($order->getOrderUser() === null) {
            throw ChannelException::missingAssociation('orderUser');
        }

        $channelContext = $this->orderConverter->assembleChannelContext($order, $context, $overrideOptions);

        $cart = $this->orderConverter->convertToCart($order, $channelContext->getContext());
        $this->cartRuleLoader->loadByCart(
            $channelContext,
            $cart,
            new CartBehavior($channelContext->getPermissions()),
            true
        );

        return $channelContext;
    }

    /**
     * @param array<string> $overrideOptions
     *
     * @throws Exception
     */
    public function restoreByUser(string $userId, Context $context, array $overrideOptions = []): ChannelContext
    {
        $user = $this->connection->createQueryBuilder()
            ->select(
                'LOWER(HEX(language_id))',
                'LOWER(HEX(user_group_id))',
                'LOWER(HEX(channel_id))',
            )
            ->from('user')
            ->where('id = :id')
            ->setParameter('id', Uuid::fromHexToBytes($userId))
            ->executeQuery()
            ->fetchAssociative();

        if (!$user) {
            throw ChannelException::userNotFoundByIdException($userId);
        }

        [$languageId, $groupId, $channelId] = array_values($user);
        $options = [
            ChannelContextService::LANGUAGE_ID => $languageId,
            ChannelContextService::CUSTOMER_ID => $userId,
            ChannelContextService::CUSTOMER_GROUP_ID => $groupId,
            ChannelContextService::VERSION_ID => $context->getVersionId(),
        ];

        $options = array_merge($options, $overrideOptions);

        $token = Uuid::randomHex();
        $channelContext = $this->factory->create(
            $token,
            $channelId,
            $options
        );

        $this->cartRuleLoader->loadByToken($channelContext, $token);
        $channelContext->getContext()->addState(...$context->getStates());

        return $channelContext;
    }

    /**
     * @throws InconsistentCriteriaIdsException
     */
    private function getOrderById(string $orderId, Context $context): ?OrderEntity
    {
        $criteria = (new Criteria([$orderId]))
            ->addAssociation('primaryOrderTransaction')
            ->addAssociation('primaryOrderDelivery')
            ->addAssociation('lineItems')
            ->addAssociation('currency')
            ->addAssociation('deliveries')
            ->addAssociation('language.locale')
            ->addAssociation('orderUser.user')
            ->addAssociation('billingAddress')
            ->addAssociation('transactions.stateMachineState');

        $event = Feature::silent(
            'v6.8.0.0',
            static fn (): ChannelContextRestorerOrderCriteriaEvent => new ChannelContextRestorerOrderCriteriaEvent($criteria, $context),
        );
        $this->eventDispatcher->dispatch($event);

        return $this->orderRepository->search($criteria, $context)->getEntities()->get($orderId);
    }
}
