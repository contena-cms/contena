<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Payment\Struct;

use Contena\Core\System\Payment\Gateway\PaymentStatus;
use Contena\Core\System\Payment\Struct\PaymentResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(PaymentResult::class)]
final class PaymentResultTest extends TestCase
{
    public function testPersistedResultCanBeRestoredWithResourceReferences(): void
    {
        $result = new PaymentResult(
            PaymentStatus::PENDING,
            PaymentResult::ACTION_REDIRECT,
            'https://pay.example/checkout',
            'request-1',
            'trade-1',
            'WAIT_PAY',
            'Waiting for payment',
            ['provider' => 'value'],
        );

        $restored = PaymentResult::fromArray($result->toArray())
            ->withResource('P202609030001', 'app-order-1', 'T202609030001');

        static::assertEquals($result, PaymentResult::fromArray($result->toArray()));
        static::assertSame('P202609030001', $restored->resourceNo);
        static::assertSame('app-order-1', $restored->externalResourceNo);
        static::assertSame('T202609030001', $restored->transactionNo);
    }

    public function testMissingOrInvalidStoredValuesUseSafeDefaults(): void
    {
        $result = PaymentResult::fromArray([
            'status' => 1,
            'action' => '',
            'data' => 'invalid',
        ], PaymentStatus::PROCESSING);

        static::assertSame(PaymentStatus::PROCESSING, $result->status);
        static::assertSame(PaymentResult::ACTION_NONE, $result->action);
        static::assertSame([], $result->data);
    }
}
