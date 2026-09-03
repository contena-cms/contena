<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Gateway\Alipay;

use Contena\Core\System\Payment\DataAbstractionLayer\PaymentChannelMethod\PaymentMethods;
use Contena\Core\System\Payment\Gateway\GatewayExecutorInterface;
use Contena\Core\System\Payment\Gateway\PaymentHandlerInterface;
use Contena\Core\System\Payment\Gateway\PaymentStatus;
use Contena\Core\System\Payment\Gateway\ProviderResultMapper;
use Contena\Core\System\Payment\Gateway\QueryHandlerInterface;
use Contena\Core\System\Payment\Gateway\RefundHandlerInterface;
use Contena\Core\System\Payment\Gateway\TransferHandlerInterface;
use Contena\Core\System\Payment\PaymentException;
use Contena\Core\System\Payment\Struct\PaymentRequest;
use Contena\Core\System\Payment\Struct\PaymentResult;
use Contena\Core\System\Payment\Struct\QueryRequest;
use Contena\Core\System\Payment\Struct\RefundRequest;
use Contena\Core\System\Payment\Struct\TransferRequest;
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

    public function pay(PaymentRequest $request, array $config): PaymentResult
    {
        $action = match ($request->method) {
            PaymentMethods::H5 => 'h5',
            PaymentMethods::APP => 'app',
            PaymentMethods::MINI_PROGRAM => 'mini',
            PaymentMethods::PAGE => 'web',
            PaymentMethods::FACE => 'pos',
            default => throw PaymentException::capabilityNotSupported($this->code(), 'pay:' . $request->method),
        };
        $parameters = array_replace($request->extra, [
            'out_trade_no' => $request->externalOrderNo,
            'total_amount' => number_format($request->amount / 100, 2, '.', ''),
            'subject' => $request->subject,
            '_notify_url' => $request->notifyUrl,
            '_return_url' => $request->returnUrl,
        ]);

        return ProviderResultMapper::paymentResult(ProviderResultMapper::data($this->call($config, $action, $parameters)), PaymentStatus::PENDING, $request->method);
    }

    public function query(QueryRequest $request, array $config): PaymentResult
    {
        $data = ProviderResultMapper::data($this->call($config, 'query', array_filter([
            'out_trade_no' => $request->orderNo,
            'trade_no' => $request->providerTradeNo,
        ])));
        $status = match ($data['trade_status'] ?? null) {
            'TRADE_SUCCESS', 'TRADE_FINISHED' => PaymentStatus::SUCCEEDED,
            'WAIT_BUYER_PAY' => PaymentStatus::PENDING,
            'TRADE_CLOSED' => PaymentStatus::CLOSED,
            default => PaymentStatus::UNKNOWN,
        };

        return ProviderResultMapper::paymentResult($data, $status);
    }

    public function refund(RefundRequest $request, array $config): PaymentResult
    {
        $data = ProviderResultMapper::data($this->call($config, 'refund', array_filter([
            'out_trade_no' => $request->orderNo,
            'trade_no' => $request->providerTradeNo,
            'out_request_no' => $request->refundNo,
            'refund_amount' => number_format($request->amount / 100, 2, '.', ''),
            'refund_reason' => $request->reason,
        ])));
        $status = ($data['code'] ?? null) === '10000' ? PaymentStatus::SUCCEEDED : PaymentStatus::FAILED;

        return ProviderResultMapper::paymentResult($data, $status);
    }

    public function transfer(TransferRequest $request, array $config): PaymentResult
    {
        $data = ProviderResultMapper::data($this->call($config, 'transfer', array_replace($request->extra, [
            'out_biz_no' => $request->externalTransferNo,
            'trans_amount' => number_format($request->amount / 100, 2, '.', ''),
            'product_code' => 'TRANS_ACCOUNT_NO_PWD',
            'biz_scene' => 'DIRECT_TRANSFER',
            'payee_info' => ['identity' => $request->payee, 'identity_type' => 'ALIPAY_LOGON_ID', 'name' => $request->payeeName],
            'order_title' => $request->remark ?? $request->externalTransferNo,
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
