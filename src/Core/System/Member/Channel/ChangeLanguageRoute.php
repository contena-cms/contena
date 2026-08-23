<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Channel;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Validation\EntityExists;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Routing\ChannelApiRouteScope;
use Contena\Core\Framework\Validation\BuildValidationEvent;
use Contena\Core\Framework\Validation\Constraint\Uuid;
use Contena\Core\Framework\Validation\DataBag\DataBag;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\Framework\Validation\DataValidationDefinition;
use Contena\Core\Framework\Validation\DataValidator;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\SuccessResponse;
use Contena\Core\System\Member\MemberCollection;
use Contena\Core\System\Member\MemberEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Route(
    defaults: [
        PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ChannelApiRouteScope::ID],
        PlatformRequest::ATTRIBUTE_CONTEXT_TOKEN_REQUIRED => true,
    ]
)]
class ChangeLanguageRoute extends AbstractChangeLanguageRoute
{
    /**
     * @internal
     *
     * @param EntityRepository<MemberCollection> $memberRepository
     */
    public function __construct(
        private readonly EntityRepository $memberRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly DataValidator $validator
    ) {
    }

    public function getDecorated(): AbstractChangeLanguageRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/channel-api/account/change-language',
        name: 'channel-api.account.change-language',
        defaults: [PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true],
        methods: [Request::METHOD_POST]
    )]
    public function change(RequestDataBag $requestDataBag, ChannelContext $context, MemberEntity $member): SuccessResponse
    {
        $this->validateLanguageId($requestDataBag, $context);

        $memberData = [
            'id' => $member->getId(),
            'languageId' => $requestDataBag->get('languageId'),
        ];

        $this->memberRepository->update([$memberData], $context->getContext());

        return new SuccessResponse();
    }

    private function validateLanguageId(DataBag $data, ChannelContext $context): void
    {
        $validation = new DataValidationDefinition('member.language.update');

        $languageCriteria = new Criteria([$data->get('languageId')]);
        $languageCriteria->addFilter(new EqualsFilter('channels.id', $context->getChannelId()));

        $validation->add('languageId', new Uuid())
            ->add('languageId', new EntityExists(entity: 'language', context: $context->getContext(), criteria: $languageCriteria));

        $this->dispatchValidationEvent($validation, $data, $context->getContext());

        $this->validator->validate($data->all(), $validation);
    }

    private function dispatchValidationEvent(DataValidationDefinition $definition, DataBag $data, Context $context): void
    {
        $validationEvent = new BuildValidationEvent($definition, $data, $context);
        $this->eventDispatcher->dispatch($validationEvent, $validationEvent->getName());
    }
}
