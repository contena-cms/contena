<?php declare(strict_types=1);

namespace Contena\Core\System\User\Channel;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\RateLimiter\RateLimiter;
use Contena\Core\Framework\Routing\StoreApiRouteScope;
use Contena\Core\Framework\Util\Random;
use Contena\Core\Framework\Validation\BuildValidationEvent;
use Contena\Core\Framework\Validation\DataBag\DataBag;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\Framework\Validation\DataValidationDefinition;
use Contena\Core\Framework\Validation\DataValidator;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainEntity;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\SuccessResponse;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\System\User\Aggregate\UserRecovery\UserRecoveryCollection;
use Contena\Core\System\User\Aggregate\UserRecovery\UserRecoveryEntity;
use Contena\Core\System\User\Event\PasswordRecoveryUrlEvent;
use Contena\Core\System\User\Event\UserAccountRecoverRequestEvent;
use Contena\Core\System\User\Extension\SendPasswordRecoveryMailRouteExtension;
use Contena\Core\System\User\Service\EmailIdnConverter;
use Contena\Core\System\User\UserCollection;
use Contena\Core\System\User\UserEntity;
use Contena\Core\System\User\UserException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\EqualTo;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Validation\Exception\ConstraintViolationException;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class SendPasswordRecoveryMailRoute extends AbstractSendPasswordRecoveryMailRoute
{
    /**
     * @internal
     *
     * @param EntityRepository<UserCollection> $userRepository
     * @param EntityRepository<UserRecoveryCollection> $userRecoveryRepository
     */
    public function __construct(
        private readonly EntityRepository $userRepository,
        private readonly EntityRepository $userRecoveryRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly DataValidator $validator,
        private readonly SystemConfigService $systemConfigService,
        private readonly RequestStack $requestStack,
        private readonly RateLimiter $rateLimiter,
        private readonly ExtensionDispatcher $extensions,
    ) {
    }

    public function getDecorated(): AbstractSendPasswordRecoveryMailRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/store-api/account/recovery-password', name: 'store-api.account.recovery.send.mail', methods: ['POST'])]
    public function sendRecoveryMail(RequestDataBag $data, ChannelContext $context, bool $validateFrontendUrl = true): SuccessResponse
    {
        return $this->extensions->publish(
            name: SendPasswordRecoveryMailRouteExtension::NAME,
            extension: new SendPasswordRecoveryMailRouteExtension($data, $context, $validateFrontendUrl),
            function: $this->_sendRecoveryMail(...),
        );
    }

    private function _sendRecoveryMail(RequestDataBag $data, ChannelContext $context, bool $validateFrontendUrl): SuccessResponse
    {
        EmailIdnConverter::encodeDataBag($data);

        $this->validateRecoverEmail($data, $context, $validateFrontendUrl);

        if (($request = $this->requestStack->getMainRequest()) !== null) {
            $key = strtolower(\sprintf('%s-%s', $data->get('email'), $request->getClientIp()));
            $this->rateLimiter->ensureAccepted(RateLimiter::RESET_PASSWORD, $key);
        }

        try {
            $user = $this->getUserByEmail($data->get('email'), $context);
        } catch (UserException) {
            return new SuccessResponse();
        }

        $userId = $user->getId();

        $userIdCriteria = new Criteria();
        $userIdCriteria->addFilter(new EqualsFilter('userId', $userId));

        $repoContext = $context->getContext();

        $existingRecovery = $this->userRecoveryRepository->search($userIdCriteria, $repoContext)->getEntities()->first();
        if ($existingRecovery) {
            $this->deleteRecoveryForUser($existingRecovery, $repoContext);
        }

        $recoveryData = [
            'userId' => $userId,
            'hash' => Random::getAlphanumericString(32),
        ];

        $this->userRecoveryRepository->create([$recoveryData], $repoContext);

        $userRecovery = $this->userRecoveryRepository->search($userIdCriteria, $repoContext)->getEntities()->first();
        if (!$userRecovery) {
            throw UserException::userNotFoundByIdException($userId);
        }

        $hash = $userRecovery->getHash();

        $recoverUrl = $this->getRecoverUrl($context, $hash, $data->get('frontendUrl'), $userRecovery);

        $event = new UserAccountRecoverRequestEvent($context, $userRecovery, $recoverUrl);

        $this->eventDispatcher->dispatch($event, UserAccountRecoverRequestEvent::EVENT_NAME);

        return new SuccessResponse();
    }

    private function validateRecoverEmail(DataBag $data, ChannelContext $context, bool $validateFrontendUrl = true): void
    {
        $validation = new DataValidationDefinition('user.email.recover');

        $validation
            ->add(
                'email',
                new Email()
            );

        if ($validateFrontendUrl) {
            $validation
                ->add('frontendUrl', new NotBlank(), new Choice(choices: array_values($this->getDomainUrls($context))));
        }

        $this->dispatchValidationEvent($validation, $data, $context->getContext());

        $this->validator->validate($data->all(), $validation);

        $this->tryValidateEqualtoConstraint($data->all(), 'email', $validation);
    }

    /**
     * @return string[]
     */
    private function getDomainUrls(ChannelContext $context): array
    {
        $domains = $context->getChannel()->getDomains();
        if (!$domains) {
            return [];
        }

        return array_map(static fn (ChannelDomainEntity $domainEntity) => rtrim($domainEntity->getUrl(), '/'), $domains->getElements());
    }

    private function dispatchValidationEvent(DataValidationDefinition $definition, DataBag $data, Context $context): void
    {
        $validationEvent = new BuildValidationEvent($definition, $data, $context);
        $this->eventDispatcher->dispatch($validationEvent, $validationEvent->getName());
    }

    /**
     * @param array<string|int, string> $data
     *
     * @throws ConstraintViolationException
     */
    private function tryValidateEqualtoConstraint(array $data, string $field, DataValidationDefinition $validation): void
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

        $message = str_replace('{{ compared_value }}', $compareValue ?? '', $equalityValidation->message);

        $violations = new ConstraintViolationList();
        $violations->add(new ConstraintViolation($message, $equalityValidation->message, [], '', $field, $data[$field]));

        throw new ConstraintViolationException($violations, $data);
    }

    private function getUserByEmail(string $email, ChannelContext $context): UserEntity
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('user.active', 1))
            ->addFilter(new EqualsFilter('user.email', $email))
            ->addFilter(new EqualsFilter('user.guest', 0))
            ->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, [
                new EqualsFilter('user.boundChannelId', null),
                new EqualsFilter('user.boundChannelId', $context->getChannelId()),
            ]));

        $user = $this->userRepository->search($criteria, $context->getContext())->getEntities()->first();
        if (!$user) {
            throw UserException::userNotFound($email);
        }

        return $user;
    }

    private function deleteRecoveryForUser(UserRecoveryEntity $existingRecovery, Context $context): void
    {
        $recoveryData = [
            'id' => $existingRecovery->getId(),
        ];

        $this->userRecoveryRepository->delete([$recoveryData], $context);
    }

    private function getRecoverUrl(
        ChannelContext $context,
        string $hash,
        string $frontendUrl,
        UserRecoveryEntity $userRecovery
    ): string {
        $urlTemplate = $this->systemConfigService->get(
            'core.loginRegistration.pwdRecoverUrl',
            $context->getChannelId()
        );
        if (!\is_string($urlTemplate)) {
            $urlTemplate = '/account/recover/password?hash=%%RECOVERHASH%%';
        }

        $urlEvent = new PasswordRecoveryUrlEvent($context, $urlTemplate, $hash, $frontendUrl, $userRecovery);
        $this->eventDispatcher->dispatch($urlEvent);

        return rtrim($frontendUrl, '/') . str_replace(
            '%%RECOVERHASH%%',
            $hash,
            $urlEvent->getRecoveryUrl()
        );
    }
}
