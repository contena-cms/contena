<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Channel;

use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Routing\ChannelApiRouteScope;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Member\Aggregate\MemberGroup\MemberGroupCollection;
use Contena\Core\System\Member\MemberException;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ChannelApiRouteScope::ID]])]
class MemberGroupRegistrationSettingsRoute extends AbstractMemberGroupRegistrationSettingsRoute
{
    /**
     * @internal
     *
     * @param EntityRepository<MemberGroupCollection> $memberGroupRepository
     */
    public function __construct(private readonly EntityRepository $memberGroupRepository)
    {
    }

    public function getDecorated(): AbstractMemberGroupRegistrationSettingsRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * Though this is a GET route, caching was not added as the output may be altered depending on dynamic rules,
     * which is not taken into account during the cache hash calculation.
     */
    #[Route(path: '/channel-api/member-group-registration/config/{memberGroupId}', name: 'channel-api.member-group-registration.config', methods: ['GET'])]
    public function load(string $memberGroupId, ChannelContext $context): MemberGroupRegistrationSettingsRouteResponse
    {
        $criteria = new Criteria([$memberGroupId])
            ->addFilter(new EqualsFilter('registrationActive', 1))
            ->addFilter(new EqualsFilter('registrationChannels.id', $context->getChannelId()));

        $result = $this->memberGroupRepository->search($criteria, $context->getContext());
        if ($result->getTotal() === 0) {
            throw MemberException::memberGroupRegistrationConfigurationNotFound($memberGroupId);
        }

        $memberGroup = $result->getEntities()->first();
        \assert($memberGroup !== null);

        return new MemberGroupRegistrationSettingsRouteResponse($memberGroup);
    }
}
