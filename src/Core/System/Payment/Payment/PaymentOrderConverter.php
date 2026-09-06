<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Payment;

use Contena\Core\Framework\Context;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentApp\PaymentAppEntity;
use Contena\Core\System\Payment\Event\PaymentOrderConvertedEvent;
use Contena\Core\System\Payment\Payment\Struct\PaymentRequest;
use Contena\Core\System\Payment\Routing\PaymentRoute;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Converts an accepted request and selected route into order data.
 * Identity allocation and execution records belong to persistence, not conversion.
 *
 * @internal
 */
final class PaymentOrderConverter
{
    public function __construct(private readonly EventDispatcherInterface $eventDispatcher)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function convert(PaymentAppEntity $app, PaymentRequest $request, PaymentRoute $route, Context $context): array
    {
        $data = [
            'paymentAppId' => $app->getId(),
            'externalOrderNo' => $request->externalOrderNo,
            'amount' => $request->amount,
            'currencyCode' => strtoupper($request->currencyCode),
            'channelCode' => $route->gateway->code(),
            'channelConfigId' => $route->channelConfigId,
            'methodCode' => $request->method,
            'deviceType' => $request->deviceType ?? $request->method,
            'subject' => $request->subject,
            'clientIp' => $request->clientIp,
            'channelExtra' => $request->extra,
            'notifyUrl' => $request->notifyUrl,
            'returnUrl' => $request->returnUrl,
        ];
        $event = new PaymentOrderConvertedEvent($app, $request, $route, $data, $context);
        $this->eventDispatcher->dispatch($event);

        return $event->convertedOrder;
    }
}
