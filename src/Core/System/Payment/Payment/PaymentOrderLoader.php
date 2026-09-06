<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Payment;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\PaymentOrderCollection;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\PaymentOrderEntity;
use Contena\Core\System\Payment\Payment\Struct\OrderReference;
use Contena\Core\System\Payment\PaymentException;
use Contena\Tests\Integration\Core\System\Payment\PaymentServiceTest;

/**
 * Read access shared with refunds, without exposing payment execution internals.
 *
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see PaymentServiceTest
 */
class PaymentOrderLoader
{
    /**
     * @param EntityRepository<PaymentOrderCollection> $repository
     */
    public function __construct(private readonly EntityRepository $repository)
    {
    }

    public function load(string $paymentAppId, OrderReference $reference, Context $context): PaymentOrderEntity
    {
        if ($context->hasGlobalTenantAccess()) {
            throw PaymentException::invalidRequest('Payment operations require a platform or tenant context.');
        }
        if (($reference->orderNo ?? '') === '' && ($reference->externalOrderNo ?? '') === '') {
            throw PaymentException::orderNotFound('');
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('paymentAppId', $paymentAppId));
        if ($reference->orderNo !== null) {
            $criteria->addFilter(new EqualsFilter('orderNo', $reference->orderNo));
        }
        if ($reference->externalOrderNo !== null) {
            $criteria->addFilter(new EqualsFilter('externalOrderNo', $reference->externalOrderNo));
        }
        $criteria->addAssociation('state');
        $criteria->setLimit(1);

        return $this->repository->search($criteria, $context)->getEntities()->first()
            ?? throw PaymentException::orderNotFound($reference->orderNo ?? $reference->externalOrderNo ?? '');
    }
}
