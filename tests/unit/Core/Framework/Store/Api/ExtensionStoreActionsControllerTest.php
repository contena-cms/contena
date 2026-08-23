<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Store\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Plugin\PluginManagementService;
use Contena\Core\Framework\Plugin\PluginService;
use Contena\Core\Framework\Store\Api\ExtensionStoreActionsController;
use Contena\Core\Framework\Store\Services\AbstractExtensionLifecycle;
use Contena\Core\Framework\Store\StoreException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(ExtensionStoreActionsController::class)]
class ExtensionStoreActionsControllerTest extends TestCase
{
    public function testRefreshExtensions(): void
    {
        $pluginService = $this->createMock(PluginService::class);
        $pluginService->expects($this->once())->method('refreshPlugins');

        $response = $this->createController(pluginService: $pluginService)->refreshExtensions(Context::createDefaultContext());

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    public function testRefreshExtensionsDoesNothingWhenRuntimeManagementIsDisabled(): void
    {
        $pluginService = $this->createMock(PluginService::class);
        $pluginService->expects($this->never())->method('refreshPlugins');

        $response = $this->createController(pluginService: $pluginService, runtimeExtensionManagementAllowed: false)
            ->refreshExtensions(Context::createDefaultContext());

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    public function testLifecycleActionsDelegateToNativeExtensionLifecycle(): void
    {
        $context = Context::createDefaultContext();
        $lifecycle = $this->createMock(AbstractExtensionLifecycle::class);
        $lifecycle->expects($this->once())->method('install')->with('plugin', 'ExamplePlugin', $context);
        $lifecycle->expects($this->once())->method('activate')->with('plugin', 'ExamplePlugin', $context);
        $lifecycle->expects($this->once())->method('deactivate')->with('plugin', 'ExamplePlugin', $context);

        $controller = $this->createController(lifecycle: $lifecycle);

        static::assertSame(Response::HTTP_NO_CONTENT, $controller->installExtension('plugin', 'ExamplePlugin', $context)->getStatusCode());
        static::assertSame(Response::HTTP_NO_CONTENT, $controller->activateExtension('plugin', 'ExamplePlugin', $context)->getStatusCode());
        static::assertSame(Response::HTTP_NO_CONTENT, $controller->deactivateExtension('plugin', 'ExamplePlugin', $context)->getStatusCode());
    }

    public function testUploadRejectsNonZipFilesAndDeletesTemporaryFile(): void
    {
        $file = static::createStub(UploadedFile::class);
        $file->method('getPathname')->willReturn('/tmp/example.txt');
        $file->method('getMimeType')->willReturn('text/plain');

        $fileSystem = $this->createMock(Filesystem::class);
        $fileSystem->expects($this->once())->method('remove')->with('/tmp/example.txt');

        $request = new Request();
        $request->files->set('file', $file);

        $this->expectExceptionObject(StoreException::pluginNotAZipFile('text/plain'));

        $this->createController(fileSystem: $fileSystem)->uploadExtensions($request, Context::createDefaultContext());
    }

    public function testLifecycleActionsAreBlockedWhenRuntimeManagementIsDisabled(): void
    {
        $this->expectExceptionObject(StoreException::extensionRuntimeExtensionManagementNotAllowed());

        $this->createController(runtimeExtensionManagementAllowed: false)
            ->activateExtension('plugin', 'ExamplePlugin', Context::createDefaultContext());
    }

    private function createController(
        ?AbstractExtensionLifecycle $lifecycle = null,
        ?PluginService $pluginService = null,
        ?PluginManagementService $pluginManagementService = null,
        ?Filesystem $fileSystem = null,
        bool $runtimeExtensionManagementAllowed = true,
    ): ExtensionStoreActionsController {
        return new ExtensionStoreActionsController(
            $lifecycle ?? static::createStub(AbstractExtensionLifecycle::class),
            $pluginService ?? static::createStub(PluginService::class),
            $pluginManagementService ?? static::createStub(PluginManagementService::class),
            $fileSystem ?? static::createStub(Filesystem::class),
            $runtimeExtensionManagementAllowed,
        );
    }
}
