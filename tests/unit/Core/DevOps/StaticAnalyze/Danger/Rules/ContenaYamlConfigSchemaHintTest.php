<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Rules;

use Danger\Context;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Contena\Core\DevOps\StaticAnalyze\Danger\Rules\ContenaYamlConfigSchemaHint;
use Contena\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Stub\StubFile;
use Contena\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Stub\StubPlatform;
use Contena\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Stub\StubPullRequest;

/**
 * @internal
 */
#[CoversClass(ContenaYamlConfigSchemaHint::class)]
class ContenaYamlConfigSchemaHintTest extends TestCase
{
    /**
     * @param list<string> $touchedFiles
     */
    #[TestDox('Warns when contena.yaml changes without a config-schema.json change')]
    #[DataProvider('touchedFilesProvider')]
    public function testConfigSchemaSync(array $touchedFiles, bool $expectWarning): void
    {
        $files = array_map(static fn (string $name): StubFile => new StubFile($name), $touchedFiles);
        $context = new Context(new StubPlatform(new StubPullRequest($files)));

        (new ContenaYamlConfigSchemaHint())($context);

        static::assertSame($expectWarning, $context->hasWarnings());
        if ($expectWarning) {
            static::assertStringContainsString('config-schema.json', $context->getWarnings()[0]);
        }
    }

    public static function touchedFilesProvider(): \Generator
    {
        yield 'contena.yaml without schema update warns' => [['config/packages/contena.yaml'], true];
        yield 'contena.yaml with schema update passes' => [['config/packages/contena.yaml', 'config-schema.json'], false];
        yield 'schema-only change passes' => [['config-schema.json'], false];
        yield 'unrelated yaml change passes' => [['config/packages/framework.yaml'], false];
    }
}
