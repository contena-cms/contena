<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Theme\Twig;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\ChannelRequest;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\File\Event\ChannelFileTemplateResolveEvent;
use Contena\Frontend\Theme\DatabaseChannelThemeLoader;
use Contena\Frontend\Theme\Twig\ThemeInheritanceBuilderInterface;
use Contena\Frontend\Theme\Twig\ThemeNamespaceHierarchyBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @internal
 */
#[CoversClass(ThemeNamespaceHierarchyBuilder::class)]
class ThemeNamespaceHierarchyBuilderTest extends TestCase
{
    private ThemeNamespaceHierarchyBuilder $builder;

    protected function setUp(): void
    {
        $connectionMock = static::createStub(Connection::class);
        $cachedThemeLoader = new DatabaseChannelThemeLoader($connectionMock);

        $this->builder = new ThemeNamespaceHierarchyBuilder(new TestInheritanceBuilder(), $cachedThemeLoader);
    }

    public function testThemeNamespaceHierarchyBuilderSubscribesToRequestAndExceptionEvents(): void
    {
        $events = $this->builder->getSubscribedEvents();

        static::assertSame([
            KernelEvents::REQUEST,
            KernelEvents::EXCEPTION,
            ChannelFileTemplateResolveEvent::class,
        ], array_keys($events));
    }

    public function testThemesAreEmptyIfRequestHasNoValidAttributes(): void
    {
        $request = Request::createFromGlobals();

        $this->builder->requestEvent(new RequestEvent(static::createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST));

        $this->assertThemes([], $this->builder);
    }

    public function testThemesIfThemeNameIsSet(): void
    {
        $request = Request::createFromGlobals();
        $request->attributes->set(ChannelRequest::ATTRIBUTE_THEME_NAME, 'TestTheme');

        $this->builder->requestEvent(new RequestEvent(static::createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST));

        $this->assertThemes([
            'Frontend' => true,
            'TestTheme' => true,
        ], $this->builder);
    }

    public function testRequestEventWithExceptionEvent(): void
    {
        $request = Request::createFromGlobals();
        $request->attributes->set(ChannelRequest::ATTRIBUTE_THEME_NAME, 'TestTheme');

        $this->builder->requestEvent(new ExceptionEvent(static::createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, new \RuntimeException()));

        $this->assertThemes([
            'Frontend' => true,
            'TestTheme' => true,
        ], $this->builder);
    }

    public function testOnChannelFileTemplateResolveLoadsThemeForChannel(): void
    {
        $connectionMock = $this->createMock(Connection::class);
        $connectionMock
            ->expects($this->once())
            ->method('fetchAssociative')
            ->willReturn([
                'themeName' => 'CtTheme',
                'parentThemeName' => null,
                'themeId' => Uuid::randomHex(),
            ]);

        $builder = new ThemeNamespaceHierarchyBuilder(new TestInheritanceBuilder(), new DatabaseChannelThemeLoader($connectionMock));
        $builder->onChannelFileTemplateResolve(new ChannelFileTemplateResolveEvent(Uuid::randomHex()));

        $this->assertThemes([
            'CtTheme' => true,
            'Frontend' => true,
        ], $builder);
    }

    public function testThemesIfBaseNameIsSet(): void
    {
        $request = Request::createFromGlobals();
        $request->attributes->set(ChannelRequest::ATTRIBUTE_THEME_NAME, null);
        $request->attributes->set(ChannelRequest::ATTRIBUTE_THEME_BASE_NAME, 'TestTheme');

        $this->builder->requestEvent(new RequestEvent(static::createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST));

        $this->assertThemes([
            'Frontend' => true,
            'TestTheme' => true,
        ], $this->builder);
    }

    public function testReset(): void
    {
        $request = Request::createFromGlobals();
        $request->attributes->set(ChannelRequest::ATTRIBUTE_THEME_NAME, null);
        $request->attributes->set(ChannelRequest::ATTRIBUTE_THEME_BASE_NAME, 'TestTheme');

        $this->builder->requestEvent(new RequestEvent(static::createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST));

        $this->builder->reset();

        $this->assertThemes([], $this->builder);
    }

    public function testItReturnsItsInputIfNoThemesAreSet(): void
    {
        $bundles = ['a' => 1, 'b' => 2];

        $hierarchy = $this->builder->buildNamespaceHierarchy(['a' => 1, 'b' => 2]);

        static::assertSame($bundles, $hierarchy);
    }

    public function testItPassesBundlesAndThemesToBuilder(): void
    {
        $bundles = ['a' => 1, 'b' => 2];

        $request = Request::createFromGlobals();
        $request->attributes->set(ChannelRequest::ATTRIBUTE_THEME_NAME, 'TestTheme');

        $this->builder->requestEvent(new RequestEvent(static::createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST));

        $hierarchy = $this->builder->buildNamespaceHierarchy($bundles);

        static::assertEquals([
            'Frontend' => 1,
            'TestTheme' => 1,
        ], $hierarchy);
    }

    /**
     * @param array<string, bool> $expectation
     */
    private function assertThemes(array $expectation, ThemeNamespaceHierarchyBuilder $builder): void
    {
        $refProperty = new \ReflectionProperty(ThemeNamespaceHierarchyBuilder::class, 'themes')->getValue($builder);

        static::assertEquals($expectation, $refProperty);
    }
}

/**
 * @internal
 */
class TestInheritanceBuilder implements ThemeInheritanceBuilderInterface
{
    /**
     * @param array<string, int> $bundles
     * @param array<int|string, bool> $themes
     *
     * @return array<string, int>
     */
    public function build(array $bundles, array $themes): array
    {
        // Convert boolean theme values to integer priorities for test purposes
        $result = [];
        foreach ($themes as $key => $value) {
            $result[(string) $key] = $value === true ? 1 : 0;
        }

        return $result;
    }
}
