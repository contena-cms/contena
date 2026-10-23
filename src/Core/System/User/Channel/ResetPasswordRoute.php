<?php declare(strict_types=1);

namespace Contena\Core\System\User\Channel;

use Psr\Clock\ClockInterface;
use Contena\Core\System\User\Aggregate\UserRecovery\UserRecoveryCollection;
use Contena\Core\System\User\Aggregate\UserRecovery\UserRecoveryEntity;
use Contena\Core\System\User\UserCollection;
use Contena\Core\System\User\UserException;
use Contena\Core\System\User\Extension\ResetPasswordRouteExtension;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\RateLimiter\RateLimiter;
use Contena\Core\Framework\Routing\StoreApiRouteScope;
use Contena\Core\Framework\Validation\BuildValidationEvent;
use Contena\Core\Framework\Validation\DataBag\DataBag;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\Framework\Validation\DataValidationDefinition;
use Contena\Core\Framework\Validation\DataValidationFactoryInterface;
use Contena\Core\Framework\Validation\DataValidator;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\SuccessResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints\EqualTo;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class ResetPasswordRoute extends AbstractResetPasswordRoute
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
        private readonly RequestStack $requestStack,
        private readonly RateLimiter $rateLimiter,
        private readonly DataValidationFactoryInterface $passwordValidationFactory,
        private readonly ClockInterface $clock,
        private readonly ExtensionDispatcher $extensions,
    ) {
    }

    public function getDecorated(): AbstractResetPasswordRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/store-api/account/recovery-password-confirm', name: 'store-api.account.recovery.password', methods: ['POST'])]
    public function resetPassword(RequestDataBag $data, ChannelContext $context): SuccessResponse
    {
        return $this->extensions->publish(
            name: ResetPasswordRouteExtension::NAME,
            extension: new ResetPasswordRouteExtension($data, $context),
            function: $this->_resetPassword(...),
        );
    }

    private function _resetPassword(RequestDataBag $data, ChannelContext $context): SuccessResponse
    {
        $this->validateResetPassword($data, $context);

        $hash = $data->get('hash');

        if (!$this->checkHash($hash, $context->getContext())) {
            throw UserException::userRecoveryHashExpired($hash);
        }

        $userHashCriteria = new Criteria();
        $userHashCriteria->addFilter(new EqualsFilter('hash', $hash));
        $userHashCriteria->addAssociation('user');

        $userRecovery = $this->userRecoveryRepository->search($userHashCriteria, $context->getContext())->getEntities()->first();
        if (!$userRecovery) {
            throw UserException::userNotFoundByHash($hash);
        }

        $user = $userRecovery->getUser();

        if (!$user) {
            throw UserException::userNotFoundByHash($hash);
        }

        // reset login and pw-reset limit when password was changed
        if (($request = $this->requestStack->getMainRequest()) !== null) {
            $cacheKey = strtolower((string) $user->getEmail()) . '-' . $request->getClientIp();

            $this->rateLimiter->reset(RateLimiter::LOGIN_ROUTE, $cacheKey);
            $this->rateLimiter->resetIfConfigured(RateLimiter::LOGIN_USER, strtolower((string) $user->getEmail()));
            $this->rateLimiter->resetIfConfigured(RateLimiter::LOGIN_CLIENT, (string) $request->getClientIp());
            $this->rateLimiter->reset(RateLimiter::RESET_PASSWORD, $cacheKey);
        }

        $userData = [
            'id' => $user->getId(),
            'password' => $data->get('newPassword'),
            'legacyPassword' => null,
            'legacyEncoder' => null,
        ];

        if ($user->getDoubleOptInRegistration() && $user->getDoubleOptInConfirmDate() === null) {
            $userData['doubleOptInConfirmDate'] = $this->clock->now();
        }

        $this->userRepository->update([$userData], $context->getContext());
        $this->deleteRecoveryForUser($userRecovery, $context->getContext());

        return new SuccessResponse();
    }

    /**
     * @throws ConstraintViolationException
     */
    private function validateResetPassword(DataBag $data, ChannelContext $context): void
    {
        $definition = new DataValidationDefinition('user.password.update');

        $passwordDefinition = $this->passwordValidationFactory->update($context);
        $definition->add('newPassword', new EqualTo(propertyPath: 'newPasswordConfirm'), ...$passwordDefinition->getProperty('password'));

        $this->dispatchValidationEvent($definition, $data, $context->getContext());

        $this->validator->validate($data->all(), $definition);

        $this->tryValidateEqualtoConstraint($data->all(), 'newPassword', $definition);
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

    private function deleteRecoveryForUser(UserRecoveryEntity $existingRecovery, Context $context): void
    {
        $recoveryData = [
            'id' => $existingRecovery->getId(),
        ];

        $this->userRecoveryRepository->delete([$recoveryData], $context);
    }

    private function checkHash(string $hash, Context $context): bool
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('hash', $hash));

        $recovery = $this->userRecoveryRepository->search($criteria, $context)->getEntities()->first();

        $validDateTime = $this->clock->now()->sub(new \DateInterval('PT2H'));

        return $recovery && $validDateTime < $recovery->getCreatedAt();
    }
}
