<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\DataAbstractionLayer\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\DataAbstractionLayer\Command\DataAbstractionLayerValidateCommand;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionValidator;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[CoversClass(DataAbstractionLayerValidateCommand::class)]
class DataAbstractionLayerValidateCommandTest extends TestCase
{
    use KernelTestBehaviour;

    public function testValidationErrors(): void
    {
        $validator = static::createStub(DefinitionValidator::class);
        $validator->method('validate')->willReturn([
            'Contena\\Core\\Content\\Product\\ProductDefinition' => ['Error 1', 'Error 2'],
            'Contena\\Core\\Content\\Category\\CategoryDefinition' => ['Error 3'],
        ]);
        $command = new DataAbstractionLayerValidateCommand($validator);
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        static::assertSame(1, $commandTester->getStatusCode());
        static::assertStringContainsString('Found 3 errors in 2 entities', $commandTester->getDisplay());
        static::assertStringContainsString('ProductDefinition', $commandTester->getDisplay());
        static::assertStringContainsString('CategoryDefinition', $commandTester->getDisplay());
        static::assertStringContainsString('Error 1', $commandTester->getDisplay());
        static::assertStringContainsString('Error 3', $commandTester->getDisplay());
    }

    public function testFormatJsonOutput(): void
    {
        $validator = static::createStub(DefinitionValidator::class);
        $validator->method('validate')->willReturn([
            'Contena\\Core\\Content\\Product\\ProductDefinition' => ['Error 1'],
        ]);
        $command = new DataAbstractionLayerValidateCommand($validator);
        $commandTester = new CommandTester($command);
        $commandTester->execute(['--format' => 'json']);

        static::assertSame(1, $commandTester->getStatusCode());
        static::assertStringContainsString('ProductDefinition', $commandTester->getDisplay());
        static::assertStringContainsString('Error 1', $commandTester->getDisplay());
        static::assertJson($commandTester->getDisplay());
    }

    public function testTolerateForeignKeyOptionIsPassedToValidator(): void
    {
        $validator = $this->createMock(DefinitionValidator::class);
        $validator->expects($this->once())
            ->method('validate')
            ->with(['fk.first', 'fk.second'])
            ->willReturn([]);
        $command = new DataAbstractionLayerValidateCommand($validator);
        $commandTester = new CommandTester($command);
        $commandTester->execute(['--tolerate-foreign-key' => ['fk.first', 'fk.second']]);

        static::assertSame(0, $commandTester->getStatusCode());
        static::assertStringContainsString('No errors found', $commandTester->getDisplay());
    }

    public function testNamespaceFilter(): void
    {
        $validator = static::createStub(DefinitionValidator::class);
        $validator->method('validate')->willReturn([
            'Contena\\Core\\Content\\Product\\ProductDefinition' => ['Error 1'],
            'Contena\\Core\\Content\\Category\\CategoryDefinition' => ['Error 2'],
            'Other\\Namespace\\Foo' => ['Error 3'],
        ]);
        $command = new DataAbstractionLayerValidateCommand($validator);
        $commandTester = new CommandTester($command);
        $commandTester->execute(['--namespaces' => ['Contena\\Core\\Content\\Product']]);

        static::assertSame(1, $commandTester->getStatusCode());
        static::assertStringContainsString('ProductDefinition', $commandTester->getDisplay());
        static::assertStringContainsString('Error 1', $commandTester->getDisplay());
        static::assertStringNotContainsString('CategoryDefinition', $commandTester->getDisplay());
        static::assertStringNotContainsString('Error 2', $commandTester->getDisplay());
        static::assertStringNotContainsString('Error 3', $commandTester->getDisplay());
    }

    public function testNamespaceFilterWithPartialNamespace(): void
    {
        $validator = static::createStub(DefinitionValidator::class);
        $validator->method('validate')->willReturn([
            'Contena\\Core\\Content\\Product\\ProductDefinition' => ['Error 1'],
            'Contena\\Core\\Content\\Category\\CategoryDefinition' => ['Error 2'],
            'Other\\Namespace\\Foo' => ['Error 3'],
        ]);
        $command = new DataAbstractionLayerValidateCommand($validator);
        $commandTester = new CommandTester($command);
        $commandTester->execute(['--namespaces' => ['Contena\\Core']]);

        static::assertSame(1, $commandTester->getStatusCode());
        static::assertStringContainsString('ProductDefinition', $commandTester->getDisplay());
        static::assertStringContainsString('CategoryDefinition', $commandTester->getDisplay());
        static::assertStringContainsString('Error 1', $commandTester->getDisplay());
        static::assertStringContainsString('Error 2', $commandTester->getDisplay());
        static::assertStringNotContainsString('Error 3', $commandTester->getDisplay());
        static::assertStringNotContainsString('Foo', $commandTester->getDisplay());
    }

    public function testNamespaceFilterWithMultipleNamespaces(): void
    {
        $validator = static::createStub(DefinitionValidator::class);
        $validator->method('validate')->willReturn([
            'Contena\\Core\\Content\\Product\\ProductDefinition' => ['Error 1'],
            'Contena\\Core\\Content\\Category\\CategoryDefinition' => ['Error 2'],
            'Other\\Namespace\\Foo' => ['Error 3'],
            'Another\\Namespace\\Bar' => ['Error 4'],
        ]);
        $command = new DataAbstractionLayerValidateCommand($validator);
        $commandTester = new CommandTester($command);
        $commandTester->execute(['--namespaces' => ['Contena\\Core\\Content\\Product', 'Other\\Namespace']]);

        static::assertSame(1, $commandTester->getStatusCode());
        static::assertStringContainsString('ProductDefinition', $commandTester->getDisplay());
        static::assertStringContainsString('Error 1', $commandTester->getDisplay());
        static::assertStringContainsString('Foo', $commandTester->getDisplay());
        static::assertStringContainsString('Error 3', $commandTester->getDisplay());
        static::assertStringNotContainsString('CategoryDefinition', $commandTester->getDisplay());
        static::assertStringNotContainsString('Error 2', $commandTester->getDisplay());
        static::assertStringNotContainsString('Bar', $commandTester->getDisplay());
        static::assertStringNotContainsString('Error 4', $commandTester->getDisplay());
    }
}
