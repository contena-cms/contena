<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\OpenApi;

use Contena\Core\System\Payment\DataAbstractionLayer\PaymentApp\PaymentAppEntity;
use Contena\Core\System\Payment\OpenApi\Subscriber\PaymentAppValidator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

/**
 * @internal
 */
final class PaymentAppValueResolver implements ValueResolverInterface
{
    /**
     * @return \Generator<PaymentAppEntity>
     */
    public function resolve(Request $request, ArgumentMetadata $argument): \Generator
    {
        if ($argument->getType() !== PaymentAppEntity::class) {
            return;
        }

        $app = $request->attributes->get(PaymentAppValidator::ATTRIBUTE_PAYMENT_APP);
        if (!$app instanceof PaymentAppEntity) {
            return;
        }

        yield $app;
    }
}
