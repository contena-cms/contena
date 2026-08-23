<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Channel;

use Psr\Clock\ClockInterface;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\RateLimiter\RateLimiter;
use Contena\Core\Framework\Routing\ChannelApiRouteScope;
use Contena\Core\Framework\Validation\BuildValidationEvent;
use Contena\Core\Framework\Validation\DataBag\DataBag;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\Framework\Validation\DataValidationDefinition;
use Contena\Core\Framework\Validation\DataValidationFactoryInterface;
use Contena\Core\Framework\Validation\DataValidator;
use Contena\Core\Framework\Validation\Exception\ConstraintViolationException;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\SuccessResponse;
use Contena\Core\System\Member\Aggregate\MemberRecovery\MemberRecoveryCollection;
use Contena\Core\System\Member\Aggregate\MemberRecovery\MemberRecoveryEntity;
use Contena\Core\System\Member\MemberCollection;
use Contena\Core\System\Member\MemberException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints\EqualTo;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ChannelApiRouteScope::ID]])]
class ResetPasswordRoute extends AbstractResetPasswordRoute
{
    /**
     * @internal
     *
     * @param EntityRepository<MemberCollection> $memberRepository
     * @param EntityRepository<MemberRecoveryCollection> $memberRecoveryRepository
     */
    public function __construct(
        private readonly EntityRepository $memberRepository,
        private readonly EntityRepository $memberRecoveryRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly DataValidator $validator,
        private readonly RequestStack $requestStack,
        private readonly RateLimiter $rateLimiter,
        private readonly DataValidationFactoryInterface $passwordValidationFactory,
        private readonly ClockInterface $clock,
    ) {
    }

    public function getDecorated(): AbstractResetPasswordRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/channel-api/account/recovery-password-confirm', name: 'channel-api.account.recovery.password', methods: ['POST'])]
    public function resetPassword(RequestDataBag $data, ChannelContext $context): SuccessResponse
    {
        $this->validateResetPassword($data, $context);

        $hash = $data->get('hash');

        if (!$this->checkHash($hash, $context->getContext())) {
            throw MemberException::memberRecoveryHashExpired($hash);
        }

        $memberHashCriteria = new Criteria();
        $memberHashCriteria->addFilter(new EqualsFilter('hash', $hash));
        $memberHashCriteria->addAssociation('member');

        $memberRecovery = $this->memberRecoveryRepository->search($memberHashCriteria, $context->getContext())->getEntities()->first();
        if (!$memberRecovery) {
            throw MemberException::memberNotFoundByHash($hash);
        }

        $member = $memberRecovery->getMember();

        if (!$member) {
            throw MemberException::memberNotFoundByHash($hash);
        }

        // reset login and pw-reset limit when password was changed
        if (($request = $this->requestStack->getMainRequest()) !== null) {
            $cacheKey = strtolower((string) $member->getEmail()) . '-' . $request->getClientIp();

            $this->rateLimiter->reset(RateLimiter::LOGIN_ROUTE, $cacheKey, $context->getContext());
            $this->rateLimiter->resetIfConfigured(RateLimiter::LOGIN_USER, strtolower((string) $member->getEmail()), $context->getContext());
            $this->rateLimiter->resetIfConfigured(RateLimiter::LOGIN_CLIENT, (string) $request->getClientIp(), $context->getContext());
            $this->rateLimiter->reset(RateLimiter::RESET_PASSWORD, $cacheKey, $context->getContext());
        }

        $memberData = [
            'id' => $member->getId(),
            'password' => $data->get('newPassword'),
        ];

        if ($member->getDoubleOptInRegistration() && $member->getDoubleOptInConfirmDate() === null) {
            $memberData['doubleOptInConfirmDate'] = $this->clock->now();
        }

        $this->memberRepository->update([$memberData], $context->getContext());
        $this->deleteRecoveryForMember($memberRecovery, $context->getContext());

        return new SuccessResponse();
    }

    /**
     * @throws ConstraintViolationException
     */
    private function validateResetPassword(DataBag $data, ChannelContext $context): void
    {
        $definition = new DataValidationDefinition('member.password.update');

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

    private function deleteRecoveryForMember(MemberRecoveryEntity $existingRecovery, Context $context): void
    {
        $recoveryData = [
            'id' => $existingRecovery->getId(),
        ];

        $this->memberRecoveryRepository->delete([$recoveryData], $context);
    }

    private function checkHash(string $hash, Context $context): bool
    {
        $criteria = new Criteria()
            ->addFilter(new EqualsFilter('hash', $hash));

        $recovery = $this->memberRecoveryRepository->search($criteria, $context)->getEntities()->first();

        $validDateTime = $this->clock->now()->sub(new \DateInterval('PT2H'));

        return $recovery && $validDateTime < $recovery->getCreatedAt();
    }
}
