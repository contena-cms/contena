<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Gateway\Wechat;

use Contena\Core\System\Payment\DataAbstractionLayer\PaymentChannelMethod\PaymentMethods;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentChannelNotifyRecord\PaymentNotificationTypes;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\PaymentOrderEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentRefund\PaymentRefundEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentTransfer\PaymentTransferEntity;
use Contena\Core\System\Payment\Gateway\GatewayExecutorInterface;
use Contena\Core\System\Payment\Gateway\GatewayNotificationHandlerInterface;
use Contena\Core\System\Payment\Gateway\PaymentHandlerInterface;
use Contena\Core\System\Payment\Gateway\PaymentStatus;
use Contena\Core\System\Payment\Gateway\QueryHandlerInterface;
use Contena\Core\System\Payment\Gateway\RefundHandlerInterface;
use Contena\Core\System\Payment\Gateway\TransferHandlerInterface;
use Contena\Core\System\Payment\PaymentException;
use Contena\Core\System\Payment\Struct\GatewayNotification;
use Contena\Core\System\Payment\Struct\GatewayNotificationResult;
use Contena\Core\System\Payment\Struct\PaymentResult;
use Psr\Http\Message\ResponseInterface;
use Yansongda\Artful\Rocket;
use Yansongda\Supports\Collection;

/**
 * @internal
 */
final readonly class WechatGateway implements PaymentHandlerInterface, QueryHandlerInterface, RefundHandlerInterface, TransferHandlerInterface, GatewayNotificationHandlerInterface
{
    public function __construct(private GatewayExecutorInterface $executor)
    {
    }

    public function code(): string
    {
        return 'wechat';
    }

    public function pay(PaymentOrderEntity $order, array $config): PaymentResult
    {
        $action = match ($order->methodCode) {
            PaymentMethods::H5 => 'h5',
            PaymentMethods::APP => 'app',
            PaymentMethods::MINI_PROGRAM => 'mini',
            PaymentMethods::JSAPI => 'mp',
            PaymentMethods::NATIVE => 'scan',
            default => throw PaymentException::capabilityNotSupported($this->code(), 'pay:' . $order->methodCode),
        };
        $parameters = array_replace($order->channelExtra ?? [], [
            'out_trade_no' => $order->orderNo,
            'description' => $order->subject,
            'amount' => ['total' => $order->amount, 'currency' => $order->currencyCode],
        ]);

        return $this->mapPayResult($this->responseData($this->call($config, $action, $parameters)), $order->methodCode);
    }

    public function query(PaymentOrderEntity $order, array $config): PaymentResult
    {
        $parameters = array_filter([
            'out_trade_no' => $order->orderNo,
            'transaction_id' => $order->channelTradeNo,
            '_action' => $this->action($order->methodCode),
        ]);
        $data = $this->responseData($this->call($config, 'query', $parameters));
        $status = match ($data['trade_state'] ?? null) {
            'SUCCESS' => PaymentStatus::SUCCEEDED,
            'NOTPAY', 'USERPAYING' => PaymentStatus::PENDING,
            'CLOSED', 'REVOKED', 'PAYERROR' => PaymentStatus::CLOSED,
            default => PaymentStatus::UNKNOWN,
        };

        return $this->result($data, $status, 'out_trade_no', ['transaction_id']);
    }

    public function refund(PaymentRefundEntity $refund, PaymentOrderEntity $order, array $config): PaymentResult
    {
        $parameters = array_filter([
            'out_trade_no' => $order->orderNo,
            'transaction_id' => $order->channelTradeNo,
            'out_refund_no' => $refund->refundNo,
            'reason' => $refund->reason,
            'amount' => ['refund' => $refund->refundAmount, 'total' => $order->amount, 'currency' => $order->currencyCode],
        ]);
        $data = $this->responseData($this->call($config, 'refund', $parameters));
        $status = isset($data['refund_id']) ? PaymentStatus::PROCESSING : PaymentStatus::FAILED;

        return $this->result($data, $status, 'out_refund_no', ['refund_id']);
    }

    public function transfer(PaymentTransferEntity $transfer, array $config): PaymentResult
    {
        $parameters = array_replace($transfer->channelExtra ?? [], [
            'out_bill_no' => $transfer->transferNo,
            'transfer_scene_id' => ($transfer->channelExtra ?? [])['transfer_scene_id'] ?? '1000',
            'openid' => $transfer->payee,
            'transfer_amount' => $transfer->amount,
            'transfer_remark' => $transfer->remark ?? $transfer->transferNo,
            'user_name' => $transfer->payeeName,
        ]);
        $data = $this->responseData($this->call($config, 'transfer', $parameters));
        $status = isset($data['transfer_bill_no'], $data['out_bill_no']) ? PaymentStatus::PROCESSING : PaymentStatus::FAILED;

        return $this->result($data, $status, 'out_bill_no', ['transfer_bill_no']);
    }

    public function handleNotification(GatewayNotification $notification, array $config): GatewayNotificationResult
    {
        $data = $this->responseData($this->call($config, 'callback', [
            'body' => $notification->rawBody,
            'headers' => $notification->headers,
        ]));
        $type = $this->notificationType($data);
        $resourceNo = $this->notificationResourceNo($data, $type);
        $status = $this->notificationStatus($data, $type);

        return new GatewayNotificationResult(
            $type,
            $resourceNo,
            $this->mapNotificationResult($data, $status, $type),
            json_encode(['code' => 'SUCCESS', 'message' => '成功'], \JSON_THROW_ON_ERROR),
            'application/json',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function responseData(mixed $response): array
    {
        if ($response instanceof Collection) {
            return $response->all();
        }
        if ($response instanceof Rocket) {
            return $this->responseData($response->getDestination() ?? $response->getPayload());
        }
        if ($response instanceof ResponseInterface) {
            return [
                '_http_status' => $response->getStatusCode(),
                '_headers' => $response->getHeaders(),
                '_body' => (string) $response->getBody(),
            ];
        }
        if (\is_array($response)) {
            return $response;
        }

        return ['value' => $response];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function mapPayResult(array $data, string $method): PaymentResult
    {
        $action = PaymentResult::ACTION_NONE;
        $actionValue = null;

        if (\is_string($data['_body'] ?? null) && $data['_body'] !== '') {
            $action = $method === PaymentMethods::APP ? PaymentResult::ACTION_CLIENT : PaymentResult::ACTION_HTML;
            $actionValue = $data['_body'];
        } elseif ($method === PaymentMethods::H5 && \is_string($data['h5_url'] ?? null) && $data['h5_url'] !== '') {
            $action = PaymentResult::ACTION_REDIRECT;
            $actionValue = $data['h5_url'];
        } elseif ($method === PaymentMethods::NATIVE && \is_string($data['code_url'] ?? $data['qr_code'] ?? null)) {
            $action = PaymentResult::ACTION_QR_CODE;
            $actionValue = (string) ($data['code_url'] ?? $data['qr_code']);
        } elseif (\in_array($method, [PaymentMethods::APP, PaymentMethods::JSAPI, PaymentMethods::MINI_PROGRAM], true) && (isset($data['prepay_id']) || isset($data['paySign']))) {
            $action = PaymentResult::ACTION_CLIENT;
            $actionValue = json_encode($data, \JSON_THROW_ON_ERROR);
        }

        return $this->result($data, PaymentStatus::PENDING, 'out_trade_no', ['transaction_id'], $action, $actionValue);
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string> $resourceKeys
     */
    private function result(array $data, string $status, string $requestKey, array $resourceKeys, string $action = PaymentResult::ACTION_NONE, ?string $actionValue = null): PaymentResult
    {
        return new PaymentResult(
            $status,
            $action,
            $actionValue,
            $this->string($data, $requestKey),
            $this->string($data, ...$resourceKeys),
            $this->string($data, 'code', 'result_code'),
            $this->string($data, 'message', 'msg', 'result_msg'),
            $data,
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private function mapNotificationResult(array $data, string $status, int $type): PaymentResult
    {
        return match ($type) {
            PaymentNotificationTypes::REFUND => $this->result($data, $status, 'out_refund_no', ['refund_id']),
            PaymentNotificationTypes::TRANSFER => $this->result($data, $status, 'out_bill_no', ['transfer_bill_no']),
            PaymentNotificationTypes::SUBSCRIPTION => $this->result($data, $status, 'external_agreement_no', ['contract_id', 'agreement_no']),
            default => $this->result($data, $status, 'out_trade_no', ['transaction_id']),
        };
    }

    /**
     * @param array<string, mixed> $data
     */
    private function string(array $data, string ...$keys): ?string
    {
        foreach ($keys as $key) {
            if (\is_scalar($data[$key] ?? null)) {
                return (string) $data[$key];
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function notificationType(array $data): int
    {
        return match (true) {
            isset($data['out_refund_no']) => PaymentNotificationTypes::REFUND,
            isset($data['out_bill_no']) => PaymentNotificationTypes::TRANSFER,
            isset($data['contract_id']) || isset($data['external_agreement_no']) => PaymentNotificationTypes::SUBSCRIPTION,
            default => PaymentNotificationTypes::PAYMENT,
        };
    }

    /**
     * @param array<string, mixed> $data
     */
    private function notificationResourceNo(array $data, int $type): string
    {
        $value = match ($type) {
            PaymentNotificationTypes::REFUND => $data['out_refund_no'] ?? null,
            PaymentNotificationTypes::TRANSFER => $data['out_bill_no'] ?? null,
            PaymentNotificationTypes::SUBSCRIPTION => $data['external_agreement_no'] ?? null,
            default => $data['out_trade_no'] ?? null,
        };

        return \is_scalar($value) && (string) $value !== ''
            ? (string) $value
            : throw PaymentException::invalidRequest('The WeChat Pay notification has no platform resource number.');
    }

    /**
     * @param array<string, mixed> $data
     */
    private function notificationStatus(array $data, int $type): string
    {
        $status = $data['trade_state'] ?? $data['refund_status'] ?? $data['state'] ?? $data['contract_state'] ?? null;

        return match ($type) {
            PaymentNotificationTypes::PAYMENT => match ($status) {
                'SUCCESS' => PaymentStatus::SUCCEEDED,
                'NOTPAY', 'USERPAYING' => PaymentStatus::PENDING,
                'CLOSED', 'REVOKED', 'PAYERROR' => PaymentStatus::CLOSED,
                default => PaymentStatus::UNKNOWN,
            },
            PaymentNotificationTypes::REFUND => $status === 'SUCCESS' ? PaymentStatus::SUCCEEDED : ($status === 'ABNORMAL' ? PaymentStatus::FAILED : PaymentStatus::PROCESSING),
            PaymentNotificationTypes::TRANSFER => $status === 'SUCCESS' ? PaymentStatus::SUCCEEDED : (\in_array($status, ['FAIL', 'FAILED'], true) ? PaymentStatus::FAILED : PaymentStatus::PROCESSING),
            PaymentNotificationTypes::SUBSCRIPTION => \in_array($status, ['SIGNED', 'NORMAL'], true) ? PaymentStatus::SUCCEEDED : (\in_array($status, ['TERMINATED', 'UNSIGNED'], true) ? PaymentStatus::CLOSED : PaymentStatus::PENDING),
            default => PaymentStatus::UNKNOWN,
        };
    }

    private function action(?string $method): string
    {
        return match ($method) {
            PaymentMethods::APP => 'app',
            PaymentMethods::MINI_PROGRAM => 'mini',
            PaymentMethods::JSAPI => 'jsapi',
            PaymentMethods::H5 => 'h5',
            default => 'native',
        };
    }

    /**
     * @param array<string, mixed> $config
     * @param array<string, mixed> $parameters
     */
    private function call(array $config, string $action, array $parameters): mixed
    {
        return $this->executor->execute($this->code(), $this->mapConfig($config), $action, $parameters);
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private function mapConfig(array $config): array
    {
        return [
            'mch_id' => $config['merchantId'] ?? '',
            'mch_secret_key' => $config['merchantSecretKey'] ?? '',
            'mch_secret_cert' => $config['merchantPrivateKey'] ?? '',
            'mch_public_cert_path' => $config['merchantCertificate'] ?? '',
            'app_id' => $config['appId'] ?? '',
            'mp_app_id' => $config['officialAccountAppId'] ?? '',
            'mini_app_id' => $config['miniProgramAppId'] ?? '',
            'notify_url' => $config['notifyUrl'] ?? '',
        ];
    }
}
