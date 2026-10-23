<?php declare(strict_types=1);

namespace Contena\Core\System\User\Channel;

use Psr\Clock\ClockInterface;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Routing\StoreApiRouteScope;
use Contena\Core\Framework\Util\Hasher;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\Framework\Validation\DataValidationDefinition;
use Contena\Core\Framework\Validation\DataValidator;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Context\ChannelContextPersister;
use Contena\Core\System\Channel\Context\ChannelContextServiceInterface;
use Contena\Core\System\Channel\Context\ChannelContextServiceParameters;
use Contena\Core\System\User\Event\GuestUserRegisterEvent;
use Contena\Core\System\User\Event\UserLoginEvent;
use Contena\Core\System\User\Event\UserRegisterEvent;
use Contena\Core\System\User\Extension\RegisterConfirmRouteExtension;
use Contena\Core\System\User\UserCollection;
use Contena\Core\System\User\UserException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints\EqualTo;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class RegisterConfirmRoute extends AbstractRegisterConfirmRoute
{
    /**
     * @internal
     *
     * @param EntityRepository<UserCollection> $userRepository
     */
    public function __construct(
        private readonly EntityRepository $userRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly DataValidator $validator,
        private readonly ChannelContextPersister $contextPersister,
        private readonly ChannelContextServiceInterface $contextService,
        private readonly ClockInterface $clock,
        private readonly ExtensionDispatcher $extensions,
    ) {
    }

    public function getDecorated(): AbstractRegisterConfirmRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/store-api/account/register-confirm', name: 'store-api.account.register.confirm', methods: ['POST'])]
    public function confirm(RequestDataBag $dataBag, ChannelContext $context): UserResponse
    {
        return $this->extensions->publish(
            name: RegisterConfirmRouteExtension::NAME,
            extension: new RegisterConfirmRouteExtension($dataBag, $context),
            function: $this->_confirm(...),
        );
    }

    private function _confirm(RequestDataBag $dataBag, ChannelContext $context): UserResponse
    {
        if (!$dataBag->has('hash')) {
            throw UserException::noHashProvided();
        }

        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('hash', $dataBag->get('hash')))
            ->addAssociations(['addresses'])
            ->setLimit(1);

        $user = $this->userRepository->search($criteria, $context->getContext())->getEntities()->first();
        if (!$user) {
            throw UserException::userNotFoundByHash($dataBag->get('hash'));
        }

        $this->validator->validate(
            [
                'em' => $dataBag->get('em'),
                'doubleOptInRegistration' => $user->getDoubleOptInRegistration(),
            ],
            $this->getBeforeConfirmValidation(Hasher::hash($user->getEmail(), 'sha1'))
        );

        if ($user->getDoubleOptInConfirmDate() !== null) {
            throw UserException::userAlreadyConfirmed($user->getId());
        }

        $userUpdate = [
            'id' => $user->getId(),
            'doubleOptInConfirmDate' => $this->clock->now(),
        ];
        $this->userRepository->update([$userUpdate], $context->getContext());

        $newToken = $this->contextPersister->replace($context->getToken(), $context);

        $this->contextPersister->save(
            $newToken,
            [
                'userId' => $user->getId(),
                'billingAddressId' => null,
                'shippingAddressId' => null,
            ],
            $context->getChannelId(),
            $user->getId()
        );

        $new = $this->contextService->get(
            new ChannelContextServiceParameters(
                $context->getChannelId(),
                $newToken,
                $context->getLanguageId(),
                $context->getCurrencyId(),
                $context->getDomainId(),
                $context->getContext(),
                $user->getId()
            )
        );

        $new->addState(...$context->getStates());

        if ($user->getGuest()) {
            $this->eventDispatcher->dispatch(new GuestUserRegisterEvent($new, $user));
        } else {
            $this->eventDispatcher->dispatch(new UserRegisterEvent($new, $user));
        }

        $criteria = (new Criteria([$user->getId()]))
            ->addAssociations(['addresses'])
            ->setLimit(1);

        $user = $this->userRepository->search($criteria, $new->getContext())->getEntities()->first();
        \assert($user !== null);

        $response = new UserResponse($user);

        $event = new UserLoginEvent($new, $user, $newToken);
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
