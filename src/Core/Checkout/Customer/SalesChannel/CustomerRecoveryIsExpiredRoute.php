<?php declare(strict_types=1);

namespace Contena\Core\Checkout\Customer\SalesChannel;

use Psr\Clock\ClockInterface;
use Contena\Core\Checkout\Customer\Aggregate\CustomerRecovery\CustomerRecoveryCollection;
use Contena\Core\Checkout\Customer\Aggregate\CustomerRecovery\CustomerRecoveryEntity;
use Contena\Core\Checkout\Customer\CustomerException;
use Contena\Core\Checkout\Customer\Extension\CustomerRecoveryIsExpiredRouteExtension;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Routing\StoreApiRouteScope;
use Contena\Core\Framework\Validation\BuildValidationEvent;
use Contena\Core\Framework\Validation\DataBag\DataBag;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\Framework\Validation\DataValidationDefinition;
use Contena\Core\Framework\Validation\DataValidator;
use Contena\Core\Framework\Validation\Exception\ConstraintViolationException;
use Contena\Core\PlatformRequest;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('checkout')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class CustomerRecoveryIsExpiredRoute extends AbstractCustomerRecoveryIsExpiredRoute
{
    /**
     * @internal
     *
     * @param EntityRepository<CustomerRecoveryCollection> $customerRecoveryRepository
     */
    public function __construct(
        private readonly EntityRepository $customerRecoveryRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly DataValidator $validator,
        private readonly ClockInterface $clock,
        private readonly ExtensionDispatcher $extensions,
    ) {
    }

    public function getDecorated(): AbstractCustomerRecoveryIsExpiredRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/store-api/account/customer-recovery-is-expired', name: 'store-api.account.customer.recovery.is.expired', methods: ['POST'])]
    public function load(RequestDataBag $data, SalesChannelContext $context): CustomerRecoveryIsExpiredResponse
    {
        return $this->extensions->publish(
            name: CustomerRecoveryIsExpiredRouteExtension::NAME,
            extension: new CustomerRecoveryIsExpiredRouteExtension($data, $context),
            function: $this->_load(...),
        );
    }

    private function _load(RequestDataBag $data, SalesChannelContext $context): CustomerRecoveryIsExpiredResponse
    {
        $this->validateHash($data, $context);

        $hash = $data->get('hash');

        $customerHashCriteria = new Criteria();
        $customerHashCriteria->addFilter(new EqualsFilter('hash', $hash));

        $customerRecovery = $this->customerRecoveryRepository->search($customerHashCriteria, $context->getContext())->getEntities()->first();
        if (!$customerRecovery) {
            throw CustomerException::customerNotFoundByHash($hash);
        }

        return new CustomerRecoveryIsExpiredResponse($this->isExpired($customerRecovery));
    }

    /**
     * @throws ConstraintViolationException
     */
    private function validateHash(DataBag $data, SalesChannelContext $context): void
    {
        $definition = new DataValidationDefinition('customer.recovery.get');

        $hashLength = 32;

        $definition->add('hash', new NotBlank(), new Type('string'), new Length($hashLength));

        $this->dispatchValidationEvent($definition, $data, $context->getContext());

        $this->validator->validate($data->all(), $definition);
    }

    private function dispatchValidationEvent(DataValidationDefinition $definition, DataBag $data, Context $context): void
    {
        $validationEvent = new BuildValidationEvent($definition, $data, $context);
        $this->eventDispatcher->dispatch($validationEvent, $validationEvent->getName());
    }

    private function isExpired(CustomerRecoveryEntity $customerRecovery): bool
    {
        $validDateTime = $this->clock->now()->sub(new \DateInterval('PT2H'));

        return $validDateTime > $customerRecovery->getCreatedAt();
    }
}
