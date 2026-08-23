<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Channel;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
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
use Contena\Core\System\Channel\ContextTokenResponse;
use Contena\Core\System\Member\Event\MemberPasswordChangedEvent;
use Contena\Core\System\Member\MemberCollection;
use Contena\Core\System\Member\MemberEntity;
use Contena\Core\System\Member\Validation\Constraint\MemberPasswordMatches;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints\EqualTo;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Route(
    defaults: [
        PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ChannelApiRouteScope::ID],
        PlatformRequest::ATTRIBUTE_CONTEXT_TOKEN_REQUIRED => true,
    ]
)]
class ChangePasswordRoute extends AbstractChangePasswordRoute
{
    /**
     * @internal
     *
     * @param EntityRepository<MemberCollection> $memberRepository
     */
    public function __construct(
        private readonly EntityRepository $memberRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly SystemConfigService $systemConfigService,
        private readonly DataValidator $validator
    ) {
    }

    public function getDecorated(): AbstractChangePasswordRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/channel-api/account/change-password',
        name: 'channel-api.account.change-password',
        defaults: [PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true],
        methods: [Request::METHOD_POST]
    )]
    public function change(RequestDataBag $requestDataBag, ChannelContext $context, MemberEntity $member): ContextTokenResponse
    {
        $this->validatePasswordFields($requestDataBag, $context);

        $memberData = [
            'id' => $member->getId(),
            'password' => $requestDataBag->get('newPassword'),
        ];

        $this->memberRepository->update([$memberData], $context->getContext());

        $this->eventDispatcher->dispatch(new MemberPasswordChangedEvent($context, $member));

        return new ContextTokenResponse($context->getToken());
    }

    private function dispatchValidationEvent(DataValidationDefinition $definition, DataBag $data, Context $context): void
    {
        $validationEvent = new BuildValidationEvent($definition, $data, $context);
        $this->eventDispatcher->dispatch($validationEvent, $validationEvent->getName());
    }

    /**
     * @throws ConstraintViolationException
     */
    private function validatePasswordFields(DataBag $data, ChannelContext $context): void
    {
        $definition = new DataValidationDefinition('member.password.update');

        $minPasswordLength = $this->systemConfigService->getInt('core.loginRegistration.passwordMinLength', $context->getChannelId());
        if ($minPasswordLength < 0) {
            $minPasswordLength = null;
        }
        $definition
            ->add('newPassword', new NotBlank(), new Length(min: $minPasswordLength), new EqualTo(propertyPath: 'newPasswordConfirm'))
            ->add('password', new MemberPasswordMatches(channelContext: $context));

        $this->dispatchValidationEvent($definition, $data, $context->getContext());

        $this->validator->validate($data->all(), $definition);

        $this->tryValidateEqualToConstraint($data->all(), 'newPassword', $definition);
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
