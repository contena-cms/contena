<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Channel;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\RateLimiter\RateLimiter;
use Contena\Core\Framework\Routing\ChannelApiRouteScope;
use Contena\Core\Framework\Util\Random;
use Contena\Core\Framework\Validation\BuildValidationEvent;
use Contena\Core\Framework\Validation\DataBag\DataBag;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\Framework\Validation\DataValidationDefinition;
use Contena\Core\Framework\Validation\DataValidator;
use Contena\Core\Framework\Validation\Exception\ConstraintViolationException;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainEntity;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\SuccessResponse;
use Contena\Core\System\Member\Aggregate\MemberRecovery\MemberRecoveryCollection;
use Contena\Core\System\Member\Aggregate\MemberRecovery\MemberRecoveryEntity;
use Contena\Core\System\Member\Event\MemberAccountRecoverRequestEvent;
use Contena\Core\System\Member\Event\PasswordRecoveryUrlEvent;
use Contena\Core\System\Member\MemberCollection;
use Contena\Core\System\Member\MemberEntity;
use Contena\Core\System\Member\MemberException;
use Contena\Core\System\Member\Service\EmailIdnConverter;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\EqualTo;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ChannelApiRouteScope::ID]])]
class SendPasswordRecoveryMailRoute extends AbstractSendPasswordRecoveryMailRoute
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
        private readonly SystemConfigService $systemConfigService,
        private readonly RequestStack $requestStack,
        private readonly RateLimiter $rateLimiter
    ) {
    }

    public function getDecorated(): AbstractSendPasswordRecoveryMailRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/channel-api/account/recovery-password', name: 'channel-api.account.recovery.send.mail', methods: ['POST'])]
    public function sendRecoveryMail(RequestDataBag $data, ChannelContext $context, bool $validateFrontendUrl = true): SuccessResponse
    {
        EmailIdnConverter::encodeDataBag($data);

        $this->validateRecoverEmail($data, $context, $validateFrontendUrl);

        if (($request = $this->requestStack->getMainRequest()) !== null) {
            $key = strtolower(\sprintf('%s-%s', $data->get('email'), $request->getClientIp()));
            $this->rateLimiter->ensureAccepted(RateLimiter::RESET_PASSWORD, $key, $context->getContext());
        }

        try {
            $member = $this->getMemberByEmail($data->get('email'), $context);
        } catch (MemberException) {
            return new SuccessResponse();
        }

        $memberId = $member->getId();

        $memberIdCriteria = new Criteria();
        $memberIdCriteria->addFilter(new EqualsFilter('memberId', $memberId));
        $memberIdCriteria->addAssociation('member');

        $repoContext = $context->getContext();

        $existingRecovery = $this->memberRecoveryRepository->search($memberIdCriteria, $repoContext)->getEntities()->first();
        if ($existingRecovery) {
            $this->deleteRecoveryForMember($existingRecovery, $repoContext);
        }

        $recoveryData = [
            'memberId' => $memberId,
            'hash' => Random::getAlphanumericString(32),
        ];

        $this->memberRecoveryRepository->create([$recoveryData], $repoContext);

        $memberRecovery = $this->memberRecoveryRepository->search($memberIdCriteria, $repoContext)->getEntities()->first();
        if (!$memberRecovery) {
            throw MemberException::memberNotFoundById($memberId);
        }

        $hash = $memberRecovery->getHash();

        $recoverUrl = $this->getRecoverUrl($context, $hash, $data->get('frontendUrl'), $memberRecovery);

        $event = new MemberAccountRecoverRequestEvent($context, $memberRecovery, $recoverUrl);

        $this->eventDispatcher->dispatch($event, MemberAccountRecoverRequestEvent::EVENT_NAME);

        return new SuccessResponse();
    }

    private function validateRecoverEmail(DataBag $data, ChannelContext $context, bool $validateFrontendUrl = true): void
    {
        $validation = new DataValidationDefinition('member.email.recover');

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

    private function getMemberByEmail(string $email, ChannelContext $context): MemberEntity
    {
        $criteria = new Criteria()
            ->addFilter(new EqualsFilter('member.active', 1))
            ->addFilter(new EqualsFilter('member.email', $email))
            ->addFilter(new EqualsFilter('member.channelId', $context->getChannelId()));

        $member = $this->memberRepository->search($criteria, $context->getContext())->getEntities()->first();
        if (!$member) {
            throw MemberException::memberNotFound($email);
        }

        return $member;
    }

    private function deleteRecoveryForMember(MemberRecoveryEntity $existingRecovery, Context $context): void
    {
        $recoveryData = [
            'id' => $existingRecovery->getId(),
        ];

        $this->memberRecoveryRepository->delete([$recoveryData], $context);
    }

    private function getRecoverUrl(
        ChannelContext $context,
        string $hash,
        string $frontendUrl,
        MemberRecoveryEntity $memberRecovery
    ): string {
        $urlTemplate = $this->systemConfigService->get(
            'core.loginRegistration.pwdRecoverUrl',
            $context->getChannelId()
        );
        if (!\is_string($urlTemplate)) {
            $urlTemplate = '/account/recover/password?hash=%%RECOVERHASH%%';
        }

        $urlEvent = new PasswordRecoveryUrlEvent($context, $urlTemplate, $hash, $frontendUrl, $memberRecovery);
        $this->eventDispatcher->dispatch($urlEvent);

        return rtrim($frontendUrl, '/') . str_replace(
            '%%RECOVERHASH%%',
            $hash,
            $urlEvent->getRecoveryUrl()
        );
    }
}
