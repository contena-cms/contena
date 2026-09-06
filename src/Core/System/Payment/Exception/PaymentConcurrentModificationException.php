<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Exception;

use Contena\Core\System\Payment\PaymentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @final
 */
class PaymentConcurrentModificationException extends PaymentException
{
    public function __construct(string $reference)
    {
        parent::__construct(Response::HTTP_CONFLICT, self::CONCURRENT_MODIFICATION, 'Payment resource "{{ reference }}" changed in another transaction. Reconcile its status in a new transaction; do not repeat the financial operation.', ['reference' => $reference]);
    }
}
