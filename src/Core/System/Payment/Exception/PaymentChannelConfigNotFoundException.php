<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Exception;

use Contena\Core\System\Payment\PaymentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @final
 */
class PaymentChannelConfigNotFoundException extends PaymentException
{
    public function __construct(string $id)
    {
        parent::__construct(Response::HTTP_UNPROCESSABLE_ENTITY, self::CHANNEL_CONFIG_NOT_FOUND, 'Payment channel configuration "{{ id }}" was not found.', ['id' => $id]);
    }
}
