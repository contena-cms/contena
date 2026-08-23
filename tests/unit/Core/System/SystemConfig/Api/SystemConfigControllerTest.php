<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\SystemConfig\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Validation\Exception\ConstraintViolationException;
use Contena\Core\System\SystemConfig\Api\SystemConfigController;
use Contena\Core\System\SystemConfig\Service\ConfigurationService;
use Contena\Core\System\SystemConfig\SystemConfigException;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\System\SystemConfig\Validation\SystemConfigValidator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(SystemConfigController::class)]
class SystemConfigControllerTest extends TestCase
{
    public function testCheckConfigurationWithEmptyDomainReturnsFalse(): void
    {
        $result = $this->createController()->checkConfiguration(new Request(), Context::createDefaultContext());

        static::assertSame('false', $result->getContent());
    }

    public function testCheckConfiguration(): void
    {
        $configurationService = static::createStub(ConfigurationService::class);
        $configurationService->method('checkConfiguration')->willReturn(true);

        $result = $this->createController(configurationService: $configurationService)->checkConfiguration(
            new Request(['domain' => 'foo']),
            Context::createDefaultContext()
        );

        static::assertSame('true', $result->getContent());
    }

    public function testGetConfigurationSchema(): void
    {
        $configurationService = static::createStub(ConfigurationService::class);
        $configurationService->method('getConfiguration')->willReturn(['foo' => 'bar']);

        $result = $this->createController(configurationService: $configurationService)->getConfiguration(
            new Request(['domain' => 'foo']),
            Context::createDefaultContext()
        );

        static::assertSame('{"foo":"bar"}', $result->getContent());
    }

    public function testGetConfigurationSchemaRequiresDomain(): void
    {
        $this->expectExceptionObject(SystemConfigException::missingRequestParameter('domain'));

        $this->createController()->getConfiguration(new Request(), Context::createDefaultContext());
    }

    public function testGetConfigurationValuesUsesGlobalDomain(): void
    {
        $systemConfig = $this->createMock(SystemConfigService::class);
        $systemConfig->expects($this->once())
            ->method('getDomain')
            ->with('example.config', null, false, static::isInstanceOf(Context::class))
            ->willReturn(['example.config.enabled' => true]);

        $request = new Request([
            'domain' => 'example.config',
        ]);

        $result = $this->createController(systemConfig: $systemConfig)->getConfigurationValues($request, Context::createDefaultContext());

        static::assertSame('{"example.config.enabled":true}', $result->getContent());
    }

    public function testGetConfigurationValuesRequiresDomain(): void
    {
        $this->expectExceptionObject(SystemConfigException::missingRequestParameter('domain'));

        $this->createController()->getConfigurationValues(new Request(), Context::createDefaultContext());
    }

    public function testGetConfigurationValuesReturnsObjectForEmptyConfiguration(): void
    {
        $systemConfig = static::createStub(SystemConfigService::class);
        $systemConfig->method('getDomain')->willReturn([]);

        $result = $this->createController(systemConfig: $systemConfig)
            ->getConfigurationValues(new Request(['domain' => 'example.config']), Context::createDefaultContext());

        static::assertSame('{}', $result->getContent());
    }

    #[DataProvider('saveConfigurationProvider')]
    public function testSaveConfiguration(Request $request, ?string $expectedChannelId, bool $expectedSilent): void
    {
        $systemConfig = $this->createMock(SystemConfigService::class);
        $systemConfig->expects($this->once())
            ->method('setMultiple')
            ->with(['foo' => '1'], $expectedChannelId, $expectedSilent, static::isInstanceOf(Context::class));

        $result = $this->createController(systemConfig: $systemConfig)->saveConfiguration($request, Context::createDefaultContext());

        static::assertSame(Response::HTTP_NO_CONTENT, $result->getStatusCode());
    }

    public static function saveConfigurationProvider(): \Generator
    {
        yield 'without silent' => [
            new Request([], ['foo' => '1']),
            null,
            true,
        ];

        yield 'with silent' => [
            new Request(['silent' => '1'], ['foo' => '1']),
            null,
            true,
        ];

        yield 'with explicit non-silent' => [
            new Request(['silent' => '0'], ['foo' => '1']),
            null,
            false,
        ];

        yield 'with channel' => [
            new Request(['channelId' => 'channel-id'], ['foo' => '1']),
            'channel-id',
            true,
        ];
    }

    public function testBatchSaveValidatesAndSavesValues(): void
    {
        $values = ['example.config.enabled' => true];
        $payload = ['null' => $values];

        $validator = $this->createMock(SystemConfigValidator::class);
        $validator->expects($this->once())->method('validate')->with($payload, static::isInstanceOf(Context::class));

        $systemConfig = $this->createMock(SystemConfigService::class);
        $systemConfig->expects($this->once())
            ->method('setMultiple')
            ->with($values, null, true, static::isInstanceOf(Context::class));

        $result = $this->createController(systemConfig: $systemConfig, validator: $validator)
            ->batchSaveConfiguration(new Request([], $payload), Context::createDefaultContext());

        static::assertSame('{}', $result->getContent());
    }

    public function testBatchValidationFailureIsForwarded(): void
    {
        $validator = static::createStub(SystemConfigValidator::class);
        $validator->method('validate')->willThrowException(static::createStub(ConstraintViolationException::class));

        $this->expectException(ConstraintViolationException::class);

        $this->createController(validator: $validator)->batchSaveConfiguration(
            new Request([], []),
            Context::createDefaultContext()
        );
    }

    #[DataProvider('inheritRequestDataProvider')]
    public function testInheritFlag(Request $request, bool $expectedFlag): void
    {
        $systemConfig = $this->createMock(SystemConfigService::class);
        $systemConfig->expects($this->once())
            ->method('getDomain')
            ->with('dummy domain', 'dummy channel', $expectedFlag, static::isInstanceOf(Context::class));

        $this->createController(systemConfig: $systemConfig)->getConfigurationValues($request, Context::createDefaultContext());
    }

    public static function inheritRequestDataProvider(): \Generator
    {
        yield 'inherit flag not set' => [
            new Request([
                'domain' => 'dummy domain',
                'channelId' => 'dummy channel',
            ]),
            false,
        ];

        yield 'inherit flag set to false' => [
            new Request([
                'domain' => 'dummy domain',
                'channelId' => 'dummy channel',
                'inherit' => false,
            ]),
            false,
        ];

        yield 'inherit flag set to true' => [
            new Request([
                'domain' => 'dummy domain',
                'channelId' => 'dummy channel',
                'inherit' => true,
            ]),
            true,
        ];
    }

    private function createController(
        ?ConfigurationService $configurationService = null,
        ?SystemConfigService $systemConfig = null,
        ?SystemConfigValidator $validator = null,
    ): SystemConfigController {
        return new SystemConfigController(
            $configurationService ?? static::createStub(ConfigurationService::class),
            $systemConfig ?? static::createStub(SystemConfigService::class),
            $validator ?? static::createStub(SystemConfigValidator::class)
        );
    }
}
