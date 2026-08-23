<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Channel;

use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Routing\ChannelApiRouteScope;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Member\MemberCollection;
use Contena\Core\System\Member\MemberDefinition;
use Contena\Core\System\Member\MemberEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ChannelApiRouteScope::ID]])]
class MemberRoute extends AbstractMemberRoute
{
    /**
     * @internal
     *
     * @param EntityRepository<MemberCollection> $memberRepository
     */
    public function __construct(private readonly EntityRepository $memberRepository)
    {
    }

    public function getDecorated(): AbstractMemberRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/channel-api/account/member',
        name: 'channel-api.account.member',
        defaults: [
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true,
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED_ALLOW_GUEST => true,
            PlatformRequest::ATTRIBUTE_ENTITY => MemberDefinition::ENTITY_NAME,
        ],
        methods: [Request::METHOD_GET, Request::METHOD_POST],
    )]
    public function load(Request $request, ChannelContext $context, Criteria $criteria, MemberEntity $member): MemberResponse
    {
        $criteria->setIds([$member->getId()]);

        $memberEntity = $this->memberRepository->search($criteria, $context->getContext())->getEntities()->first();
        \assert($memberEntity !== null);

        return new MemberResponse($memberEntity);
    }
}
