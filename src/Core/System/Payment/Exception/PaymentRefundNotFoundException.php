<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Exception;

use Contena\Core\System\Payment\PaymentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @final
 */
class PaymentRefundNotFoundException extends PaymentException
{
    public function __construct(string $id)
    {
        parent::__construct(Response::HTTP_NOT_FOUND, self::REFUND_NOT_FOUND, 'Payment refund "{{ id }}" was not found.', ['id' => $id]);
    }
}
