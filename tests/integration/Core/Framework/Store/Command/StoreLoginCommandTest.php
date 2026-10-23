<?php

declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\Store\Command;

use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Store\Command\StoreLoginCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validation;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
class StoreLoginCommandTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testEmptyPasswordOption(): void
    {
        $commandTester = new CommandTester(static::getContainer()->get(StoreLoginCommand::class));

        $violations = Validation::createValidator()->validate('', new NotBlank(message: 'The password cannot be empty'));
        $this->expectExceptionObject(new ValidationFailedException('', $violations));

        $commandTester->setInputs(['', '', '']);
        $commandTester->execute([
            '--contenaId' => 'no-reply@contena.cn',
            '--admin_user' => 'missing_user',
        ]);
    }

    public function testValidPasswordOptionInvalidUserOption(): void
    {
        $commandTester = new CommandTester(static::getContainer()->get(StoreLoginCommand::class));

        $commandTester->setInputs(['non-empty-password']);
        $commandTester->execute([
            '--contenaId' => 'no-reply@contena.cn',
            '--admin_user' => 'missing_user',
        ]);

        static::assertSame(Command::FAILURE, $commandTester->getStatusCode());
        static::assertStringContainsString('AdminUser not found', $commandTester->getDisplay());
    }
}
