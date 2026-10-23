<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Gateway\Context\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Gateway\Context\Command\ChangeBillingAddressCommand;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
#[CoversClass(ChangeBillingAddressCommand::class)]
class ChangeBillingAddressCommandTest extends TestCase
{
    public function testCommand(): void
    {
        $command = ChangeBillingAddressCommand::createFromPayload(['addressId' => '1234']);

        static::assertSame('context_change-billing-address', $command::getDefaultKeyName());
        static::assertSame('1234', $command->addressId);
    }
}
