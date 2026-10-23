<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Gateway\Context\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Gateway\Context\Command\ChangeCurrencyCommand;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
#[CoversClass(ChangeCurrencyCommand::class)]
class ChangeCurrencyCommandTest extends TestCase
{
    public function testCommand(): void
    {
        $command = ChangeCurrencyCommand::createFromPayload(['iso' => 'EUR']);

        static::assertSame('context_change-currency', $command::getDefaultKeyName());
        static::assertSame('EUR', $command->iso);
    }
}
