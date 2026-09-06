<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Exception;

use Contena\Core\System\Payment\PaymentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @final
 */
class PaymentOrderNotFoundException extends PaymentException
{
    public function __construct(string $reference)
    {
        parent::__construct(Response::HTTP_NOT_FOUND, self::ORDER_NOT_FOUND, 'Payment order "{{ reference }}" was not found.', ['reference' => $reference]);
    }
}
