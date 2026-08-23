<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Channel;

use Psr\Clock\ClockInterface;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Validation\EntityExists;
use Contena\Core\Framework\Event\DataMappingEvent;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Routing\ChannelApiRouteScope;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\BuildValidationEvent;
use Contena\Core\Framework\Validation\DataBag\DataBag;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\Framework\Validation\DataValidationDefinition;
use Contena\Core\Framework\Validation\DataValidationFactoryInterface;
use Contena\Core\Framework\Validation\DataValidator;
use Contena\Core\Framework\Validation\Exception\ConstraintViolationException;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainCollection;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainEntity;
use Contena\Core\System\Channel\ChannelApiCustomFieldMapper;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Context\ChannelContextPersister;
use Contena\Core\System\Channel\Context\ChannelContextServiceInterface;
use Contena\Core\System\Channel\Context\ChannelContextServiceParameters;
use Contena\Core\System\Member\Event\MemberLoginEvent;
use Contena\Core\System\Member\Event\MemberRegisterEvent;
use Contena\Core\System\Member\MemberCollection;
use Contena\Core\System\Member\MemberDefinition;
use Contena\Core\System\Member\MemberEvents;
use Contena\Core\System\Member\Service\DoubleOptInService;
use Contena\Core\System\Member\Service\EmailIdnConverter;
use Contena\Core\System\Member\Validation\Constraint\MemberEmailUnique;
use Contena\Core\System\NumberRange\ValueGenerator\AbstractNumberRangeValueGenerator;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ChannelApiRouteScope::ID]])]
class RegisterRoute extends AbstractRegisterRoute
{
    /**
     * @internal
     *
     * @param EntityRepository<MemberCollection> $memberRepository
     */
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly AbstractNumberRangeValueGenerator $numberRangeValueGenerator,
        private readonly DataValidator $validator,
        private readonly DataValidationFactoryInterface $accountValidationFactory,
        private readonly SystemConfigService $systemConfigService,
        private readonly EntityRepository $memberRepository,
        private readonly ChannelContextPersister $contextPersister,
        private readonly ChannelContextServiceInterface $contextService,
        private readonly ChannelApiCustomFieldMapper $customFieldMapper,
        private readonly DataValidationFactoryInterface $passwordValidationFactory,
        private readonly DoubleOptInService $doubleOptInService,
        private readonly ClockInterface $clock,
    ) {
    }

    public function getDecorated(): AbstractRegisterRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/channel-api/account/register', name: 'channel-api.account.register', methods: ['POST'])]
    public function register(
        RequestDataBag $data,
        ChannelContext $context,
        bool $validateFrontendUrl = true,
        ?DataValidationDefinition $additionalValidationDefinitions = null
    ): MemberResponse {
        EmailIdnConverter::encodeDataBag($data);

        $this->validateRegistrationData($data, $context, $additionalValidationDefinitions, $validateFrontendUrl);

        $member = $this->mapMemberData($data, $context);

        $member = $this->doubleOptInService->mapMemberDoubleOptInData($member, $context);

        if ($data->get('customFields') instanceof RequestDataBag) {
            $member['customFields'] = $this->customFieldMapper->map(MemberDefinition::ENTITY_NAME, $data->get('customFields'));
        }

        // Convert all DataBags to array
        $member = array_map(static function (mixed $value) {
            if ($value instanceof DataBag) {
                return $value->all();
            }

            return $value;
        }, $member);

        $writeContext = clone $context->getContext();
        $writeContext->addState(EntityIndexerRegistry::USE_INDEXING_QUEUE);

        $this->memberRepository->create([$member], $writeContext);

        $criteria = new Criteria([$member['id']]);

        $memberEntity = $this->memberRepository->search($criteria, $context->getContext())->getEntities()->first();
        \assert(assertion: $memberEntity !== null);

        if ($memberEntity->getDoubleOptInRegistration()) {
            $this->doubleOptInService->sendDoubleOptInMail(
                $memberEntity,
                $context,
                (string) $data->get('frontendUrl'),
                $data->get('redirectTo'),
                $data->get('redirectParameters')
            );

            // We don't want to leak the hash in channel-api
            $memberEntity->setHash('');

            return new MemberResponse($memberEntity);
        }

        $response = new MemberResponse($memberEntity);

        $newToken = $this->contextPersister->replace($context->getToken(), $context);

        $this->contextPersister->save(
            $newToken,
            [
                'memberId' => $memberEntity->getId(),
                'domainId' => $context->getDomainId(),
            ],
            $context->getChannelId(),
            $memberEntity->getId()
        );

        $new = $this->contextService->get(
            new ChannelContextServiceParameters(
                channelId: $context->getChannelId(),
                token: $newToken,
                languageId: $context->getLanguageId(),
                domainId: $context->getDomainId(),
                memberId: $memberEntity->getId(),
            )
        );

        $new->addState(...$context->getStates());

        $this->eventDispatcher->dispatch(new MemberRegisterEvent($new, $memberEntity));

        $event = new MemberLoginEvent($new, $memberEntity, $newToken);
        $this->eventDispatcher->dispatch($event);

        $response->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $newToken);

        // We don't want to leak the hash in channel-api
        $memberEntity->setHash('');

        return $response;
    }

    private function validateRegistrationData(
        DataBag $data,
        ChannelContext $context,
        ?DataValidationDefinition $additionalValidations,
        bool $validateFrontendUrl
    ): void {
        $definition = $this->getMemberCreateValidationDefinition($data, $context);

        if ($additionalValidations) {
            $definition->merge($additionalValidations);
        }

        if ($validateFrontendUrl) {
            $definition
                ->add('frontendUrl', new NotBlank(), new Choice(choices: $this->getDomainUrls($context)));
        }

        if ($this->systemConfigService->get('core.loginRegistration.requireDataProtectionCheckbox', $context->getChannelId())) {
            $definition->add('acceptedDataProtection', new NotBlank());
        }

        $violations = $this->validator->getViolations($data->all(), $definition);

        if (!$violations->count()) {
            return;
        }

        throw new ConstraintViolationException($violations, $data->all());
    }

    /**
     * @return list<string>
     */
    private function getDomainUrls(ChannelContext $context): array
    {
        $channelDomainCollection = $context->getChannel()->getDomains();
        \assert($channelDomainCollection instanceof ChannelDomainCollection);

        return array_values(array_map(static fn (ChannelDomainEntity $domainEntity) => rtrim($domainEntity->getUrl(), '/'), $channelDomainCollection->getElements()));
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
            '%d-%d-%d',
            (int) $birthdayYear,
            (int) $birthdayMonth,
            (int) $birthdayDay
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function mapMemberData(DataBag $data, ChannelContext $context): array
    {
        $member = [
            'memberNumber' => $this->numberRangeValueGenerator->getValue(
                $this->memberRepository->getDefinition()->getEntityName(),
                $context->getContext()
            ),
            'channelId' => $context->getChannelId(),
            'languageId' => $context->getLanguageId(),
            'groupId' => $context->getMemberGroupId(),
            'requestedGroupId' => $data->get('requestedGroupId', null),
            'name' => $data->get('name'),
            'phoneNumber' => $data->get('phoneNumber'),
            'email' => $data->get('email'),
            'title' => $data->get('title'),
            'active' => true,
            'birthday' => $this->getBirthday($data),
            'firstLogin' => $this->clock->now(),
            'password' => $data->get('password'),
        ];

        $event = new DataMappingEvent($data, $member, $context->getContext());
        $this->eventDispatcher->dispatch($event, MemberEvents::MAPPING_REGISTER_MEMBER);

        $member = $event->getOutput();
        $member['id'] = Uuid::randomHex();

        return $member;
    }

    private function getMemberCreateValidationDefinition(DataBag $data, ChannelContext $context): DataValidationDefinition
    {
        $validation = $this->accountValidationFactory->create($context);

        $criteria = new Criteria()
            ->addFilter(new EqualsFilter('registrationChannels.id', $context->getChannelId()));

        $validation->add('requestedGroupId', new EntityExists(
            entity: 'member_group',
            context: $context->getContext(),
            criteria: $criteria,
        ));

        $validation->merge($this->passwordValidationFactory->create($context));
        $validation->add('email', new MemberEmailUnique(channelContext: $context));

        $validationEvent = new BuildValidationEvent($validation, $data, $context->getContext());
        $this->eventDispatcher->dispatch($validationEvent, $validationEvent->getName());

        return $validation;
    }
}
