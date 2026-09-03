<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Gateway\Wechat;

use Contena\Core\System\Payment\DataAbstractionLayer\PaymentChannelMethod\PaymentMethods;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\PaymentOrderEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentRefund\PaymentRefundEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentTransfer\PaymentTransferEntity;
use Contena\Core\System\Payment\Gateway\GatewayExecutorInterface;
use Contena\Core\System\Payment\Gateway\PaymentHandlerInterface;
use Contena\Core\System\Payment\Gateway\PaymentStatus;
use Contena\Core\System\Payment\Gateway\ProviderResultMapper;
use Contena\Core\System\Payment\Gateway\QueryHandlerInterface;
use Contena\Core\System\Payment\Gateway\RefundHandlerInterface;
use Contena\Core\System\Payment\Gateway\TransferHandlerInterface;
use Contena\Core\System\Payment\PaymentException;
use Contena\Core\System\Payment\Struct\PaymentResult;

/**
 * @internal
 */
final readonly class WechatGateway implements PaymentHandlerInterface, QueryHandlerInterface, RefundHandlerInterface, TransferHandlerInterface
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
            'notify_url' => $order->notifyUrl,
        ]);

        return ProviderResultMapper::paymentResult(ProviderResultMapper::data($this->call($config, $action, $parameters)), PaymentStatus::PENDING, $order->methodCode);
    }

    public function query(PaymentOrderEntity $order, array $config): PaymentResult
    {
        $parameters = array_filter([
            'out_trade_no' => $order->orderNo,
            'transaction_id' => $order->channelTradeNo,
            '_action' => $this->action($order->methodCode),
        ]);
        $data = ProviderResultMapper::data($this->call($config, 'query', $parameters));
        $status = match ($data['trade_state'] ?? null) {
            'SUCCESS' => PaymentStatus::SUCCEEDED,
            'NOTPAY', 'USERPAYING' => PaymentStatus::PENDING,
            'CLOSED', 'REVOKED', 'PAYERROR' => PaymentStatus::CLOSED,
            default => PaymentStatus::UNKNOWN,
        };

        return ProviderResultMapper::paymentResult($data, $status);
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
        $data = ProviderResultMapper::data($this->call($config, 'refund', $parameters));
        $status = isset($data['refund_id']) ? PaymentStatus::PROCESSING : PaymentStatus::FAILED;

        return ProviderResultMapper::paymentResult($data, $status);
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
        $data = ProviderResultMapper::data($this->call($config, 'transfer', $parameters));
        $status = isset($data['transfer_bill_no'], $data['out_bill_no']) ? PaymentStatus::PROCESSING : PaymentStatus::FAILED;

        return ProviderResultMapper::paymentResult($data, $status);
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
