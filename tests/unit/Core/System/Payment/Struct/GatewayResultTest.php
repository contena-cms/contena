<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Payment\Struct;

use Contena\Core\System\Payment\Gateway\PaymentStatus;
use Contena\Core\System\Payment\Struct\GatewayResponse;
use Contena\Core\System\Payment\Struct\GatewayResult;
use Contena\Core\System\Payment\Struct\PaymentAction;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(GatewayResult::class)]
final class GatewayResultTest extends TestCase
{
    public function testPersistedGatewayResultCanBeRestored(): void
    {
        $result = new GatewayResult(
            PaymentStatus::PENDING,
            new PaymentAction(PaymentAction::REDIRECT, 'https://pay.example/checkout'),
            new GatewayResponse('request-1', 'trade-1', 'WAIT_PAY', 'Waiting for payment', ['provider' => 'value']),
        );

        static::assertEquals($result, GatewayResult::fromArray($result->toArray()));
    }

    public function testMissingOrInvalidStoredValuesUseSafeDefaults(): void
    {
        $result = GatewayResult::fromArray([
            'status' => 1,
            'action' => '',
            'data' => 'invalid',
        ], PaymentStatus::PROCESSING);

        static::assertSame(PaymentStatus::PROCESSING, $result->status);
        static::assertNull($result->action);
        static::assertSame([], $result->response->data);
    }
}
