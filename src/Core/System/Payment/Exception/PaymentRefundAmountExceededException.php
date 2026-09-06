<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Exception;

use Contena\Core\System\Payment\PaymentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @final
 */
class PaymentRefundAmountExceededException extends PaymentException
{
    public function __construct(int $amount)
    {
        parent::__construct(Response::HTTP_UNPROCESSABLE_ENTITY, self::REFUND_AMOUNT_EXCEEDED, 'The refundable balance is less than {{ amount }}.', ['amount' => $amount]);
    }
}
