<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Payment\Gateway;

use Contena\Core\System\Payment\Gateway\PaymentStatus;
use Contena\Core\System\Payment\Gateway\ProviderResultMapper;
use Contena\Core\System\Payment\Struct\PaymentResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ProviderResultMapper::class)]
final class ProviderResultMapperTest extends TestCase
{
    public function testMapsTransferReferencesAndClientAction(): void
    {
        $data = [
            'out_bill_no' => 'platform-transfer-1',
            'transfer_bill_no' => 'provider-transfer-1',
            'prepay_id' => 'prepay-1',
            'result_code' => 'SUCCESS',
            'message' => 'Accepted',
        ];

        $result = ProviderResultMapper::paymentResult($data, PaymentStatus::PROCESSING);

        static::assertSame('platform-transfer-1', $result->providerRequestId);
        static::assertSame('provider-transfer-1', $result->providerResourceId);
        static::assertSame('SUCCESS', $result->resultCode);
        static::assertSame('Accepted', $result->resultMessage);
        static::assertSame(PaymentResult::ACTION_CLIENT, $result->action);
        static::assertSame(json_encode($data, \JSON_THROW_ON_ERROR), $result->actionValue);
    }

    public function testMapsAgreementResourceNumber(): void
    {
        $result = ProviderResultMapper::paymentResult([
            'external_agreement_no' => 'platform-agreement-1',
            'agreement_no' => 'provider-agreement-1',
        ], PaymentStatus::SUCCEEDED);

        static::assertSame('platform-agreement-1', $result->providerRequestId);
        static::assertSame('provider-agreement-1', $result->providerResourceId);
    }
}
