<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Exception;

use Contena\Core\System\Payment\PaymentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @final
 */
class PaymentGatewayNotFoundException extends PaymentException
{
    public function __construct(string $code)
    {
        parent::__construct(Response::HTTP_NOT_FOUND, self::GATEWAY_NOT_FOUND, 'Payment gateway "{{ code }}" was not found.', ['code' => $code]);
    }
}
