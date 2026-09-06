<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Exception;

use Contena\Core\System\Payment\PaymentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @final
 */
class PaymentOrderNotSucceededException extends PaymentException
{
    public function __construct(string $reference)
    {
        parent::__construct(Response::HTTP_UNPROCESSABLE_ENTITY, self::ORDER_NOT_SUCCEEDED, 'Payment order "{{ reference }}" has not succeeded.', ['reference' => $reference]);
    }
}
