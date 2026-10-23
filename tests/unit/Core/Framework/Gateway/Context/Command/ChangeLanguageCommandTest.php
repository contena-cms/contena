<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Gateway\Context\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Gateway\Context\Command\ChangeLanguageCommand;

/**
 * @internal
 */
#[CoversClass(ChangeLanguageCommand::class)]
class ChangeLanguageCommandTest extends TestCase
{
    public function testCommand(): void
    {
        $command = ChangeLanguageCommand::createFromPayload(['iso' => 'de-DE']);

        static::assertSame('context_change-language', $command::getDefaultKeyName());
        static::assertSame('de-DE', $command->iso);
    }
}
