<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Gateway\Alipay;

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
use Yansongda\Pay\Pay;

/**
 * @internal
 */
final readonly class AlipayGateway implements PaymentHandlerInterface, QueryHandlerInterface, RefundHandlerInterface, TransferHandlerInterface
{
    public function __construct(private GatewayExecutorInterface $executor)
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
            'total_amount' => number_format($order->amount / 100, 2, '.', ''),
            'subject' => $order->subject,
            '_notify_url' => $order->notifyUrl,
            '_return_url' => $order->returnUrl,
        ]);

        return ProviderResultMapper::paymentResult(ProviderResultMapper::data($this->call($config, $action, $parameters)), PaymentStatus::PENDING, $order->methodCode);
    }

    public function query(PaymentOrderEntity $order, array $config): PaymentResult
    {
        $data = ProviderResultMapper::data($this->call($config, 'query', array_filter([
            'out_trade_no' => $order->orderNo,
            'trade_no' => $order->channelTradeNo,
        ])));
        $status = match ($data['trade_status'] ?? null) {
            'TRADE_SUCCESS', 'TRADE_FINISHED' => PaymentStatus::SUCCEEDED,
            'WAIT_BUYER_PAY' => PaymentStatus::PENDING,
            'TRADE_CLOSED' => PaymentStatus::CLOSED,
            default => PaymentStatus::UNKNOWN,
        };

        return ProviderResultMapper::paymentResult($data, $status);
    }

    public function refund(PaymentRefundEntity $refund, PaymentOrderEntity $order, array $config): PaymentResult
    {
        $data = ProviderResultMapper::data($this->call($config, 'refund', array_filter([
            'out_trade_no' => $order->orderNo,
            'trade_no' => $order->channelTradeNo,
            'out_request_no' => $refund->refundNo,
            'refund_amount' => number_format($refund->refundAmount / 100, 2, '.', ''),
            'refund_reason' => $refund->reason,
        ])));
        $status = ($data['code'] ?? null) === '10000' ? PaymentStatus::SUCCEEDED : PaymentStatus::FAILED;

        return ProviderResultMapper::paymentResult($data, $status);
    }

    public function transfer(PaymentTransferEntity $transfer, array $config): PaymentResult
    {
        $data = ProviderResultMapper::data($this->call($config, 'transfer', array_replace($transfer->channelExtra ?? [], [
            'out_biz_no' => $transfer->transferNo,
            'trans_amount' => number_format($transfer->amount / 100, 2, '.', ''),
            'product_code' => 'TRANS_ACCOUNT_NO_PWD',
            'biz_scene' => 'DIRECT_TRANSFER',
            'payee_info' => ['identity' => $transfer->payee, 'identity_type' => 'ALIPAY_LOGON_ID', 'name' => $transfer->payeeName],
            'order_title' => $transfer->remark ?? $transfer->transferNo,
        ])));
        $status = ($data['code'] ?? null) === '10000' ? PaymentStatus::SUCCEEDED : PaymentStatus::FAILED;

        return ProviderResultMapper::paymentResult($data, $status);
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
            'app_id' => $config['appId'] ?? '',
            'app_secret_cert' => $config['appPrivateKey'] ?? '',
            'alipay_public_cert_path' => $config['alipayPublicKey'] ?? '',
            'notify_url' => $config['notifyUrl'] ?? '',
            'return_url' => $config['returnUrl'] ?? '',
            'mode' => ($config['mode'] ?? 'normal') === 'sandbox' ? Pay::MODE_SANDBOX : Pay::MODE_NORMAL,
        ];
    }
}
