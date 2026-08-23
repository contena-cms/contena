<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\SystemConfig\Validation;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Contena\Core\Framework\Validation\DataValidator;
use Contena\Core\Framework\Validation\Exception\ConstraintViolationException;
use Contena\Core\System\SystemConfig\Service\ConfigurationService;
use Contena\Core\System\SystemConfig\Validation\SystemConfigValidator;

/**
 * @internal
 */
class SystemConfigValidatorTest extends TestCase
{
    use KernelTestBehaviour;

    /**
     * @param array<string, string|null> $input
     */
    #[DataProvider('validationProvider')]
    public function testValidation(array $input, bool $expectError): void
    {
        $configurationService = static::createStub(ConfigurationService::class);
        $configurationService->method('getConfiguration')->willReturn([
            [
                'elements' => [
                    [
                        'name' => 'example.config.requiredValue',
                        'config' => ['required' => true, 'maxLength' => 255],
                    ],
                ],
            ],
        ]);

        $validator = new SystemConfigValidator(
            $configurationService,
            self::getContainer()->get(DataValidator::class)
        );

        if ($expectError) {
            $this->expectException(ConstraintViolationException::class);
        }

        $validator->validate(['null' => $input], Context::createDefaultContext());

        $this->addToAssertionCount(1);
    }

    /**
     * @return \Generator<string, array{input: array<string, string|null>, expectError: bool}>
     */
    public static function validationProvider(): \Generator
    {
        yield 'required value is valid' => [
            'input' => ['example.config.requiredValue' => 'value'],
            'expectError' => false,
        ];

        yield 'required value rejects empty string' => [
            'input' => ['example.config.requiredValue' => ''],
            'expectError' => true,
        ];

        yield 'required value rejects null' => [
            'input' => ['example.config.requiredValue' => null],
            'expectError' => true,
        ];
    }
}
