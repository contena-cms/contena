<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Store\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Store\Api\ExtensionStoreDataController;
use Contena\Core\Framework\Store\Services\AbstractExtensionDataProvider;

/**
 * @internal
 */
#[CoversClass(ExtensionStoreDataController::class)]
class ExtensionStoreDataControllerTest extends TestCase
{
    public function testInstalledExtensionsAreReturnedFromTheNativeProvider(): void
    {
        $context = Context::createDefaultContext();
        $extensions = [['name' => 'ExamplePlugin', 'type' => 'plugin']];

        $provider = $this->createMock(AbstractExtensionDataProvider::class);
        $provider->expects($this->once())->method('getInstalledExtensions')->with($context)->willReturn($extensions);

        $controller = new ExtensionStoreDataController(
            $provider,
            static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class),
        );

        $response = $controller->getInstalledExtensions($context);

        static::assertSame($extensions, json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR));
    }
}
