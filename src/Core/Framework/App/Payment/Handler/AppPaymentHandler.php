<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Payment\Handler;

use Doctrine\DBAL\Connection;
use Contena\Core\Checkout\Cart\Cart;
use Contena\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Contena\Core\Checkout\Payment\Cart\PaymentHandler\AbstractPaymentHandler;
use Contena\Core\Checkout\Payment\PaymentException;
use Contena\Core\Framework\App\Aggregate\AppPaymentMethod\AppPaymentMethodEntity;
use Contena\Core\Framework\App\AppCollection;
use Contena\Core\Framework\App\AppEntity;
use Contena\Core\Framework\App\AppException;
use Contena\Core\Framework\App\Payload\SourcedPayloadInterface;
use Contena\Core\Framework\App\Payment\Payload\PaymentPayloadService;
use Contena\Core\Framework\App\Payment\Response\AbstractResponse;
use Contena\Core\Framework\App\Payment\Response\PaymentResponse;
use Contena\Core\Framework\App\Payment\Response\RefundResponse;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Struct\Struct;
use Contena\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;
use Contena\Core\System\StateMachine\StateMachineRegistry;
use Contena\Core\System\StateMachine\Transition;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal only for use by the app-system
 */
#[Package('checkout')]
class AppPaymentHandler extends AbstractPaymentHandler
{
    /**
     * @param EntityRepository<OrderTransactionCaptureRefundCollection> $refundRepository
     * @param EntityRepository<OrderTransactionCollection> $orderTransactionRepository
     * @param EntityRepository<AppCollection> $appRepository
     */
    public function __construct(
        private readonly StateMachineRegistry $stateMachineRegistry,
        private readonly PaymentPayloadService $payloadService,
        private readonly EntityRepository $refundRepository,
        private readonly EntityRepository $orderTransactionRepository,
        private readonly EntityRepository $appRepository,
        private readonly Connection $connection,
    ) {
    }







    /**
     * @template T of AbstractResponse
     *
     * @param class-string<T> $responseClass
     *
     * @return T
     */
    private function requestAppServer(
        string $url,
        string $responseClass,
        SourcedPayloadInterface $payload,
        AppEntity $app,
        Context $context
    ): AbstractResponse {
        $response = $this->payloadService->request($url, $payload, $app, $responseClass, $context);

        if ($response->getErrorMessage()) {
            throw AppException::interrupted($response->getErrorMessage());
        }

        return $response;
    }

    private function transitionState(string $entityId, AbstractResponse $response, Context $context, string $entityName = OrderTransactionDefinition::ENTITY_NAME): void
    {
        if (!$response instanceof PaymentResponse && !$response instanceof RefundResponse) {
            return;
        }

        if (!$response->getStatus()) {
            return;
        }

        if ($response->getStatus() === StateMachineTransitionActions::ACTION_CANCEL) {
            throw PaymentException::customerCanceled($entityId, $response->getErrorMessage() ?? '');
        }

        $this->stateMachineRegistry->transition(
            new Transition(
                $entityName,
                $entityId,
                $response->getStatus(),
                'stateId'
            ),
            $context
        );
    }



    private function getApp(AppPaymentMethodEntity $appPaymentMethod): AppEntity
    {
        if ($appPaymentMethod->getApp()) {
            return $appPaymentMethod->getApp();
        }

        $appId = $appPaymentMethod->getAppId();
        if (!$appId) {
            throw AppException::interrupted('Loaded data invalid');
        }

        $app = $this->appRepository
            ->search(new Criteria([$appId]), Context::createDefaultContext())
            ->getEntities()
            ->first();

        if (!$app) {
            throw AppException::interrupted('Loaded data invalid');
        }

        return $app;
    }



}
