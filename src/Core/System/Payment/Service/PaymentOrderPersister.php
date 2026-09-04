<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Service;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentApp\PaymentAppEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\Aggregate\PaymentOrderTransaction\PaymentOrderTransactionCollection;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\PaymentOrderCollection;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\PaymentOrderEntity;
use Contena\Core\System\Payment\Struct\PaymentRequest;
use Contena\Core\System\Payment\Struct\PaymentRoute;

/**
 * Persists converted payment order payloads.
 *
 * @internal
 */
final class PaymentOrderPersister
{
    /**
     * @param EntityRepository<PaymentOrderCollection> $paymentOrderRepository
     * @param EntityRepository<PaymentOrderTransactionCollection> $paymentOrderTransactionRepository
     */
    public function __construct(
        private readonly EntityRepository $paymentOrderRepository,
        private readonly EntityRepository $paymentOrderTransactionRepository,
        private readonly PaymentOrderConverter $converter,
    ) {
    }

    public function persist(PaymentAppEntity $app, PaymentRequest $request, PaymentRoute $route, Context $context): PaymentOrderCreation
    {
        $payload = $this->converter->convert($app, $request, $route, $context);

        $this->paymentOrderRepository->create([$payload['data']], $context);

        return new PaymentOrderCreation($payload['orderId'], $payload['transactionId']);
    }

    public function persistQueryTransaction(PaymentOrderEntity $order, Context $context): string
    {
        $payload = $this->converter->convertQueryTransaction($order, $context);

        $this->paymentOrderTransactionRepository->create([$payload['data']], $context);

        return $payload['id'];
    }
}
