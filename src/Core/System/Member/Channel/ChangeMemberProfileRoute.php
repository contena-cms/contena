<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Channel;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Event\DataMappingEvent;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Routing\ChannelApiRouteScope;
use Contena\Core\Framework\Validation\BuildValidationEvent;
use Contena\Core\Framework\Validation\DataBag\DataBag;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\Framework\Validation\DataValidationDefinition;
use Contena\Core\Framework\Validation\DataValidationFactoryInterface;
use Contena\Core\Framework\Validation\DataValidator;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelApiCustomFieldMapper;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\SuccessResponse;
use Contena\Core\System\Member\MemberCollection;
use Contena\Core\System\Member\MemberDefinition;
use Contena\Core\System\Member\MemberEntity;
use Contena\Core\System\Member\MemberEvents;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Route(
    defaults: [
        PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ChannelApiRouteScope::ID],
        PlatformRequest::ATTRIBUTE_CONTEXT_TOKEN_REQUIRED => true,
    ]
)]
class ChangeMemberProfileRoute extends AbstractChangeMemberProfileRoute
{
    /**
     * @internal
     *
     * @param EntityRepository<MemberCollection> $memberRepository
     */
    public function __construct(
        private readonly EntityRepository $memberRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly DataValidator $validator,
        private readonly DataValidationFactoryInterface $memberProfileValidationFactory,
        private readonly ChannelApiCustomFieldMapper $channelApiCustomFieldMapper,
    ) {
    }

    public function getDecorated(): AbstractChangeMemberProfileRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/channel-api/account/change-profile',
        name: 'channel-api.account.change-profile',
        defaults: [
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true,
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED_ALLOW_GUEST => true,
        ],
        methods: [Request::METHOD_POST]
    )]
    public function change(RequestDataBag $data, ChannelContext $context, MemberEntity $member): SuccessResponse
    {
        $validation = $this->memberProfileValidationFactory->update($context);

        $this->dispatchValidationEvent($validation, $data, $context->getContext());

        $this->validator->validate($data->all(), $validation);

        $memberData = $data->only('name', 'phoneNumber', 'title');

        if ($birthday = $this->getBirthday($data)) {
            $memberData['birthday'] = $birthday;
        }

        if ($data->get('customFields') instanceof RequestDataBag) {
            $memberData['customFields'] = $this->channelApiCustomFieldMapper->map(
                MemberDefinition::ENTITY_NAME,
                $data->get('customFields')
            );
            if ($memberData['customFields'] === []) {
                unset($memberData['customFields']);
            }
        }

        $mappingEvent = new DataMappingEvent($data, $memberData, $context->getContext());
        $this->eventDispatcher->dispatch($mappingEvent, MemberEvents::MAPPING_MEMBER_PROFILE_SAVE);

        $memberData = $mappingEvent->getOutput();

        $memberData['id'] = $member->getId();

        $this->memberRepository->update([$memberData], $context->getContext());

        return new SuccessResponse();
    }

    private function dispatchValidationEvent(DataValidationDefinition $definition, DataBag $data, Context $context): void
    {
        $validationEvent = new BuildValidationEvent($definition, $data, $context);
        $this->eventDispatcher->dispatch($validationEvent, $validationEvent->getName());
    }

    private function getBirthday(DataBag $data): ?\DateTimeInterface
    {
        $birthdayDay = $data->get('birthdayDay');
        $birthdayMonth = $data->get('birthdayMonth');
        $birthdayYear = $data->get('birthdayYear');

        if (!\is_numeric($birthdayDay) || !\is_numeric($birthdayMonth) || !\is_numeric($birthdayYear)) {
            return null;
        }

        return new \DateTime(\sprintf(
            '%s-%s-%s',
            $birthdayYear,
            $birthdayMonth,
            $birthdayDay
        ));
    }
}
