<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Theme;

use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Contena\Core\ChannelRequest;
use Contena\Core\PlatformRequest;
use Contena\Core\Test\Generator;
use Contena\Frontend\Theme\ThemeRuntimeConfig;
use Contena\Frontend\Theme\ThemeRuntimeConfigService;
use Contena\Frontend\Theme\ThemeScripts;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[CoversClass(ThemeScripts::class)]
class ThemeScriptsTest extends TestCase
{
    private RequestStack $requestStack;

    private ThemeRuntimeConfigService&Stub $themeRuntimeConfigService;

    private FilesystemOperator&Stub $tempFilesystem;

    private LoggerInterface&Stub $logger;

    private ThemeScripts $themeScripts;

    protected function setUp(): void
    {
        parent::setUp();
        $this->themeRuntimeConfigService = static::createStub(ThemeRuntimeConfigService::class);
        $this->tempFilesystem = static::createStub(FilesystemOperator::class);
        $this->logger = static::createStub(LoggerInterface::class);
        $this->requestStack = new RequestStack();
        $this->themeScripts = $this->createThemeScripts();
    }

    public function testGetThemeScriptsWhenNoRequestGiven(): void
    {
        $themeRuntimeConfigService = $this->createMock(ThemeRuntimeConfigService::class);
        $themeRuntimeConfigService->expects($this->never())->method('getResolvedRuntimeConfig');
        $themeScripts = $this->createThemeScripts($themeRuntimeConfigService);
        static::assertSame([], $themeScripts->getThemeScripts());
    }

    public function testGetThemeScriptsWhenAdminRequest(): void
    {
        $this->requestStack->push(new Request());

        $themeRuntimeConfigService = $this->createMock(ThemeRuntimeConfigService::class);
        $themeRuntimeConfigService->expects($this->never())->method('getResolvedRuntimeConfig');
        $themeScripts = $this->createThemeScripts($themeRuntimeConfigService);
        static::assertSame([], $themeScripts->getThemeScripts());
    }

    public function testNotExistingTheme(): void
    {
        $request = new Request();
        $request->attributes->set(ChannelRequest::ATTRIBUTE_THEME_NAME, 'invalid');
        $request->attributes->set(ChannelRequest::ATTRIBUTE_THEME_ID, 'invalid');
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CHANNEL_ID, 'channel-id');
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT, Generator::generateChannelContext());
        $this->requestStack->push($request);

        $themeRuntimeConfigService = $this->createMock(ThemeRuntimeConfigService::class);
        $themeRuntimeConfigService->expects($this->once())->method('getResolvedRuntimeConfig')->willReturn(null);
        $themeScripts = $this->createThemeScripts($themeRuntimeConfigService);

        static::assertSame([], $themeScripts->getThemeScripts());
    }

    public function testLoadPaths(): void
    {
        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CHANNEL_ID, 'Frontend');
        $request->attributes->set(ChannelRequest::ATTRIBUTE_THEME_ID, 'Frontend');
        $request->attributes->set(ChannelRequest::ATTRIBUTE_THEME_NAME, 'Frontend');

        $channelContext = Generator::generateChannelContext();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT, $channelContext);

        $this->requestStack->push($request);

        $themeRuntimeConfig = ThemeRuntimeConfig::fromArray([
            'themeId' => 'Frontend',
            'technicalName' => 'Frontend',
            'resolvedConfig' => [],
            'viewInheritance' => [],
            'scriptFiles' => ['js/foo/foo.js', 'js/foo/bar.js'],
            'iconSets' => [],
            'updatedAt' => new \DateTimeImmutable(),
        ]);
        $themeRuntimeConfigService = $this->createMock(ThemeRuntimeConfigService::class);
        $themeRuntimeConfigService->expects($this->once())->method('getResolvedRuntimeConfig')->willReturn($themeRuntimeConfig);
        $themeScripts = $this->createThemeScripts($themeRuntimeConfigService);

        static::assertSame(['js/foo/foo.js', 'js/foo/bar.js'], $themeScripts->getThemeScripts());
    }

    public function testGetImportMapReturnsNullWhenNoRequest(): void
    {
        $themeRuntimeConfigService = $this->createMock(ThemeRuntimeConfigService::class);
        $themeRuntimeConfigService->expects($this->never())->method('getResolvedRuntimeConfig');
        $themeScripts = $this->createThemeScripts($themeRuntimeConfigService);

        static::assertNull($themeScripts->getImportMap());
    }

    public function testGetImportMapReturnsNullWhenNoBuildPresent(): void
    {
        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CHANNEL_ID, 'Frontend');
        $request->attributes->set(ChannelRequest::ATTRIBUTE_THEME_ID, 'Frontend');
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT, Generator::generateChannelContext());
        $this->requestStack->push($request);

        $themeRuntimeConfig = ThemeRuntimeConfig::fromArray([
            'themeId' => 'Frontend',
            'technicalName' => 'Frontend',
            'resolvedConfig' => [],
            'viewInheritance' => [],
            'scriptFiles' => ['js/frontend/frontend.js'],
            'iconSets' => [],
            // importMap deliberately absent (no Vite build yet)
            'updatedAt' => new \DateTimeImmutable(),
        ]);

        $this->themeRuntimeConfigService->method('getResolvedRuntimeConfig')->willReturn($themeRuntimeConfig);

        static::assertNull($this->themeScripts->getImportMap());
    }

    public function testGetImportMapReturnsStoredMap(): void
    {
        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CHANNEL_ID, 'Frontend');
        $request->attributes->set(ChannelRequest::ATTRIBUTE_THEME_ID, 'Frontend');
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT, Generator::generateChannelContext());
        $this->requestStack->push($request);

        $importMap = [
            'imports' => [
                'contena' => '/bundles/frontend/frontend/contena/contena.js',
                'CT:Button' => 'js/components/CT/Button.js',
            ],
            'scopes' => [
                'js/components/MyPlugin/' => [
                    'debounce' => 'js/components/MyPlugin/vendor/debounce-abc123.js',
                ],
            ],
        ];

        $themeRuntimeConfig = ThemeRuntimeConfig::fromArray([
            'themeId' => 'Frontend',
            'technicalName' => 'Frontend',
            'resolvedConfig' => [],
            'viewInheritance' => [],
            'scriptFiles' => ['js/frontend/frontend.js'],
            'iconSets' => [],
            'importMap' => $importMap,
            'updatedAt' => new \DateTimeImmutable(),
        ]);

        $this->themeRuntimeConfigService->method('getResolvedRuntimeConfig')->willReturn($themeRuntimeConfig);

        static::assertSame($importMap, $this->themeScripts->getImportMap());
    }

    public function testGetDevImportMapReturnsNullWhenFlagFileAbsent(): void
    {
        $this->tempFilesystem->method('fileExists')->willReturn(false);

        static::assertNull($this->themeScripts->getDevImportMap());
    }

    public function testGetDevImportMapReturnsParsedMapWhenFlagFilePresent(): void
    {
        $devMap = ['imports' => ['contena' => 'http://localhost:5176/src/contena.ts']];

        $this->tempFilesystem->method('fileExists')->willReturn(true);
        $this->tempFilesystem->method('read')->willReturn((string) json_encode($devMap));

        static::assertSame($devMap, $this->themeScripts->getDevImportMap());
    }

    public function testGetDevImportMapReturnsNullForInvalidJson(): void
    {
        $this->tempFilesystem->method('fileExists')->willReturn(true);
        $this->tempFilesystem->method('read')->willReturn('not json {{{');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');
        $themeScripts = $this->createThemeScripts(logger: $logger);

        static::assertNull($themeScripts->getDevImportMap());
    }

    public function testGetDevImportMapReturnsNullWhenRequestThemeIdDoesNotMatchDevThemeId(): void
    {
        $request = new Request();
        $request->attributes->set(ChannelRequest::ATTRIBUTE_THEME_ID, 'request-theme');
        $this->requestStack->push($request);

        $this->tempFilesystem->method('fileExists')->willReturn(true);
        $this->tempFilesystem->method('read')->willReturn((string) json_encode([
            'imports' => ['contena' => 'http://localhost:5176/src/contena.ts'],
            'themeId' => 'dev-theme',
        ]));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('debug')
            ->with(
                'Frontend dev import map skipped due to theme mismatch.',
                [
                    'requestThemeId' => 'request-theme',
                    'devThemeId' => 'dev-theme',
                    'path' => 'cache/frontend_components.dev.json',
                ]
            );
        $themeScripts = $this->createThemeScripts(logger: $logger);

        static::assertNull($themeScripts->getDevImportMap());
    }

    public function testGetDevImportMapReturnsMapWhenRequestThemeIdMatchesDevThemeId(): void
    {
        $request = new Request();
        $request->attributes->set(ChannelRequest::ATTRIBUTE_THEME_ID, 'frontend');
        $this->requestStack->push($request);

        $devMap = [
            'imports' => ['contena' => 'http://localhost:5176/src/contena.ts'],
            'themeId' => 'frontend',
        ];

        $this->tempFilesystem->method('fileExists')->willReturn(true);
        $this->tempFilesystem->method('read')->willReturn((string) json_encode($devMap));

        static::assertSame($devMap, $this->themeScripts->getDevImportMap());
    }

    public function testGetDevImportMapReturnsMapWhenRequestThemeIdIsMissing(): void
    {
        $request = new Request();
        $this->requestStack->push($request);

        $devMap = [
            'imports' => ['contena' => 'http://localhost:5176/src/contena.ts'],
            'themeId' => 'frontend',
        ];

        $this->tempFilesystem->method('fileExists')->willReturn(true);
        $this->tempFilesystem->method('read')->willReturn((string) json_encode($devMap));

        static::assertSame($devMap, $this->themeScripts->getDevImportMap());
    }

    public function testGetDevImportMapReturnsMapWhenThemeIdIsNotString(): void
    {
        $request = new Request();
        $request->attributes->set(ChannelRequest::ATTRIBUTE_THEME_ID, 'frontend');
        $this->requestStack->push($request);

        $devMap = [
            'imports' => ['contena' => 'http://localhost:5176/src/contena.ts'],
            'themeId' => 123,
        ];

        $this->tempFilesystem->method('fileExists')->willReturn(true);
        $this->tempFilesystem->method('read')->willReturn((string) json_encode($devMap));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('debug');
        $themeScripts = $this->createThemeScripts(logger: $logger);

        static::assertSame($devMap, $themeScripts->getDevImportMap());
    }

    private function createThemeScripts(
        ?ThemeRuntimeConfigService $themeRuntimeConfigService = null,
        ?LoggerInterface $logger = null,
    ): ThemeScripts {
        return new ThemeScripts(
            $this->requestStack,
            $themeRuntimeConfigService ?? $this->themeRuntimeConfigService,
            $this->tempFilesystem,
            $logger ?? $this->logger,
        );
    }
}
