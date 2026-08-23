<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\SystemConfig\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\System\SystemConfig\Command\ConfigSet;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\Test\TestDefaults;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[CoversClass(ConfigSet::class)]
class ConfigSetCommandTest extends TestCase
{
    private ConfigSet $configSetCommand;

    private SystemConfigService&MockObject $systemConfigService;

    protected function setUp(): void
    {
        $this->systemConfigService = $this->createMock(SystemConfigService::class);
        $this->configSetCommand = new ConfigSet($this->systemConfigService);
    }

    /**
     * @param array<string, mixed> $input
     */
    #[DataProvider('configSetProvider')]
    public function testConfigSet(array $input, string $expectedKey, mixed $expectedValue, ?string $expectedChannelId, bool $expectedSilent): void
    {
        $this->systemConfigService->expects($this->once())
            ->method('set')
            ->with($expectedKey, static::identicalTo($expectedValue), $expectedChannelId, $expectedSilent, static::isInstanceOf(Context::class));

        $commandTester = new CommandTester($this->configSetCommand);
        $commandTester->execute($input);
    }

    public static function configSetProvider(): \Generator
    {
        yield 'string false' => [
            'input' => ['key' => 'my.key', 'value' => 'false', '--channelId' => TestDefaults::CHANNEL],
            'expectedKey' => 'my.key',
            'expectedValue' => 'false',
            'expectedChannelId' => TestDefaults::CHANNEL,
            'expectedSilent' => false,
        ];

        yield 'json decoded false' => [
            'input' => ['key' => 'my.key', 'value' => 'false', '--json' => true, '--channelId' => TestDefaults::CHANNEL],
            'expectedKey' => 'my.key',
            'expectedValue' => false,
            'expectedChannelId' => TestDefaults::CHANNEL,
            'expectedSilent' => false,
        ];

        yield 'string int' => [
            'input' => ['key' => 'my.key', 'value' => '4'],
            'expectedKey' => 'my.key',
            'expectedValue' => '4',
            'expectedChannelId' => null,
            'expectedSilent' => false,
        ];

        yield 'json decoded int' => [
            'input' => ['key' => 'my.key', 'value' => '5', '--json' => true],
            'expectedKey' => 'my.key',
            'expectedValue' => 5,
            'expectedChannelId' => null,
            'expectedSilent' => false,
        ];

        yield 'string float' => [
            'input' => ['key' => 'my.key', 'value' => '2.2'],
            'expectedKey' => 'my.key',
            'expectedValue' => '2.2',
            'expectedChannelId' => null,
            'expectedSilent' => false,
        ];

        yield 'json decoded float' => [
            'input' => ['key' => 'my.key', 'value' => '3.3', '--json' => true],
            'expectedKey' => 'my.key',
            'expectedValue' => 3.3,
            'expectedChannelId' => null,
            'expectedSilent' => false,
        ];

        yield 'string json' => [
            'input' => ['key' => 'my.key', 'value' => '{"name":"abc","place":"xyz"}'],
            'expectedKey' => 'my.key',
            'expectedValue' => '{"name":"abc","place":"xyz"}',
            'expectedChannelId' => null,
            'expectedSilent' => false,
        ];

        yield 'json decoded object' => [
            'input' => ['key' => 'my.key', 'value' => '{"name":"abc","place":"xyz"}', '--json' => true],
            'expectedKey' => 'my.key',
            'expectedValue' => ['name' => 'abc', 'place' => 'xyz'],
            'expectedChannelId' => null,
            'expectedSilent' => false,
        ];

        yield 'json decoded non-json string remains string' => [
            'input' => ['key' => 'my.key', 'value' => 'random string', '--json' => true],
            'expectedKey' => 'my.key',
            'expectedValue' => 'random string',
            'expectedChannelId' => null,
            'expectedSilent' => false,
        ];

        yield 'silent flag' => [
            'input' => ['key' => 'my.key', 'value' => 'value', '--silent' => true],
            'expectedKey' => 'my.key',
            'expectedValue' => 'value',
            'expectedChannelId' => null,
            'expectedSilent' => true,
        ];
    }
}
