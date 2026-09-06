<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Gateway\Wechat;

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

/**
 * @internal
 */
final readonly class WechatGateway implements PaymentHandlerInterface, PaymentQueryHandlerInterface, RefundHandlerInterface, TransferHandlerInterface, GatewayNotificationHandlerInterface
{
    public function __construct(private YansongdaPayClientInterface $client)
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

        return $this->mapPayResult($this->call($config, $action, $parameters), $order->methodCode);
    }

    public function query(PaymentOrderEntity $order, array $config): PaymentResult
    {
        $parameters = array_filter([
            'out_trade_no' => $order->orderNo,
            'transaction_id' => $order->channelTradeNo,
            '_action' => $this->queryAction($order->methodCode),
        ]);
        $data = $this->call($config, 'query', $parameters);
        $status = match ($data['trade_state'] ?? null) {
            'SUCCESS' => PaymentStatus::SUCCEEDED,
            'NOTPAY', 'USERPAYING' => PaymentStatus::PENDING,
            'CLOSED', 'REVOKED', 'PAYERROR' => PaymentStatus::CLOSED,
            default => PaymentStatus::UNKNOWN,
        };

        return $this->createResult($data, $status, 'out_trade_no', ['transaction_id']);
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
        $data = $this->call($config, 'refund', $parameters);
        $status = $this->mapRefundStatus($this->readString($data, 'status'));

        return $this->createResult($data, $status, 'out_refund_no', ['refund_id']);
    }

    public function transfer(PaymentTransferEntity $transfer, array $config): PaymentResult
    {
        $parameters = array_replace($transfer->channelExtra ?? [], [
            '_action' => 'mch_transfer',
            'out_bill_no' => $transfer->transferNo,
            'transfer_scene_id' => ($transfer->channelExtra ?? [])['transfer_scene_id'] ?? '1000',
            'openid' => $transfer->payee,
            'transfer_amount' => $transfer->amount,
            'transfer_remark' => $transfer->remark ?? $transfer->transferNo,
            'user_name' => $transfer->payeeName,
        ]);
        $data = $this->call($config, 'transfer', $parameters);
        $status = $this->mapTransferStatus($this->readString($data, 'state'));

        return $this->createResult($data, $status, 'out_bill_no', ['transfer_bill_no']);
    }

    public function handleNotification(GatewayNotification $notification, array $config): GatewayNotificationResult
    {
        $data = $this->call($config, 'callback', [
            'body' => $notification->rawBody,
            'headers' => $notification->headers,
        ]);
        $type = match (true) {
            isset($data['out_refund_no']) => PaymentNotificationTypes::REFUND,
            isset($data['out_bill_no']) => PaymentNotificationTypes::TRANSFER,
            isset($data['contract_id']) || isset($data['external_agreement_no']) => PaymentNotificationTypes::SUBSCRIPTION,
            default => PaymentNotificationTypes::PAYMENT,
        };
        $resourceNo = $this->notificationResourceNo($data, $type);
        $status = $this->notificationStatus($data, $type);
        $result = match ($type) {
            PaymentNotificationTypes::REFUND => $this->createResult($data, $status, 'out_refund_no', ['refund_id']),
            PaymentNotificationTypes::TRANSFER => $this->createResult($data, $status, 'out_bill_no', ['transfer_bill_no']),
            PaymentNotificationTypes::SUBSCRIPTION => $this->createResult($data, $status, 'external_agreement_no', ['contract_id', 'agreement_no']),
            default => $this->createResult($data, $status, 'out_trade_no', ['transaction_id']),
        };

        return new GatewayNotificationResult(
            $type,
            $resourceNo,
            $result,
            json_encode(['code' => 'SUCCESS', 'message' => '成功'], \JSON_THROW_ON_ERROR),
            'application/json',
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
        } elseif ($method === PaymentMethods::H5 && \is_string($data['h5_url'] ?? null) && $data['h5_url'] !== '') {
            $action = PaymentResult::ACTION_REDIRECT;
            $actionValue = $data['h5_url'];
        } elseif ($method === PaymentMethods::NATIVE && \is_string($data['code_url'] ?? $data['qr_code'] ?? null)) {
            $action = PaymentResult::ACTION_QR_CODE;
            $actionValue = (string) ($data['code_url'] ?? $data['qr_code']);
        } elseif (\in_array($method, [PaymentMethods::APP, PaymentMethods::JSAPI, PaymentMethods::MINI_PROGRAM], true)
            && (isset($data['prepay_id']) || isset($data['paySign']))
        ) {
            $action = PaymentResult::ACTION_CLIENT;
            $actionValue = json_encode($data, \JSON_THROW_ON_ERROR);
        }

        return $this->createResult($data, PaymentStatus::PENDING, 'out_trade_no', ['transaction_id'], $action, $actionValue);
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
            resultMessage: $this->readString($data, 'message', 'msg', 'result_msg'),
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
        return match ($type) {
            PaymentNotificationTypes::PAYMENT => match ($data['trade_state'] ?? null) {
                'SUCCESS' => PaymentStatus::SUCCEEDED,
                'NOTPAY', 'USERPAYING' => PaymentStatus::PENDING,
                'CLOSED', 'REVOKED', 'PAYERROR' => PaymentStatus::CLOSED,
                default => PaymentStatus::UNKNOWN,
            },
            PaymentNotificationTypes::REFUND => $this->mapRefundStatus($this->readString($data, 'refund_status')),
            PaymentNotificationTypes::TRANSFER => $this->mapTransferStatus($this->readString($data, 'state')),
            PaymentNotificationTypes::SUBSCRIPTION => match ($data['contract_state'] ?? null) {
                'SIGNED', 'NORMAL' => PaymentStatus::SUCCEEDED,
                'TERMINATED', 'UNSIGNED' => PaymentStatus::CLOSED,
                default => PaymentStatus::UNKNOWN,
            },
            default => PaymentStatus::UNKNOWN,
        };
    }

    private function queryAction(?string $method): string
    {
        return match ($method) {
            PaymentMethods::APP => 'app',
            PaymentMethods::MINI_PROGRAM => 'mini',
            PaymentMethods::JSAPI => 'jsapi',
            PaymentMethods::H5 => 'h5',
            default => 'native',
        };
    }

    private function mapRefundStatus(?string $status): string
    {
        return match ($status) {
            'SUCCESS' => PaymentStatus::SUCCEEDED,
            'PROCESSING' => PaymentStatus::PROCESSING,
            'CLOSED' => PaymentStatus::FAILED,
            // ABNORMAL requires reconciliation; it does not release the reservation.
            default => PaymentStatus::UNKNOWN,
        };
    }

    private function mapTransferStatus(?string $status): string
    {
        return match ($status) {
            'SUCCESS' => PaymentStatus::SUCCEEDED,
            'FAIL', 'FAILED', 'CANCELLED' => PaymentStatus::FAILED,
            'ACCEPTED', 'PROCESSING', 'WAIT_USER_CONFIRM', 'TRANSFERING', 'CANCELING' => PaymentStatus::PROCESSING,
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
