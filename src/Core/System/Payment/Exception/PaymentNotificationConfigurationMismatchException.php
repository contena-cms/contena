<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Exception;

use Contena\Core\System\Payment\PaymentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @final
 */
class PaymentNotificationConfigurationMismatchException extends PaymentException
{
    public function __construct(string $reference)
    {
        parent::__construct(Response::HTTP_UNPROCESSABLE_ENTITY, self::NOTIFICATION_CONFIGURATION_MISMATCH, 'The payment resource "{{ reference }}" does not belong to this channel configuration.', ['reference' => $reference]);
    }
}
