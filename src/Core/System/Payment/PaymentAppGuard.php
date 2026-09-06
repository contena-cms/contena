<?php declare(strict_types=1);

namespace Contena\Core\System\Payment;

use Contena\Core\Framework\Context;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentApp\PaymentAppEntity;

/**
 * @internal
 */
final class PaymentAppGuard
{
    public function validate(PaymentAppEntity $app, Context $context): void
    {
        if (!$app->status || $app->tenantId !== $context->getTenantId()) {
            throw PaymentException::appNotFound($app->appCode);
        }
        if ($context->hasGlobalTenantAccess()) {
            throw PaymentException::invalidRequest('Payment operations require a platform or tenant context.');
        }
    }
}
