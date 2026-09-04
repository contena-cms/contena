<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Exception;

use Contena\Core\System\Payment\PaymentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
class PaymentAppNotFoundException extends PaymentException
{
    public function __construct(
        string $code
    ) {
        parent::__construct(
            Response::HTTP_NOT_FOUND,
            self::APP_NOT_FOUND,
            'Payment app "{{ code }}" was not found or is disabled.',
            ['code' => $code]
        );
    }
}
