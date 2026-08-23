<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Store\Session;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Store\Session\FrontendSessionStorageFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage;
use Symfony\Component\HttpFoundation\Session\Storage\SessionStorageFactoryInterface;

/**
 * @internal
 */
#[CoversClass(FrontendSessionStorageFactory::class)]
class FrontendSessionStorageFactoryTest extends TestCase
{
    #[DataProvider('sessionStorageProvider')]
    public function testCreateStorageSetsCookiePath(
        ?string $baseUrl,
        string $expectedCookiePath
    ): void {
        $storageMock = $this->createMock(NativeSessionStorage::class);

        $storageMock->expects($this->once())
            ->method('setOptions')
            ->with(['cookie_path' => $expectedCookiePath]);

        $innerFactory = $this->createMock(SessionStorageFactoryInterface::class);
        $innerFactory->expects($this->once())->method('createStorage')->willReturn($storageMock);

        $factory = new FrontendSessionStorageFactory($innerFactory, true);

        $request = new Request();
        $request->attributes->set('ct-channel-base-url', $baseUrl);

        $factory->createStorage($request);
    }

    /**
     * @return iterable<string, array{baseUrl: string|null, expectedCookiePath: string}>
     */
    public static function sessionStorageProvider(): iterable
    {
        yield 'Specific channel path' => [
            'baseUrl' => '/germany',
            'expectedCookiePath' => '/germany',
        ];

        yield 'Empty string defaults to root' => [
            'baseUrl' => '',
            'expectedCookiePath' => '/',
        ];

        yield 'Null value defaults to root' => [
            'baseUrl' => null,
            'expectedCookiePath' => '/',
        ];
    }

    public function testCreateStorageDoesNotSetCookiePathWhenConfigDisabled(): void
    {
        $storageMock = $this->createMock(NativeSessionStorage::class);

        $storageMock->expects($this->never())
            ->method('setOptions');

        $innerFactory = $this->createMock(SessionStorageFactoryInterface::class);
        $innerFactory->expects($this->once())->method('createStorage')->willReturn($storageMock);

        $factory = new FrontendSessionStorageFactory($innerFactory, false);

        $request = new Request();
        $request->attributes->set('ct-channel-base-url', '/germany');

        $factory->createStorage($request);
    }

    public function testCreateStorageDoesNotSetCookiePathByDefault(): void
    {
        $storageMock = $this->createMock(NativeSessionStorage::class);

        $storageMock->expects($this->never())
            ->method('setOptions');

        $innerFactory = $this->createMock(SessionStorageFactoryInterface::class);
        $innerFactory->expects($this->once())->method('createStorage')->willReturn($storageMock);

        $factory = new FrontendSessionStorageFactory($innerFactory);

        $request = new Request();
        $request->attributes->set('ct-channel-base-url', '/germany');

        $factory->createStorage($request);
    }
}
