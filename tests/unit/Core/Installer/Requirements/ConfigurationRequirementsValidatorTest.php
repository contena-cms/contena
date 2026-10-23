<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Installer\Requirements;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Installer\Requirements\ConfigurationRequirementsValidator;
use Contena\Core\Installer\Requirements\IniConfigReader;
use Contena\Core\Installer\Requirements\Struct\RequirementsCheckCollection;
use Contena\Core\Installer\Requirements\Struct\SystemCheck;

/**
 * @internal
 */
#[CoversClass(ConfigurationRequirementsValidator::class)]
class ConfigurationRequirementsValidatorTest extends TestCase
{
    private Stub&IniConfigReader $configReader;

    private ConfigurationRequirementsValidator $validator;

    protected function setUp(): void
    {
        $this->configReader = static::createStub(IniConfigReader::class);
        $this->validator = new ConfigurationRequirementsValidator($this->configReader);
    }

    /**
     * @param array<string, string> $iniValues
     * @param SystemCheck[] $expectedChecks
     */
    #[DataProvider('configRequirements')]
    public function testValidateRequirements(array $iniValues, array $expectedChecks): void
    {
        $this->configReader->method('get')->willReturnCallback(
            static fn ($arg) => $iniValues[$arg] ?? ''
        );

        $checks = $this->validator->validateRequirements(new RequirementsCheckCollection());

        static::assertCount(\count($expectedChecks), $checks);
        foreach ($expectedChecks as $index => $expected) {
            /** @var SystemCheck $check */
            $check = $checks->get($index);
            static::assertSame($expected->getStatus(), $check->getStatus());
            static::assertSame($expected->getName(), $check->getName());
            static::assertSame($expected->getRequiredValue(), $check->getRequiredValue());
            static::assertSame($expected->getInstalledValue(), $check->getInstalledValue());
        }
    }

    public static function configRequirements(): \Generator
    {
        yield 'all checks pass with minimum requirements' => [
            [
                'max_execution_time' => '30',
                'memory_limit' => '512M',
                'opcache.memory_consumption' => '256',
            ],
            [
            ],
        ];

        yield 'all checks pass with higher configs' => [
            [
                'max_execution_time' => '60',
                'memory_limit' => '1024M',
                'opcache.memory_consumption' => '512',
            ],
            [
            ],
        ];

        yield 'all checks fail with lower configs' => [
            [
                'max_execution_time' => '29',
                'memory_limit' => '511M',
                'opcache.memory_consumption' => '255',
            ],
            [
            ],
        ];

        yield 'one check fails with lower configs' => [
            [
                'max_execution_time' => '29',
                'memory_limit' => '512M',
                'opcache.memory_consumption' => '256',
            ],
            [
            ],
        ];

        yield 'Opcache is not configured' => [
            [
                'max_execution_time' => '30',
                'memory_limit' => '512M',
                'opcache.memory_consumption' => '',
            ],
            [
            ],
        ];

        yield 'Checks pass with unlimited values' => [
            [
                'max_execution_time' => '0',
                'memory_limit' => '-1',
                'opcache.memory_consumption' => '',
            ],
            [
            ],
        ];
    }
}
