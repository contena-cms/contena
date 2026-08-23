<?php declare(strict_types=1);

namespace Contena\Tests\DevOps\Core\Test;

use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\DataAbstractionLayer\Command\DataAbstractionLayerValidateCommand;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
class DataAbstractionLayerValidateCommandTest extends TestCase
{
    use KernelTestBehaviour;

    public function testNoValidationErrors(): void
    {
        $commandTester = new CommandTester(static::getContainer()->get(DataAbstractionLayerValidateCommand::class));
        $commandTester->execute([]);

        static::assertSame(
            0,
            $commandTester->getStatusCode(),
            "\"bin/console dal:validate\" returned errors:\n" . $commandTester->getDisplay()
        );
    }
}
