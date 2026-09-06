<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Exception;

use Contena\Core\System\Payment\PaymentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @final
 */
class PaymentCapabilityNotSupportedException extends PaymentException
{
    public function __construct(string $channel, string $operation)
    {
        parent::__construct(Response::HTTP_UNPROCESSABLE_ENTITY, self::CAPABILITY_NOT_SUPPORTED, 'Payment channel "{{ channel }}" does not support "{{ operation }}".', ['channel' => $channel, 'operation' => $operation]);
    }
}
