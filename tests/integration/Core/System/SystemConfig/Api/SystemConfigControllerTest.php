<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\SystemConfig\Api;

use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Contena\Core\System\SystemConfig\Api\SystemConfigController;
use Contena\Core\System\SystemConfig\Service\ConfigurationService;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\System\SystemConfig\Validation\SystemConfigValidator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class SystemConfigControllerTest extends TestCase
{
    use KernelTestBehaviour;

    public function testBatchSaveConfigurationPersistsNestedConfigKeys(): void
    {
        $key = 'core.basicInformation.foo.bar.baz';
        $systemConfigService = static::getContainer()->get(SystemConfigService::class);

        $systemConfigService->delete($key);

        try {
            $response = $this->createController()->batchSaveConfiguration(
                new Request([], [
                    'null' => [$key => 'test-value'],
                ]),
                Context::createDefaultContext()
            );

            static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
            static::assertSame('test-value', $systemConfigService->get($key));
        } finally {
            $systemConfigService->delete($key);
        }
    }

    private function createController(): SystemConfigController
    {
        return new SystemConfigController(
            static::getContainer()->get(ConfigurationService::class),
            static::getContainer()->get(SystemConfigService::class),
            static::getContainer()->get(SystemConfigValidator::class)
        );
    }
}
