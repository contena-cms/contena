<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\Context;

use Contena\Core\Framework\Api\Context\ChannelApiSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Event\ChannelContextPermissionsChangedEvent;
use Contena\Core\System\Member\Aggregate\MemberGroup\MemberGroupCollection;
use Contena\Core\System\Member\MemberCollection;
use Contena\Core\System\Member\MemberEntity;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ChannelContextFactory extends AbstractChannelContextFactory
{
    /**
     * @internal
     *
     * @param EntityRepository<MemberCollection> $memberRepository
     * @param EntityRepository<MemberGroupCollection> $memberGroupRepository
     */
    public function __construct(
        private readonly EntityRepository $memberRepository,
        private readonly EntityRepository $memberGroupRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly AbstractBaseChannelContextFactory $baseChannelContextFactory,
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

        $member = null;
        if (\is_string($options[ChannelContextService::MEMBER_ID] ?? null)) {
            $member = $this->loadMember($options, $base->getContext());
        }

        $memberGroup = $base->getCurrentMemberGroup();
        if ($member !== null) {
            $criteria = new Criteria([$member->getGroupId()]);
            $criteria->setTitle('context-factory::member-group');
            $memberGroup = $this->memberGroupRepository->search($criteria, $base->getContext())->getEntities()->first() ?? $memberGroup;
        }

        $domainId = \is_string($options[ChannelContextService::DOMAIN_ID] ?? null) ? $options[ChannelContextService::DOMAIN_ID] : null;
        $channelContext = new ChannelContext(
            $base->getContext(),
            $token,
            $domainId,
            $base->getChannel(),
            $memberGroup,
            $base->getCountry(),
            $member,
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
    private function loadMember(array $options, Context $context): ?MemberEntity
    {
        $memberId = $options[ChannelContextService::MEMBER_ID];

        $criteria = new Criteria([$memberId]);
        $criteria->setTitle('context-factory::member');

        $source = $context->getSource();
        \assert($source instanceof ChannelApiSource);

        $criteria->addFilter(new EqualsFilter('channelId', $source->getChannelId()));
        $member = $this->memberRepository->search($criteria, $context)->getEntities()->get($memberId);

        if (!$member?->getActive()) {
            return null;
        }

        return $member;
    }
}
