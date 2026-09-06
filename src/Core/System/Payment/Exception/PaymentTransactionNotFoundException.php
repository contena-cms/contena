<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Exception;

use Contena\Core\System\Payment\PaymentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @final
 */
class PaymentTransactionNotFoundException extends PaymentException
{
    public function __construct(string $id)
    {
        parent::__construct(Response::HTTP_NOT_FOUND, self::TRANSACTION_NOT_FOUND, 'Payment transaction "{{ id }}" was not found.', ['id' => $id]);
    }
}
