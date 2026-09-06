<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Gateway\Alipay;

use Contena\Core\System\Payment\DataAbstractionLayer\PaymentChannelMethod\PaymentMethods;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentChannelNotifyRecord\PaymentNotificationTypes;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\PaymentOrderEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentRefund\PaymentRefundEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentTransfer\PaymentTransferEntity;
use Contena\Core\System\Payment\Gateway\GatewayNotificationHandlerInterface;
use Contena\Core\System\Payment\Gateway\PaymentHandlerInterface;
use Contena\Core\System\Payment\Gateway\PaymentQueryHandlerInterface;
use Contena\Core\System\Payment\Gateway\PaymentStatus;
use Contena\Core\System\Payment\Gateway\RefundHandlerInterface;
use Contena\Core\System\Payment\Gateway\TransferHandlerInterface;
use Contena\Core\System\Payment\Gateway\YansongdaPayClientInterface;
use Contena\Core\System\Payment\Notification\Struct\GatewayNotification;
use Contena\Core\System\Payment\Notification\Struct\GatewayNotificationResult;
use Contena\Core\System\Payment\PaymentException;
use Contena\Core\System\Payment\Struct\PaymentResult;
use Yansongda\Pay\Pay;

/**
 * @internal
 */
final readonly class AlipayGateway implements PaymentHandlerInterface, PaymentQueryHandlerInterface, RefundHandlerInterface, TransferHandlerInterface, GatewayNotificationHandlerInterface
{
    public function __construct(private YansongdaPayClientInterface $client)
    {
    }

    public function code(): string
    {
        return 'alipay';
    }

    public function pay(PaymentOrderEntity $order, array $config): PaymentResult
    {
        $action = match ($order->methodCode) {
            PaymentMethods::H5 => 'h5',
            PaymentMethods::APP => 'app',
            PaymentMethods::MINI_PROGRAM => 'mini',
            PaymentMethods::PAGE => 'web',
            PaymentMethods::FACE => 'pos',
            default => throw PaymentException::capabilityNotSupported($this->code(), 'pay:' . $order->methodCode),
        };
        $parameters = array_replace($order->channelExtra ?? [], [
            'out_trade_no' => $order->orderNo,
            'total_amount' => $this->formatAmount($order->amount),
            'subject' => $order->subject,
            '_return_url' => $order->returnUrl,
        ]);

        return $this->mapPayResult($this->call($config, $action, $parameters), $order->methodCode);
    }

    public function query(PaymentOrderEntity $order, array $config): PaymentResult
    {
        $data = $this->call($config, 'query', array_filter([
            'out_trade_no' => $order->orderNo,
            'trade_no' => $order->channelTradeNo,
        ]));
        $status = match ($data['trade_status'] ?? null) {
            'TRADE_SUCCESS', 'TRADE_FINISHED' => PaymentStatus::SUCCEEDED,
            'WAIT_BUYER_PAY' => PaymentStatus::PENDING,
            'TRADE_CLOSED' => PaymentStatus::CLOSED,
            default => PaymentStatus::UNKNOWN,
        };

        return $this->createResult($data, $status, 'out_trade_no', ['trade_no']);
    }

    public function refund(PaymentRefundEntity $refund, PaymentOrderEntity $order, array $config): PaymentResult
    {
        $data = $this->call($config, 'refund', array_filter([
            'out_trade_no' => $order->orderNo,
            'trade_no' => $order->channelTradeNo,
            'out_request_no' => $refund->refundNo,
            'refund_amount' => $this->formatAmount($refund->refundAmount),
            'refund_reason' => $refund->reason,
        ]));
        $status = match ($data['code'] ?? null) {
            '10000' => PaymentStatus::SUCCEEDED,
            '40001', '40002', '40004', '40006' => PaymentStatus::FAILED,
            default => PaymentStatus::UNKNOWN,
        };

        return $this->createResult($data, $status, 'out_request_no', ['refund_id', 'trade_no']);
    }

    public function transfer(PaymentTransferEntity $transfer, array $config): PaymentResult
    {
        $data = $this->call($config, 'transfer', array_replace($transfer->channelExtra ?? [], [
            'out_biz_no' => $transfer->transferNo,
            'trans_amount' => $this->formatAmount($transfer->amount),
            'product_code' => 'TRANS_ACCOUNT_NO_PWD',
            'biz_scene' => 'DIRECT_TRANSFER',
            'payee_info' => ['identity' => $transfer->payee, 'identity_type' => 'ALIPAY_LOGON_ID', 'name' => $transfer->payeeName],
            'order_title' => $transfer->remark ?? $transfer->transferNo,
        ]));
        $status = match ($data['code'] ?? null) {
            '10000' => match ($data['status'] ?? null) {
                'SUCCESS' => PaymentStatus::SUCCEEDED,
                'DEALING' => PaymentStatus::PROCESSING,
                'FAIL' => PaymentStatus::FAILED,
                default => PaymentStatus::UNKNOWN,
            },
            '40001', '40002', '40004', '40006' => PaymentStatus::FAILED,
            default => PaymentStatus::UNKNOWN,
        };

        return $this->createResult($data, $status, 'out_biz_no', ['order_id']);
    }

    public function handleNotification(GatewayNotification $notification, array $config): GatewayNotificationResult
    {
        $data = $this->call($config, 'callback', $notification->parameters);
        $type = match (true) {
            isset($data['out_request_no']) => PaymentNotificationTypes::REFUND,
            isset($data['external_agreement_no']) || isset($data['agreement_no']) => PaymentNotificationTypes::SUBSCRIPTION,
            isset($data['out_biz_no']) && !isset($data['out_trade_no']) => PaymentNotificationTypes::TRANSFER,
            default => PaymentNotificationTypes::PAYMENT,
        };
        $resourceNo = $this->notificationResourceNo($data, $type);
        $status = $this->notificationStatus($data, $type);
        $result = match ($type) {
            PaymentNotificationTypes::REFUND => $this->createResult($data, $status, 'out_request_no', ['refund_id', 'trade_no']),
            PaymentNotificationTypes::TRANSFER => $this->createResult($data, $status, 'out_biz_no', ['order_id']),
            PaymentNotificationTypes::SUBSCRIPTION => $this->createResult($data, $status, 'external_agreement_no', ['agreement_no', 'contract_id']),
            default => $this->createResult($data, $status, 'out_trade_no', ['trade_no']),
        };

        return new GatewayNotificationResult(
            $type,
            $resourceNo,
            $result,
            'success',
        );
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
        } elseif (\is_string($data['h5_url'] ?? null) && $data['h5_url'] !== '') {
            $action = PaymentResult::ACTION_REDIRECT;
            $actionValue = $data['h5_url'];
        }

        return $this->createResult($data, PaymentStatus::PENDING, 'out_trade_no', ['trade_no'], $action, $actionValue);
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string> $resourceKeys
     */
    private function createResult(array $data, string $status, string $requestKey, array $resourceKeys, string $action = PaymentResult::ACTION_NONE, ?string $actionValue = null): PaymentResult
    {
        return new PaymentResult(
            status: $status,
            action: $action,
            actionValue: $actionValue,
            providerRequestId: $this->readString($data, $requestKey),
            providerResourceId: $this->readString($data, ...$resourceKeys),
            resultCode: $this->readString($data, 'code', 'result_code'),
            resultMessage: $this->readString($data, 'msg', 'message', 'sub_msg'),
            data: $data,
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private function readString(array $data, string ...$keys): ?string
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
    private function notificationResourceNo(array $data, int $type): string
    {
        $value = match ($type) {
            PaymentNotificationTypes::REFUND => $data['out_request_no'] ?? null,
            PaymentNotificationTypes::TRANSFER => $data['out_biz_no'] ?? null,
            PaymentNotificationTypes::SUBSCRIPTION => $data['external_agreement_no'] ?? null,
            default => $data['out_trade_no'] ?? null,
        };

        return \is_scalar($value) && (string) $value !== ''
            ? (string) $value
            : throw PaymentException::invalidRequest('The Alipay notification has no platform resource number.');
    }

    /**
     * @param array<string, mixed> $data
     */
    private function notificationStatus(array $data, int $type): string
    {
        $status = $data['trade_status'] ?? $data['refund_status'] ?? $data['status'] ?? null;

        return match ($type) {
            PaymentNotificationTypes::PAYMENT => match ($status) {
                'TRADE_SUCCESS', 'TRADE_FINISHED' => PaymentStatus::SUCCEEDED,
                'WAIT_BUYER_PAY' => PaymentStatus::PENDING,
                'TRADE_CLOSED' => PaymentStatus::CLOSED,
                default => PaymentStatus::UNKNOWN,
            },
            PaymentNotificationTypes::REFUND => $status === 'REFUND_SUCCESS' ? PaymentStatus::SUCCEEDED : PaymentStatus::UNKNOWN,
            PaymentNotificationTypes::TRANSFER => match ($status) {
                'SUCCESS', 'FINISHED' => PaymentStatus::SUCCEEDED,
                'FAIL', 'FAILED' => PaymentStatus::FAILED,
                default => PaymentStatus::PROCESSING,
            },
            PaymentNotificationTypes::SUBSCRIPTION => match ($status) {
                'NORMAL', 'SIGNED' => PaymentStatus::SUCCEEDED,
                'STOP', 'UNSIGNED' => PaymentStatus::CLOSED,
                default => PaymentStatus::PENDING,
            },
            default => PaymentStatus::UNKNOWN,
        };
    }

    /**
     * @param array<string, mixed> $config
     * @param array<string, mixed> $parameters
     *
     * @return array<string, mixed>
     */
    private function call(array $config, string $action, array $parameters): array
    {
        return $this->client->request($this->code(), $this->mapConfig($config), $action, $parameters);
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private function mapConfig(array $config): array
    {
        return [
            'app_id' => $config['appId'] ?? '',
            'app_secret_cert' => $config['appPrivateKey'] ?? '',
            'alipay_public_cert_path' => $config['alipayPublicKey'] ?? '',
            'notify_url' => $config['notifyUrl'] ?? '',
            'return_url' => $config['returnUrl'] ?? '',
            'mode' => ($config['mode'] ?? 'normal') === 'sandbox' ? Pay::MODE_SANDBOX : Pay::MODE_NORMAL,
        ];
    }

    private function formatAmount(int $amount): string
    {
        return \sprintf('%d.%02d', intdiv($amount, 100), $amount % 100);
    }
}
