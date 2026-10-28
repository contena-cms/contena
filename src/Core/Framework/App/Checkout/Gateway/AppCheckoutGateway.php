<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Checkout\Gateway;

use Contena\Core\Checkout\Gateway\CheckoutGatewayException;
use Contena\Core\Checkout\Gateway\CheckoutGatewayInterface;
use Contena\Core\Checkout\Gateway\CheckoutGatewayResponse;
use Contena\Core\Checkout\Gateway\Command\AbstractCheckoutGatewayCommand;
use Contena\Core\Checkout\Gateway\Command\CheckoutGatewayCommandCollection;
use Contena\Core\Checkout\Gateway\Command\Event\CheckoutGatewayCommandsCollectedEvent;
use Contena\Core\Checkout\Gateway\Command\Executor\CheckoutGatewayCommandExecutor;
use Contena\Core\Checkout\Gateway\Command\Registry\CheckoutGatewayCommandRegistry;
use Contena\Core\Checkout\Gateway\Command\Struct\CheckoutGatewayPayloadStruct;
use Contena\Core\Checkout\Payment\PaymentMethodEntity;
use Contena\Core\Checkout\Shipping\ShippingMethodEntity;
use Contena\Core\Framework\App\ActiveAppsLoader;
use Contena\Core\Framework\App\AppCollection;
use Contena\Core\Framework\App\Checkout\Payload\AppCheckoutGatewayPayload;
use Contena\Core\Framework\App\Checkout\Payload\AppCheckoutGatewayPayloadService;
use Contena\Core\Framework\App\Manifest\Xml\Gateway\CheckoutGateway;
use Contena\Core\Framework\App\Privileges\AppCapability;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\NotEqualsFilter;
use Contena\Core\Framework\Log\ExceptionLogger;
use Contena\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal only for use by the app-system
 */
#[Package('checkout')]
class AppCheckoutGateway implements CheckoutGatewayInterface
{
    /**
     * @param EntityRepository<AppCollection> $appRepository
     *
     * @internal
     */
    public function __construct(
        private readonly AppCheckoutGatewayPayloadService $payloadService,
        private readonly CheckoutGatewayCommandExecutor $executor,
        private readonly CheckoutGatewayCommandRegistry $registry,
        private readonly EntityRepository $appRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ExceptionLogger $logger,
        private readonly ActiveAppsLoader $activeAppsLoader,
        private readonly AppCapability $appCapability
    ) {
    }

    public function process(CheckoutGatewayPayloadStruct $payload): CheckoutGatewayResponse
    {
        $collected = new CheckoutGatewayCommandCollection();

        $context = $payload->getSalesChannelContext();
        $paymentMethods = $payload->getPaymentMethods()->map(static fn (PaymentMethodEntity $paymentMethod) => $paymentMethod->getTechnicalName());
        $shippingMethods = $payload->getShippingMethods()->map(static fn (ShippingMethodEntity $shippingMethod) => $shippingMethod->getTechnicalName());

        $appPayload = new AppCheckoutGatewayPayload($context, $payload->getCart(), $paymentMethods, $shippingMethods);
        $apps = $this->getActiveAppsWithCheckoutGateway($context->getContext());

        foreach ($apps as $app) {
            // do not push cart/customer data to an app that has not been granted the checkout gateway permission
            $this->appCapability->whenGranted($app->getId(), CheckoutGateway::PERMISSION, function () use ($app, $appPayload, $collected): void {
                $checkoutGatewayUrl = $app->getCheckoutGatewayUrl();
                \assert(\is_string($checkoutGatewayUrl));
                $appResponse = $this->payloadService->request($checkoutGatewayUrl, $appPayload, $app);

                if (!$appResponse) {
                    $this->logger->logOrThrowException(CheckoutGatewayException::emptyAppResponse($app->getName()));

                    return;
                }

                $this->collectCommandsFromAppResponse($appResponse, $collected);
            });
        }

        $response = new CheckoutGatewayResponse(
            $payload->getPaymentMethods(),
            $payload->getShippingMethods(),
            $payload->getCart()->getErrors()
        );

        $this->eventDispatcher->dispatch(new CheckoutGatewayCommandsCollectedEvent($payload, $collected));

        return $this->executor->execute($collected, $response, $context);
    }

    private function getActiveAppsWithCheckoutGateway(Context $context): AppCollection
    {
        // If no active apps are available, we can return early
        if ($this->activeAppsLoader->getActiveApps() === []) {
            return new AppCollection();
        }

        $criteria = new Criteria();
        $criteria->addAssociation('paymentMethods');

        $criteria->addFilter(
            new EqualsFilter('active', true),
            new NotEqualsFilter('checkoutGatewayUrl', null),
        );

        return $this->appRepository->search($criteria, $context)->getEntities();
    }

    private function collectCommandsFromAppResponse(AppCheckoutGatewayResponse $commands, CheckoutGatewayCommandCollection $collected): void
    {
        foreach ($commands->getCommands() as $payload) {
            if (!isset($payload['command'])) {
                $this->logger->logOrThrowException(CheckoutGatewayException::payloadInvalid());

                continue;
            }

            $commandKey = $payload['command'];
            if (!isset($payload['payload'])) {
                $this->logger->logOrThrowException(CheckoutGatewayException::payloadInvalid($commandKey));

                continue;
            }

            if (!$this->registry->hasAppCommand($commandKey)) {
                $this->logger->logOrThrowException(CheckoutGatewayException::handlerNotFound($commandKey));

                continue;
            }

            $command = $this->registry->getAppCommand($commandKey);

            if (!\is_a($command, AbstractCheckoutGatewayCommand::class, true)) {
                $this->logger->logOrThrowException(CheckoutGatewayException::handlerNotFound($commandKey));

                continue;
            }

            $commandPayload = $payload['payload'];

            try {
                $executableCommand = $command::createFromPayload($commandPayload);
            } catch (\Error) {
                $this->logger->logOrThrowException(CheckoutGatewayException::payloadInvalid($payload['command']));
                continue;
            }

            $collected->add($executableCommand);
        }
    }
}
