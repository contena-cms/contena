<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Test\Stub\App;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\App\AppEntity;
use Contena\Core\Framework\App\Manifest\Manifest;
use Contena\Core\Framework\App\Manifest\Xml\Meta\Metadata;
use Contena\Core\Test\Stub\App\StaticSourceResolver;
use Contena\Core\Test\Stub\Framework\Util\StaticFilesystem;

/**
 * @internal
 */
#[CoversClass(StaticSourceResolver::class)]
class StaticSourceResolverTest extends TestCase
{
    public function testCanResolveManifestToType(): void
    {
        $app = static::createStub(Manifest::class);

        $resolver = new StaticSourceResolver();

        static::assertSame('static', $resolver->resolveSourceType($app));
    }

    public function testFilesystemMethodsUseConfiguredFilesystem(): void
    {
        $fs = new StaticFilesystem();

        $resolver = new StaticSourceResolver([
            'myApp' => $fs,
        ]);

        $metadata = Metadata::fromArray(['name' => 'myApp', 'label' => [], 'author' => 'myApp', 'copyright' => 'none', 'license' => 'none', 'version' => '99']);
        $manifest = static::createStub(Manifest::class);
        $manifest->method('getMetadata')->willReturn($metadata);
        $app = (new AppEntity())->assign(['name' => 'myApp']);

        static::assertSame($fs, $resolver->filesystemForManifest($manifest));
        static::assertSame($fs, $resolver->filesystemForApp($app));
        static::assertSame($fs, $resolver->filesystemForAppName('myApp'));
    }

    public function testFilesystemMethodsReturnDefaultFilesystemWhenNonConfigured(): void
    {
        $fs = new StaticFilesystem();

        $resolver = new StaticSourceResolver([
            'someOtherApp' => $fs,
        ]);

        $metadata = Metadata::fromArray(['name' => 'myApp', 'label' => [], 'author' => 'myApp', 'copyright' => 'none', 'license' => 'none', 'version' => '99']);
        $manifest = static::createStub(Manifest::class);
        $manifest->method('getMetadata')->willReturn($metadata);
        $app = (new AppEntity())->assign(['name' => 'myApp']);

        static::assertNotSame($fs, $resolver->filesystemForManifest($manifest));
        static::assertNotSame($fs, $resolver->filesystemForApp($app));
        static::assertNotSame($fs, $resolver->filesystemForAppName('myApp'));
    }
}
