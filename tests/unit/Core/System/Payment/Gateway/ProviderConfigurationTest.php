<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Payment\Gateway;

use Contena\Core\System\Payment\DataAbstractionLayer\PaymentChannelMethod\PaymentMethods;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentChannelNotifyRecord\PaymentNotificationTypes;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\PaymentOrderEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentRefund\PaymentRefundEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentTransfer\PaymentTransferEntity;
use Contena\Core\System\Payment\Gateway\Alipay\AlipayGateway;
use Contena\Core\System\Payment\Gateway\PaymentStatus;
use Contena\Core\System\Payment\Gateway\Wechat\WechatGateway;
use Contena\Core\System\Payment\Gateway\YansongdaPayClientInterface;
use Contena\Core\System\Payment\Notification\Struct\GatewayNotification;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yansongda\Pay\Pay;
use Yansongda\Pay\Plugin\Wechat\V3\Marketing\MchTransfer\CreatePlugin;
use Yansongda\Pay\Shortcut\Wechat\TransferShortcut;

/**
 * @internal
 */
#[CoversClass(AlipayGateway::class)]
#[CoversClass(WechatGateway::class)]
final class ProviderConfigurationTest extends TestCase
{
    public function testAlipayOwnsItsProviderConfigurationMapping(): void
    {
        $executor = new RecordingYansongdaPayClient(['h5_url' => 'https://pay.example/checkout']);
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
        static::assertArrayNotHasKey('_notify_url', $executor->parameters);
        static::assertSame('https://app.example/return', $executor->parameters['_return_url']);
        static::assertSame(PaymentStatus::PENDING, $result->status);
    }

    public function testWechatOwnsItsProviderConfigurationMapping(): void
    {
        $executor = new RecordingYansongdaPayClient(['code_url' => 'weixin://checkout']);
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
        static::assertArrayNotHasKey('notify_url', $executor->parameters);
        static::assertSame(PaymentStatus::PENDING, $result->status);
    }

    public function testWechatRefundDistinguishesRefundAmountFromOriginalTotal(): void
    {
        $executor = new RecordingYansongdaPayClient(['refund_id' => 'refund-id']);
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

    public function testWechatRefundMapsRefundReferencesWhenOrderReferenceIsAlsoPresent(): void
    {
        $executor = new RecordingYansongdaPayClient([
            'out_trade_no' => 'order-2',
            'out_refund_no' => 'refund-1',
            'refund_id' => 'provider-refund-1',
        ]);
        $gateway = new WechatGateway($executor);

        $result = $gateway->refund(
            new PaymentRefundEntity()->assign(['refundNo' => 'refund-1', 'refundAmount' => 1200]),
            new PaymentOrderEntity()->assign(['orderNo' => 'order-2', 'amount' => 3600, 'currencyCode' => 'CNY']),
            [],
        );

        static::assertSame('refund-1', $result->providerRequestId);
        static::assertSame('provider-refund-1', $result->providerResourceId);
    }

    public function testProviderReceivesPlatformTransferNumber(): void
    {
        $executor = new RecordingYansongdaPayClient(['transfer_bill_no' => 'provider-transfer']);
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
        static::assertSame('mch_transfer', $executor->parameters['_action']);
        static::assertContains(CreatePlugin::class, new TransferShortcut()->getPlugins($executor->parameters));
    }

    /**
     * @param array<string, mixed> $response
     */
    #[DataProvider('unconfirmedResponses')]
    public function testUnconfirmedResponsesNeverReleaseRefundReservation(array $response): void
    {
        $refund = new PaymentRefundEntity()->assign(['refundNo' => 'refund-1', 'refundAmount' => 100]);
        $order = new PaymentOrderEntity()->assign(['orderNo' => 'order-1', 'amount' => 1000, 'currencyCode' => 'CNY']);
        $transfer = new PaymentTransferEntity()->assign(['transferNo' => 'transfer-1', 'amount' => 100, 'payee' => 'payee', 'payeeName' => 'Payee']);
        $client = new RecordingYansongdaPayClient($response);

        foreach ([new AlipayGateway($client), new WechatGateway($client)] as $gateway) {
            static::assertSame(PaymentStatus::UNKNOWN, $gateway->refund($refund, $order, [])->status);
            static::assertSame(PaymentStatus::UNKNOWN, $gateway->transfer($transfer, [])->status);
        }
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function unconfirmedResponses(): iterable
    {
        yield 'empty response' => [[]];
        yield 'normalized null SDK response' => [['value' => null]];
        yield 'unexpected response shape' => [['unexpected' => 'value']];
        yield 'provider system error is not a business rejection' => [['code' => '20000']];
    }

    public function testAlipayDistinguishesAcceptedTransferFromFinalSuccess(): void
    {
        $transfer = new PaymentTransferEntity()->assign(['transferNo' => 'transfer-1', 'amount' => 100, 'payee' => 'payee', 'payeeName' => 'Payee']);

        static::assertSame(PaymentStatus::PROCESSING, new AlipayGateway(new RecordingYansongdaPayClient(['code' => '10000', 'status' => 'DEALING']))->transfer($transfer, [])->status);
        static::assertSame(PaymentStatus::SUCCEEDED, new AlipayGateway(new RecordingYansongdaPayClient(['code' => '10000', 'status' => 'SUCCESS']))->transfer($transfer, [])->status);
        static::assertSame(PaymentStatus::FAILED, new AlipayGateway(new RecordingYansongdaPayClient(['code' => '40004']))->transfer($transfer, [])->status);
    }

    public function testWechatRefundRequiresAnExplicitOutcome(): void
    {
        $refund = new PaymentRefundEntity()->assign(['refundNo' => 'refund-1', 'refundAmount' => 100]);
        $order = new PaymentOrderEntity()->assign(['orderNo' => 'order-1', 'amount' => 1000, 'currencyCode' => 'CNY']);

        static::assertSame(PaymentStatus::SUCCEEDED, new WechatGateway(new RecordingYansongdaPayClient(['status' => 'SUCCESS']))->refund($refund, $order, [])->status);
        static::assertSame(PaymentStatus::FAILED, new WechatGateway(new RecordingYansongdaPayClient(['status' => 'CLOSED']))->refund($refund, $order, [])->status);
        static::assertSame(PaymentStatus::UNKNOWN, new WechatGateway(new RecordingYansongdaPayClient(['status' => 'ABNORMAL']))->refund($refund, $order, [])->status);
    }

    public function testAbnormalRefundCallbackDoesNotInheritTheOriginalPaymentSuccess(): void
    {
        $gateway = new WechatGateway(new RecordingYansongdaPayClient([
            'out_refund_no' => 'refund-1',
            'refund_status' => 'ABNORMAL',
            'trade_state' => 'SUCCESS',
        ]));

        static::assertSame(PaymentStatus::UNKNOWN, $gateway->handleNotification(new GatewayNotification(''), [])->result->status);
    }

    public function testAlipayPreservesEveryCentWithoutFloatingPointArithmetic(): void
    {
        $client = new RecordingYansongdaPayClient([]);
        $gateway = new AlipayGateway($client);
        $order = new PaymentOrderEntity()->assign(['orderNo' => 'order-1', 'amount' => \PHP_INT_MAX, 'methodCode' => PaymentMethods::H5, 'subject' => 'Large amount']);

        $gateway->pay($order, []);
        static::assertSame('92233720368547758.07', $client->parameters['total_amount']);
        $gateway->refund(new PaymentRefundEntity()->assign(['refundNo' => 'refund-1', 'refundAmount' => 1]), $order, []);
        static::assertSame('0.01', $client->parameters['refund_amount']);
        $gateway->transfer(new PaymentTransferEntity()->assign(['transferNo' => 'transfer-1', 'amount' => 101, 'payee' => 'payee', 'payeeName' => 'Payee']), []);
        static::assertSame('1.01', $client->parameters['trans_amount']);
    }

    #[DataProvider('alipayQueryStatuses')]
    public function testAlipayQueryMapsProviderStatus(string $providerStatus, string $expectedStatus): void
    {
        $gateway = new AlipayGateway(new RecordingYansongdaPayClient(['trade_status' => $providerStatus]));
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
        $gateway = new WechatGateway(new RecordingYansongdaPayClient(['trade_state' => $providerStatus]));
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

    public function testAlipayNotificationIsVerifiedAndMappedByTheGateway(): void
    {
        $executor = new RecordingYansongdaPayClient([
            'out_trade_no' => 'platform-order-1',
            'trade_no' => 'provider-order-1',
            'trade_status' => 'TRADE_SUCCESS',
        ]);
        $gateway = new AlipayGateway($executor);

        $notification = $gateway->handleNotification(new GatewayNotification('', parameters: ['sign' => 'signature']), []);

        static::assertSame('callback', $executor->operation);
        static::assertSame(['sign' => 'signature'], $executor->parameters);
        static::assertSame(PaymentNotificationTypes::PAYMENT, $notification->type);
        static::assertSame('platform-order-1', $notification->resourceNo);
        static::assertSame(PaymentStatus::SUCCEEDED, $notification->result->status);
        static::assertSame('success', $notification->responseBody);
    }

    public function testWechatNotificationKeepsRawBodyAndHeadersForSignatureVerification(): void
    {
        $executor = new RecordingYansongdaPayClient([
            'out_refund_no' => 'platform-refund-1',
            'refund_id' => 'provider-refund-1',
            'refund_status' => 'SUCCESS',
        ]);
        $gateway = new WechatGateway($executor);
        $headers = ['Wechatpay-Signature' => 'signature'];

        $notification = $gateway->handleNotification(new GatewayNotification('{"event_type":"REFUND.SUCCESS"}', $headers), []);

        static::assertSame('callback', $executor->operation);
        static::assertSame([
            'body' => '{"event_type":"REFUND.SUCCESS"}',
            'headers' => $headers,
        ], $executor->parameters);
        static::assertSame(PaymentNotificationTypes::REFUND, $notification->type);
        static::assertSame('platform-refund-1', $notification->resourceNo);
        static::assertSame(PaymentStatus::SUCCEEDED, $notification->result->status);
        static::assertSame('application/json', $notification->responseContentType);
    }
}

/**
 * @internal
 */
final class RecordingYansongdaPayClient implements YansongdaPayClientInterface
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

    public function request(string $provider, array $config, string $operation, array $parameters): array
    {
        $this->provider = $provider;
        $this->config = $config;
        $this->operation = $operation;
        $this->parameters = $parameters;

        return $this->result;
    }
}
