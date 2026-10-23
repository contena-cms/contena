<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\Context;

use Contena\Core\Framework\Api\Context\ChannelApiSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\System\User\Aggregate\UserAddress\UserAddressCollection;
use Contena\Core\System\User\Aggregate\UserGroup\UserGroupCollection;
use Contena\Core\System\User\UserCollection;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Event\ChannelContextPermissionsChangedEvent;
use Contena\Core\System\User\Aggregate\UserAddress\UserAddressEntity;
use Contena\Core\System\User\UserEntity;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;

class ChannelContextFactory extends AbstractChannelContextFactory
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EntityRepository $userRepository,
        private readonly EntityRepository $userGroupRepository,
        private readonly EntityRepository $userAddressRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly EntityRepository $currencyCountryRepository
    ) {
    }


    public function getDecorated(): AbstractChannelContextFactory
    {
        throw new DecorationPatternException(self::class);
    }

    public function create(string $token, string $channelId, array $options = []): ChannelContext
    {
        // we split the context generation to allow caching of the base context
        $base = $this->baseChannelContextFactory->create($channelId, $options);

        $user = null;
        if (\is_string($options[ChannelContextService::CUSTOMER_ID] ?? null)) {
            // load logged in user and set active addresses
            $user = $this->loadUser($options, $base->getContext());
        }

        if ($user !== null) {
            $criteria = new Criteria([$user->getGroupId()]);
            $criteria->setTitle('context-factory::user-group');
            $userGroup = $this->userGroupRepository->search($criteria, $base->getContext())->getEntities()->first() ?? $base->getCurrentUserGroup();
        } else {
            $userGroup = $base->getCurrentUserGroup();
        }

        $context = new Context(
            $base->getContext()->getSource(),
            [],
            $base->getCurrencyId(),
            $base->getContext()->getLanguageIdChain(),
            $base->getContext()->getVersionId(),
            $base->getCurrency()->getFactor(),
            true,
            Context::TAX_STATE_GROSS,
            $base->getItemRounding()
        );

        $channel = $base->getChannel();

        $domainId = \is_string($options[ChannelContextService::DOMAIN_ID] ?? null) ? $options[ChannelContextService::DOMAIN_ID] : null;

        $channelContext = new ChannelContext(
            $context,
            $token,
            $domainId,
            $channel,
            $base->getCurrency(),
            $userGroup,
            $user,
            $base->getItemRounding(),
            $base->getTotalRounding(),
            $base->getLanguageInfo(),
        );

        if (\is_array($options[ChannelContextService::PERMISSIONS] ?? null)) {
            $channelContext->setPermissions($options[ChannelContextService::PERMISSIONS]);

            $event = new ChannelContextPermissionsChangedEvent($channelContext, $options[ChannelContextService::PERMISSIONS]);
            $this->eventDispatcher->dispatch($event);

            $channelContext->lockPermissions();
        }

        if (\is_string($options[ChannelContextService::IMITATING_USER_ID] ?? null)) {
            $channelContext->setImitatingUserId($options[ChannelContextService::IMITATING_USER_ID]);
        }

        return $channelContext;
    }



    /**
     * @param array<string, mixed> $options
     */
    private function loadUser(array $options, Context $context): ?UserEntity
    {
        $addressIds = [];
        $userId = $options[ChannelContextService::CUSTOMER_ID];

        $criteria = new Criteria([$userId]);
        $criteria->setTitle('context-factory::user');

        $source = $context->getSource();
        \assert($source instanceof ChannelApiSource);

        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, [
            new EqualsFilter('user.boundChannelId', null),
            new EqualsFilter('user.boundChannelId', $source->getChannelId()),
        ]));

        $user = $this->userRepository->search($criteria, $context)->getEntities()->get($userId);
        // active check here instead of DAL filter due to no DB index
        if (!$user?->getActive()) {
            return null;
        }

        $activeBillingAddressId = $options[ChannelContextService::BILLING_ADDRESS_ID] ?? $user->getDefaultBillingAddressId();
        $activeShippingAddressId = $options[ChannelContextService::SHIPPING_ADDRESS_ID] ?? $user->getDefaultShippingAddressId();

        $addressIds[] = $activeBillingAddressId;
        $addressIds[] = $activeShippingAddressId;
        $addressIds[] = $user->getDefaultBillingAddressId();
        $addressIds[] = $user->getDefaultShippingAddressId();

        $criteria = new Criteria(\array_unique($addressIds));
        $criteria->setTitle('context-factory::addresses');
        $criteria->addAssociation('country');
        $criteria->addAssociation('countryState');

        $addresses = $this->userAddressRepository->search($criteria, $context)->getEntities();

        // a default address id can point to a deleted row, and the setters are not nullable yet
        // an existing requested address wins over an injected one, e.g. the order's address during recalculation
        $activeBillingAddress = $addresses->get($options[ChannelContextService::BILLING_ADDRESS_ID] ?? '')
            ?? $this->getInjectedAddress($options, ChannelContextService::BILLING_ADDRESS)
            ?? $addresses->get($user->getDefaultBillingAddressId());
        if ($activeBillingAddress !== null) {
            $user->setActiveBillingAddress($activeBillingAddress);
        }

        $activeShippingAddress = $addresses->get($options[ChannelContextService::SHIPPING_ADDRESS_ID] ?? '')
            ?? $this->getInjectedAddress($options, ChannelContextService::SHIPPING_ADDRESS)
            ?? $addresses->get($user->getDefaultShippingAddressId());
        if ($activeShippingAddress !== null) {
            $user->setActiveShippingAddress($activeShippingAddress);
        }

        $defaultBillingAddress = $addresses->get($user->getDefaultBillingAddressId());
        if ($defaultBillingAddress !== null) {
            $user->setDefaultBillingAddress($defaultBillingAddress);
        }

        $defaultShippingAddress = $addresses->get($user->getDefaultShippingAddressId());
        if ($defaultShippingAddress !== null) {
            $user->setDefaultShippingAddress($defaultShippingAddress);
        }

        return $user;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function getInjectedAddress(array $options, string $option): ?UserAddressEntity
    {
        $address = $options[$option] ?? null;

        return $address instanceof UserAddressEntity ? $address : null;
    }

}
