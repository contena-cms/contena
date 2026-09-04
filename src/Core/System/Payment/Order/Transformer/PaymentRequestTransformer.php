<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Order\Transformer;

use Contena\Core\System\Payment\OpenApi\Api\PaymentRequest;

class PaymentRequestTransformer
{
    /**
     * @return array<string, mixed>
     */
    public static function transform(PaymentRequest $request): array
    {
        $data = [
            'externalOrderNo' => $request->externalOrderNo,
            'methodCode' => $request->method,
            'deviceType' => $request->deviceType ?? $request->method,
            'subject' => $request->subject,
            'clientIp' => $request->clientIp,
            'channelExtra' => $request->extra,
            'notifyUrl' => $request->notifyUrl,
            'returnUrl' => $request->returnUrl,
        ];

        return $data;
    }
}
