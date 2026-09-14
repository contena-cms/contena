<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Exception;

use Contena\Core\System\Payment\PaymentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @final
 */
class PaymentCurrencyNotSupportedException extends PaymentException
{
    public function __construct(string $currencyCode)
    {
        parent::__construct(Response::HTTP_UNPROCESSABLE_ENTITY, self::CURRENCY_NOT_SUPPORTED, 'Currency "{{ currencyCode }}" is not available for payment.', ['currencyCode' => $currencyCode]);
    }
}
