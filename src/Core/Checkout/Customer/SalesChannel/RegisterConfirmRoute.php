<?php declare(strict_types=1);

namespace Contena\Core\Checkout\Customer\SalesChannel;

use Psr\Clock\ClockInterface;
use Contena\Core\Checkout\Customer\CustomerCollection;
use Contena\Core\Checkout\Customer\CustomerException;
use Contena\Core\Checkout\Customer\Event\CustomerLoginEvent;
use Contena\Core\Checkout\Customer\Event\CustomerRegisterEvent;
use Contena\Core\Checkout\Customer\Event\GuestCustomerRegisterEvent;
use Contena\Core\Checkout\Customer\Extension\RegisterConfirmRouteExtension;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Routing\StoreApiRouteScope;
use Contena\Core\Framework\Util\Hasher;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\Framework\Validation\DataValidationDefinition;
use Contena\Core\Framework\Validation\DataValidator;
use Contena\Core\PlatformRequest;
use Contena\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Contena\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Contena\Core\System\SalesChannel\Context\SalesChannelContextServiceParameters;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints\EqualTo;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('checkout')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class RegisterConfirmRoute extends AbstractRegisterConfirmRoute
{
    /**
     * @internal
     *
     * @param EntityRepository<CustomerCollection> $customerRepository
     */
    public function __construct(
        private readonly EntityRepository $customerRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly DataValidator $validator,
        private readonly SalesChannelContextPersister $contextPersister,
        private readonly SalesChannelContextServiceInterface $contextService,
        private readonly ClockInterface $clock,
        private readonly ExtensionDispatcher $extensions,
    ) {
    }

    public function getDecorated(): AbstractRegisterConfirmRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/store-api/account/register-confirm', name: 'store-api.account.register.confirm', methods: ['POST'])]
    public function confirm(RequestDataBag $dataBag, SalesChannelContext $context): CustomerResponse
    {
        return $this->extensions->publish(
            name: RegisterConfirmRouteExtension::NAME,
            extension: new RegisterConfirmRouteExtension($dataBag, $context),
            function: $this->_confirm(...),
        );
    }

    private function _confirm(RequestDataBag $dataBag, SalesChannelContext $context): CustomerResponse
    {
        if (!$dataBag->has('hash')) {
            throw CustomerException::noHashProvided();
        }

        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('hash', $dataBag->get('hash')))
            ->addAssociations(['addresses', 'salutation'])
            ->setLimit(1);

        $customer = $this->customerRepository->search($criteria, $context->getContext())->getEntities()->first();
        if (!$customer) {
            throw CustomerException::customerNotFoundByHash($dataBag->get('hash'));
        }

        $this->validator->validate(
            [
                'em' => $dataBag->get('em'),
                'doubleOptInRegistration' => $customer->getDoubleOptInRegistration(),
            ],
            $this->getBeforeConfirmValidation(Hasher::hash($customer->getEmail(), 'sha1'))
        );

        if ($customer->getDoubleOptInConfirmDate() !== null) {
            throw CustomerException::customerAlreadyConfirmed($customer->getId());
        }

        $customerUpdate = [
            'id' => $customer->getId(),
            'doubleOptInConfirmDate' => $this->clock->now(),
        ];
        $this->customerRepository->update([$customerUpdate], $context->getContext());

        $newToken = $this->contextPersister->replace($context->getToken(), $context);

        $this->contextPersister->save(
            $newToken,
            [
                'customerId' => $customer->getId(),
                'billingAddressId' => null,
                'shippingAddressId' => null,
            ],
            $context->getSalesChannelId(),
            $customer->getId()
        );

        $new = $this->contextService->get(
            new SalesChannelContextServiceParameters(
                $context->getSalesChannelId(),
                $newToken,
                $context->getLanguageId(),
                $context->getCurrencyId(),
                $context->getDomainId(),
                $context->getContext(),
                $customer->getId()
            )
        );

        $new->addState(...$context->getStates());

        if ($customer->getGuest()) {
            $this->eventDispatcher->dispatch(new GuestCustomerRegisterEvent($new, $customer));
        } else {
            $this->eventDispatcher->dispatch(new CustomerRegisterEvent($new, $customer));
        }

        $criteria = (new Criteria([$customer->getId()]))
            ->addAssociations(['addresses', 'salutation'])
            ->setLimit(1);

        $customer = $this->customerRepository->search($criteria, $new->getContext())->getEntities()->first();
        \assert($customer !== null);

        $response = new CustomerResponse($customer);

        $event = new CustomerLoginEvent($new, $customer, $newToken);
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
