<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Exception;

use Contena\Core\System\Payment\PaymentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @final
 */
class PaymentNotificationResourceNotFoundException extends PaymentException
{
    public function __construct(string $reference)
    {
        parent::__construct(Response::HTTP_NOT_FOUND, self::NOTIFICATION_RESOURCE_NOT_FOUND, 'Payment notification resource "{{ reference }}" was not found.', ['reference' => $reference]);
    }
}
