<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Administration\Framework\Api\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Contena\Administration\Framework\Api\Subscriber\AdminInfoConfigBundlesSubscriber;
use Contena\Administration\Framework\Twig\ViteFileAccessorDecorator;
use Contena\Core\Framework\Api\Event\AdminInfoConfigEvent;
use Contena\Core\Test\Stub\Framework\BundleFixture;
use Contena\Core\Test\Stub\Symfony\StubKernel;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
#[CoversClass(AdminInfoConfigBundlesSubscriber::class)]
class AdminInfoConfigBundlesSubscriberTest extends TestCase
{
    #[TestDox('Subscribes to AdminInfoConfigEvent via the enrichBundles handler')]
    public function testSubscribesToAdminInfoConfigEvent(): void
    {
        $events = AdminInfoConfigBundlesSubscriber::getSubscribedEvents();

        static::assertArrayHasKey(AdminInfoConfigEvent::class, $events);
        static::assertSame('enrichBundles', $events[AdminInfoConfigEvent::class]);
    }

    #[TestDox('enrichBundles() always sets the "bundles" key (empty when nothing matches)')]
    public function testEnrichBundlesAlwaysSetsKey(): void
    {
        $event = $this->dispatchEnrichBundles(new StubKernel([]));

        static::assertArrayHasKey('bundles', $event->getConfig());
        static::assertSame([], $event->getConfig()['bundles']);
    }

    #[TestDox('Non-Contena BundleInterface entries (e.g. Symfony framework bundles) are skipped')]
    public function testSkipsNonContenaBundles(): void
    {
        $foreignBundle = static::createStub(BundleInterface::class);

        $bundles = $this->collectBundles(new StubKernel([$foreignBundle]));

        static::assertSame([], $bundles);
    }

    #[TestDox('Bundles with Vite entry points are included with their css/js arrays')]
    public function testIncludesBundleWithViteEntryPoints(): void
    {
        $bundle = new BundleFixture('AcmeBundle', '/tmp/AcmeBundle');

        $viteAccessor = static::createStub(ViteFileAccessorDecorator::class);
        $viteAccessor->method('getBundleData')->willReturn([
            'entryPoints' => [
                'acme-bundle' => [
                    'css' => ['/bundles/acmebundle/main.css'],
                    'js' => ['/bundles/acmebundle/main.js'],
                ],
            ],
        ]);

        $bundles = $this->collectBundles(new StubKernel([$bundle]), viteAccessor: $viteAccessor);

        static::assertArrayHasKey('AcmeBundle', $bundles);
        static::assertSame(['/bundles/acmebundle/main.css'], $bundles['AcmeBundle']['css']);
        static::assertSame(['/bundles/acmebundle/main.js'], $bundles['AcmeBundle']['js']);
        static::assertSame('plugin', $bundles['AcmeBundle']['type']);
    }

    #[TestDox('Bundle::getAdminBaseUrl() short-circuits getBaseUrl and is used verbatim')]
    public function testUsesExplicitAdminBaseUrl(): void
    {
        $bundle = new class('AcmeBundle', '/tmp/AcmeBundle') extends BundleFixture {
            public function getAdminBaseUrl(): string
            {
                return 'https://acme.test';
            }
        };

        $bundles = $this->collectBundles(new StubKernel([$bundle]));

        static::assertArrayHasKey('AcmeBundle', $bundles);
        static::assertSame('https://acme.test', $bundles['AcmeBundle']['baseUrl']);
    }

    #[TestDox('A meteor-app/index.html under the bundle path causes baseUrl to be generated via the router')]
    public function testGeneratesBaseUrlFromMeteorAppFixture(): void
    {
        $bundle = new BundleFixture('AcmeBundle', '/tmp/AcmeBundle');

        $filesystem = static::createStub(Filesystem::class);
        $filesystem->method('exists')->willReturn(true);

        $router = static::createStub(RouterInterface::class);
        $router->method('generate')
            ->willReturn('/admin/acme/index.html');

        $bundles = $this->collectBundles(new StubKernel([$bundle]), router: $router, filesystem: $filesystem);

        static::assertArrayHasKey('AcmeBundle', $bundles);
        static::assertSame('/admin/acme/index.html', $bundles['AcmeBundle']['baseUrl']);
    }

    #[TestDox('A bundle with neither assets, admin base url, nor meteor-app fixture is excluded')]
    public function testBundleWithNothingIsExcluded(): void
    {
        $bundle = new BundleFixture('AcmeBundle', '/tmp/AcmeBundle');

        $filesystem = static::createStub(Filesystem::class);
        $filesystem->method('exists')->willReturn(false);

        $bundles = $this->collectBundles(new StubKernel([$bundle]), filesystem: $filesystem);

        static::assertSame([], $bundles);
    }

    #[TestDox('When the router throws (eg. administration route absent), baseUrl falls back to null')]
    public function testFallsBackToNullBaseUrlWhenRouterThrows(): void
    {
        $bundle = new BundleFixture('AcmeBundle', '/tmp/AcmeBundle');

        $filesystem = static::createStub(Filesystem::class);
        $filesystem->method('exists')->willReturn(true);

        $router = static::createStub(RouterInterface::class);
        $router->method('generate')->willThrowException(new \RuntimeException('no admin route'));

        $bundles = $this->collectBundles(new StubKernel([$bundle]), router: $router, filesystem: $filesystem);

        // null baseUrl + empty vite assets => bundle filtered out
        static::assertSame([], $bundles);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function collectBundles(
        StubKernel $kernel,
        ?RouterInterface $router = null,
        ?Filesystem $filesystem = null,
        ?ViteFileAccessorDecorator $viteAccessor = null,
    ): array {
        $event = $this->dispatchEnrichBundles(
            $kernel,
            $router,
            $filesystem,
            $viteAccessor,
        );

        return $event->getConfig()['bundles'];
    }

    private function dispatchEnrichBundles(
        StubKernel $kernel,
        ?RouterInterface $router = null,
        ?Filesystem $filesystem = null,
        ?ViteFileAccessorDecorator $viteAccessor = null,
    ): AdminInfoConfigEvent {
        $viteAccessor ??= $this->emptyViteAccessor();

        $subscriber = new AdminInfoConfigBundlesSubscriber(
            $kernel,
            $router ?? static::createStub(RouterInterface::class),
            $filesystem ?? new Filesystem(),
            $viteAccessor,
        );

        $event = new AdminInfoConfigEvent([]);
        $subscriber->enrichBundles($event);

        return $event;
    }

    private function emptyViteAccessor(): ViteFileAccessorDecorator
    {
        $vite = static::createStub(ViteFileAccessorDecorator::class);
        $vite->method('getBundleData')->willReturn(['entryPoints' => []]);

        return $vite;
    }
}
