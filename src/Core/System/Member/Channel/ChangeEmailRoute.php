<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Channel;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Routing\ChannelApiRouteScope;
use Contena\Core\Framework\Validation\BuildValidationEvent;
use Contena\Core\Framework\Validation\DataBag\DataBag;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\Framework\Validation\DataValidationDefinition;
use Contena\Core\Framework\Validation\DataValidator;
use Contena\Core\Framework\Validation\Exception\ConstraintViolationException;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\SuccessResponse;
use Contena\Core\System\Member\Aggregate\MemberRecovery\MemberRecoveryCollection;
use Contena\Core\System\Member\MemberCollection;
use Contena\Core\System\Member\MemberEntity;
use Contena\Core\System\Member\Service\EmailIdnConverter;
use Contena\Core\System\Member\Validation\Constraint\MemberEmailUnique;
use Contena\Core\System\Member\Validation\Constraint\MemberPasswordMatches;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\EqualTo;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Route(
    defaults: [
        PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ChannelApiRouteScope::ID],
        PlatformRequest::ATTRIBUTE_CONTEXT_TOKEN_REQUIRED => true,
    ]
)]
class ChangeEmailRoute extends AbstractChangeEmailRoute
{
    /**
     * @internal
     *
     * @param EntityRepository<MemberCollection> $memberRepository
     * @param EntityRepository<MemberRecoveryCollection> $memberRecoveryRepository
     */
    public function __construct(
        private readonly EntityRepository $memberRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly DataValidator $validator,
        private readonly EntityRepository $memberRecoveryRepository
    ) {
    }

    public function getDecorated(): AbstractChangeEmailRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/channel-api/account/change-email',
        name: 'channel-api.account.change-email',
        defaults: [PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true],
        methods: [Request::METHOD_POST]
    )]
    public function change(RequestDataBag $requestDataBag, ChannelContext $context, MemberEntity $member): SuccessResponse
    {
        EmailIdnConverter::encodeDataBag($requestDataBag);
        EmailIdnConverter::encodeDataBag($requestDataBag, 'emailConfirmation');

        $this->validateEmail($requestDataBag, $context);

        $memberData = [
            'id' => $member->getId(),
            'email' => $requestDataBag->get('email'),
        ];

        $this->memberRepository->update([$memberData], $context->getContext());

        $criteria = new Criteria()->addFilter(new EqualsFilter('memberId', $member->getId()));
        $ids = $this->memberRecoveryRepository->searchIds($criteria, $context->getContext())->getIds();
        if ($ids !== []) {
            $this->memberRecoveryRepository->delete(array_map(static fn ($id) => ['id' => $id], $ids), $context->getContext());
        }

        return new SuccessResponse();
    }

    private function validateEmail(DataBag $data, ChannelContext $context): void
    {
        $validation = new DataValidationDefinition('member.email.update');

        $validation
            ->add(
                'email',
                new Email(),
                new EqualTo(propertyPath: 'emailConfirmation'),
                new MemberEmailUnique(channelContext: $context)
            )
            ->add('password', new MemberPasswordMatches(channelContext: $context));

        $this->dispatchValidationEvent($validation, $data, $context->getContext());

        $this->validator->validate($data->all(), $validation);

        $this->tryValidateEqualToConstraint($data->all(), 'email', $validation);
    }

    private function dispatchValidationEvent(DataValidationDefinition $definition, DataBag $data, Context $context): void
    {
        $validationEvent = new BuildValidationEvent($definition, $data, $context);
        $this->eventDispatcher->dispatch($validationEvent, $validationEvent->getName());
    }

    /**
     * @param array<string, mixed> $data
     */
    private function tryValidateEqualToConstraint(array $data, string $field, DataValidationDefinition $validation): void
    {
        $validations = $validation->getProperties();

        if (!\array_key_exists($field, $validations)) {
            return;
        }

        $fieldValidations = $validations[$field];

        $equalityValidation = null;

        foreach ($fieldValidations as $emailValidation) {
            if ($emailValidation instanceof EqualTo) {
                $equalityValidation = $emailValidation;

                break;
            }
        }

        if (!$equalityValidation instanceof EqualTo) {
            return;
        }

        $compareValue = $data[$equalityValidation->propertyPath ?? ''] ?? null;
        if ($data[$field] === $compareValue) {
            return;
        }

        $message = str_replace('{{ compared_value }}', $compareValue, $equalityValidation->message);

        $violations = new ConstraintViolationList();
        $violations->add(new ConstraintViolation($message, $equalityValidation->message, [], '', $field, $data[$field]));

        throw new ConstraintViolationException($violations, $data);
    }
}
