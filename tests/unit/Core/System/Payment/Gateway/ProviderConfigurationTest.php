<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Payment\Gateway;

use Contena\Core\System\Payment\DataAbstractionLayer\PaymentChannelMethod\PaymentMethods;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\PaymentOrderEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentRefund\PaymentRefundEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentTransfer\PaymentTransferEntity;
use Contena\Core\System\Payment\Gateway\Alipay\AlipayGateway;
use Contena\Core\System\Payment\Gateway\GatewayExecutorInterface;
use Contena\Core\System\Payment\Gateway\PaymentStatus;
use Contena\Core\System\Payment\Gateway\Wechat\WechatGateway;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yansongda\Pay\Pay;

/**
 * @internal
 */
#[CoversClass(AlipayGateway::class)]
#[CoversClass(WechatGateway::class)]
final class ProviderConfigurationTest extends TestCase
{
    public function testAlipayOwnsItsProviderConfigurationMapping(): void
    {
        $executor = new RecordingGatewayExecutor(['h5_url' => 'https://pay.example/checkout']);
        $gateway = new AlipayGateway($executor);

        $result = $gateway->pay(new PaymentOrderEntity()->assign([
            'orderNo' => 'order-1',
            'amount' => 1250,
            'currencyCode' => 'CNY',
            'methodCode' => PaymentMethods::H5,
            'subject' => 'Order 1',
            'notifyUrl' => 'https://app.example/notify',
            'returnUrl' => 'https://app.example/return',
        ]), [
            'appId' => 'app-id',
            'appPrivateKey' => 'private-key',
            'alipayPublicKey' => 'public-key',
            'mode' => 'sandbox',
            'notifyUrl' => 'https://platform.example/notify',
            'returnUrl' => 'https://platform.example/return',
        ]);

        static::assertSame('alipay', $executor->provider);
        static::assertSame('h5', $executor->operation);
        static::assertSame([
            'app_id' => 'app-id',
            'app_secret_cert' => 'private-key',
            'alipay_public_cert_path' => 'public-key',
            'notify_url' => 'https://platform.example/notify',
            'return_url' => 'https://platform.example/return',
            'mode' => Pay::MODE_SANDBOX,
        ], $executor->config);
        static::assertSame('order-1', $executor->parameters['out_trade_no']);
        static::assertSame('12.50', $executor->parameters['total_amount']);
        static::assertSame(PaymentStatus::PENDING, $result->status);
    }

    public function testWechatOwnsItsProviderConfigurationMapping(): void
    {
        $executor = new RecordingGatewayExecutor(['code_url' => 'weixin://checkout']);
        $gateway = new WechatGateway($executor);

        $result = $gateway->pay(new PaymentOrderEntity()->assign([
            'orderNo' => 'order-2',
            'amount' => 3600,
            'currencyCode' => 'CNY',
            'methodCode' => PaymentMethods::NATIVE,
            'subject' => 'Order 2',
        ]), [
            'merchantId' => 'merchant-id',
            'merchantSecretKey' => 'secret-key',
            'merchantPrivateKey' => 'private-key',
            'merchantCertificate' => 'certificate',
            'appId' => 'app-id',
            'officialAccountAppId' => 'mp-app-id',
            'miniProgramAppId' => 'mini-app-id',
            'notifyUrl' => 'https://platform.example/notify',
        ]);

        static::assertSame('wechat', $executor->provider);
        static::assertSame('scan', $executor->operation);
        static::assertSame([
            'mch_id' => 'merchant-id',
            'mch_secret_key' => 'secret-key',
            'mch_secret_cert' => 'private-key',
            'mch_public_cert_path' => 'certificate',
            'app_id' => 'app-id',
            'mp_app_id' => 'mp-app-id',
            'mini_app_id' => 'mini-app-id',
            'notify_url' => 'https://platform.example/notify',
        ], $executor->config);
        static::assertSame('order-2', $executor->parameters['out_trade_no']);
        static::assertSame(['total' => 3600, 'currency' => 'CNY'], $executor->parameters['amount']);
        static::assertSame(PaymentStatus::PENDING, $result->status);
    }

    public function testWechatRefundDistinguishesRefundAmountFromOriginalTotal(): void
    {
        $executor = new RecordingGatewayExecutor(['refund_id' => 'refund-id']);
        $gateway = new WechatGateway($executor);

        $refund = new PaymentRefundEntity()->assign([
            'refundNo' => 'refund-1',
            'refundAmount' => 1200,
        ]);
        $order = new PaymentOrderEntity()->assign([
            'orderNo' => 'order-2',
            'amount' => 3600,
            'currencyCode' => 'CNY',
        ]);

        $gateway->refund($refund, $order, []);

        static::assertSame([
            'refund' => 1200,
            'total' => 3600,
            'currency' => 'CNY',
        ], $executor->parameters['amount']);
    }

    public function testProviderReceivesPlatformTransferNumber(): void
    {
        $executor = new RecordingGatewayExecutor(['transfer_bill_no' => 'provider-transfer']);
        $gateway = new WechatGateway($executor);
        $transfer = new PaymentTransferEntity()->assign([
            'transferNo' => 'platform-transfer-1',
            'externalTransferNo' => 'app-transfer-1',
            'amount' => 500,
            'payee' => 'openid-1',
            'payeeName' => 'Payee',
        ]);

        $gateway->transfer($transfer, []);

        static::assertSame('platform-transfer-1', $executor->parameters['out_bill_no']);
    }

    #[DataProvider('alipayQueryStatuses')]
    public function testAlipayQueryMapsProviderStatus(string $providerStatus, string $expectedStatus): void
    {
        $gateway = new AlipayGateway(new RecordingGatewayExecutor(['trade_status' => $providerStatus]));
        $order = new PaymentOrderEntity()->assign(['orderNo' => 'order-1', 'methodCode' => PaymentMethods::NATIVE]);

        static::assertSame($expectedStatus, $gateway->query($order, [])->status);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function alipayQueryStatuses(): iterable
    {
        yield 'completed trade succeeds' => ['TRADE_SUCCESS', PaymentStatus::SUCCEEDED];
        yield 'buyer payment remains pending' => ['WAIT_BUYER_PAY', PaymentStatus::PENDING];
        yield 'closed trade is closed' => ['TRADE_CLOSED', PaymentStatus::CLOSED];
        yield 'unrecognized status is unknown' => ['UNEXPECTED', PaymentStatus::UNKNOWN];
    }

    #[DataProvider('wechatQueryStatuses')]
    public function testWechatQueryMapsProviderStatus(string $providerStatus, string $expectedStatus): void
    {
        $gateway = new WechatGateway(new RecordingGatewayExecutor(['trade_state' => $providerStatus]));
        $order = new PaymentOrderEntity()->assign(['orderNo' => 'order-1', 'methodCode' => PaymentMethods::NATIVE]);

        static::assertSame($expectedStatus, $gateway->query($order, [])->status);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function wechatQueryStatuses(): iterable
    {
        yield 'successful trade succeeds' => ['SUCCESS', PaymentStatus::SUCCEEDED];
        yield 'user payment remains pending' => ['USERPAYING', PaymentStatus::PENDING];
        yield 'revoked trade is closed' => ['REVOKED', PaymentStatus::CLOSED];
        yield 'unrecognized status is unknown' => ['UNEXPECTED', PaymentStatus::UNKNOWN];
    }
}

/**
 * @internal
 */
final class RecordingGatewayExecutor implements GatewayExecutorInterface
{
    public ?string $provider = null;

    /**
     * @var array<string, mixed>
     */
    public array $config = [];

    public ?string $operation = null;

    /**
     * @var array<string, mixed>
     */
    public array $parameters = [];

    /**
     * @param array<string, mixed> $result
     */
    public function __construct(private readonly array $result)
    {
    }

    public function execute(string $provider, array $config, string $operation, array $parameters): mixed
    {
        $this->provider = $provider;
        $this->config = $config;
        $this->operation = $operation;
        $this->parameters = $parameters;

        return $this->result;
    }
}
