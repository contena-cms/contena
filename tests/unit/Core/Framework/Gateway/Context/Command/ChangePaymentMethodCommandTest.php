<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Gateway\Context\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Gateway\Context\Command\ChangePaymentMethodCommand;

/**
 * @internal
 */
#[CoversClass(ChangePaymentMethodCommand::class)]
class ChangePaymentMethodCommandTest extends TestCase
{
    public function testCommand(): void
    {
        $command = ChangePaymentMethodCommand::createFromPayload(['technicalName' => 'app_test_payment']);

        static::assertSame('context_change-payment-method', $command::getDefaultKeyName());
        static::assertSame('app_test_payment', $command->technicalName);
    }
}
