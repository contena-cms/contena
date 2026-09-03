<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Gateway\Wechat;

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

    public function pay(PaymentRequest $request, array $config): PaymentResult
    {
        $action = match ($request->method) {
            PaymentMethods::H5 => 'h5',
            PaymentMethods::APP => 'app',
            PaymentMethods::MINI_PROGRAM => 'mini',
            PaymentMethods::JSAPI => 'mp',
            PaymentMethods::NATIVE => 'scan',
            default => throw PaymentException::capabilityNotSupported($this->code(), 'pay:' . $request->method),
        };
        $parameters = array_replace($request->extra, [
            'out_trade_no' => $request->externalOrderNo,
            'description' => $request->subject,
            'amount' => ['total' => $request->amount, 'currency' => $request->currencyCode],
            'notify_url' => $request->notifyUrl,
        ]);

        return ProviderResultMapper::paymentResult(ProviderResultMapper::data($this->call($config, $action, $parameters)), PaymentStatus::PENDING, $request->method);
    }

    public function query(QueryRequest $request, array $config): PaymentResult
    {
        $data = ProviderResultMapper::data($this->call($config, 'query', array_filter([
            'out_trade_no' => $request->orderNo,
            'transaction_id' => $request->providerTradeNo,
            '_action' => $this->action($request->method),
        ])));
        $status = match ($data['trade_state'] ?? null) {
            'SUCCESS' => PaymentStatus::SUCCEEDED,
            'NOTPAY', 'USERPAYING' => PaymentStatus::PENDING,
            'CLOSED', 'REVOKED', 'PAYERROR' => PaymentStatus::CLOSED,
            default => PaymentStatus::UNKNOWN,
        };

        return ProviderResultMapper::paymentResult($data, $status);
    }

    public function refund(RefundRequest $request, array $config): PaymentResult
    {
        $data = ProviderResultMapper::data($this->call($config, 'refund', array_filter([
            'out_trade_no' => $request->orderNo,
            'transaction_id' => $request->providerTradeNo,
            'out_refund_no' => $request->refundNo,
            'reason' => $request->reason,
            'amount' => ['refund' => $request->amount, 'total' => $request->totalAmount, 'currency' => $request->currencyCode],
        ])));
        $status = isset($data['refund_id']) ? PaymentStatus::PROCESSING : PaymentStatus::FAILED;

        return ProviderResultMapper::paymentResult($data, $status);
    }

    public function transfer(TransferRequest $request, array $config): PaymentResult
    {
        $data = ProviderResultMapper::data($this->call($config, 'transfer', array_replace($request->extra, [
            'out_bill_no' => $request->externalTransferNo,
            'transfer_scene_id' => $request->extra['transfer_scene_id'] ?? '1000',
            'openid' => $request->payee,
            'transfer_amount' => $request->amount,
            'transfer_remark' => $request->remark ?? $request->externalTransferNo,
            'user_name' => $request->payeeName,
        ])));
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
