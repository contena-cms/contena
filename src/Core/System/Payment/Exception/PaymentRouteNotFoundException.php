<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Exception;

use Contena\Core\System\Payment\PaymentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @final
 */
class PaymentRouteNotFoundException extends PaymentException
{
    public function __construct(string $appId, ?string $method = null)
    {
        parent::__construct(Response::HTTP_UNPROCESSABLE_ENTITY, self::ROUTE_NOT_FOUND, 'No payment route is available for app "{{ appId }}" and method "{{ method }}".', ['appId' => $appId, 'method' => $method ?? '-']);
    }
}
