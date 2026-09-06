<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Exception;

use Contena\Core\System\Payment\PaymentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @final
 */
class PaymentDuplicateReferenceException extends PaymentException
{
    public function __construct(string $reference)
    {
        parent::__construct(Response::HTTP_CONFLICT, self::DUPLICATE_REFERENCE, 'Payment reference "{{ reference }}" already exists.', ['reference' => $reference]);
    }
}
