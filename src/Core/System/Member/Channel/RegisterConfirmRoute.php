<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Channel;

use Psr\Clock\ClockInterface;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Routing\ChannelApiRouteScope;
use Contena\Core\Framework\Util\Hasher;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\Framework\Validation\DataValidationDefinition;
use Contena\Core\Framework\Validation\DataValidator;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Context\ChannelContextPersister;
use Contena\Core\System\Channel\Context\ChannelContextServiceInterface;
use Contena\Core\System\Channel\Context\ChannelContextServiceParameters;
use Contena\Core\System\Member\Event\MemberLoginEvent;
use Contena\Core\System\Member\Event\MemberRegisterEvent;
use Contena\Core\System\Member\MemberCollection;
use Contena\Core\System\Member\MemberException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints\EqualTo;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ChannelApiRouteScope::ID]])]
class RegisterConfirmRoute extends AbstractRegisterConfirmRoute
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
        private readonly ChannelContextPersister $contextPersister,
        private readonly ChannelContextServiceInterface $contextService,
        private readonly ClockInterface $clock,
    ) {
    }

    public function getDecorated(): AbstractRegisterConfirmRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/channel-api/account/register-confirm', name: 'channel-api.account.register.confirm', methods: ['POST'])]
    public function confirm(RequestDataBag $dataBag, ChannelContext $context): MemberResponse
    {
        if (!$dataBag->has('hash')) {
            throw MemberException::noHashProvided();
        }

        $criteria = new Criteria()
            ->addFilter(new EqualsFilter('hash', $dataBag->get('hash')))
            ->addAssociations(['addresses.country', 'addresses.region'])
            ->setLimit(1);

        $member = $this->memberRepository->search($criteria, $context->getContext())->getEntities()->first();
        if (!$member) {
            throw MemberException::memberNotFoundByHash($dataBag->get('hash'));
        }

        $this->validator->validate(
            [
                'em' => $dataBag->get('em'),
                'doubleOptInRegistration' => $member->getDoubleOptInRegistration(),
            ],
            $this->getBeforeConfirmValidation(Hasher::hash($member->getEmail(), 'sha1'))
        );

        if ($member->getDoubleOptInConfirmDate() !== null) {
            throw MemberException::memberAlreadyConfirmed($member->getId());
        }

        $memberUpdate = [
            'id' => $member->getId(),
            'doubleOptInConfirmDate' => $this->clock->now(),
        ];
        $this->memberRepository->update([$memberUpdate], $context->getContext());

        $newToken = $this->contextPersister->replace($context->getToken(), $context);

        $this->contextPersister->save(
            $newToken,
            [
                'memberId' => $member->getId(),
            ],
            $context->getChannelId(),
            $member->getId()
        );

        $new = $this->contextService->get(
            new ChannelContextServiceParameters(
                channelId: $context->getChannelId(),
                token: $newToken,
                languageId: $context->getLanguageId(),
                domainId: $context->getDomainId(),
                originalContext: $context->getContext(),
                memberId: $member->getId(),
            )
        );

        $new->addState(...$context->getStates());

        $this->eventDispatcher->dispatch(new MemberRegisterEvent($new, $member));

        $criteria = new Criteria([$member->getId()])
            ->addAssociations(['addresses.country', 'addresses.region'])
            ->setLimit(1);

        $member = $this->memberRepository->search($criteria, $new->getContext())->getEntities()->first();
        \assert($member !== null);

        $response = new MemberResponse($member);

        $event = new MemberLoginEvent($new, $member, $newToken);
        $this->eventDispatcher->dispatch($event);

        $response->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $newToken);

        return $response;
    }

    private function getBeforeConfirmValidation(string $emHash): DataValidationDefinition
    {
        $definition = new DataValidationDefinition('registration.opt_in_before');
        $definition->add('em', new EqualTo(value: $emHash));
        $definition->add('doubleOptInRegistration', new IsTrue());

        return $definition;
    }
}
