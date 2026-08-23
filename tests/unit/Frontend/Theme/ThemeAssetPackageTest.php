<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Theme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\ChannelRequest;
use Contena\Core\PlatformRequest;
use Contena\Frontend\Theme\MD5ThemePathBuilder;
use Contena\Frontend\Theme\ThemeAssetPackage;
use Symfony\Component\Asset\VersionStrategy\StaticVersionStrategy;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[CoversClass(ThemeAssetPackage::class)]
class ThemeAssetPackageTest extends TestCase
{
    #[DataProvider('urlCases')]
    public function testGetUrl(string $inputUrl, ?Request $request, string $expectedUrl): void
    {
        $requestStack = new RequestStack();

        if ($request instanceof Request) {
            $requestStack->push($request);
        }

        $themeAssetPackage = new ThemeAssetPackage(
            ['http://localhost'],
            new StaticVersionStrategy('v1'),
            $requestStack,
            new MD5ThemePathBuilder()
        );

        $actual = $themeAssetPackage->getUrl($inputUrl);

        static::assertSame($expectedUrl, $actual);
    }

    public static function urlCases(): \Generator
    {
        yield 'absolute url' => [
            'http://localhost/absolute/url',
            Request::create('http://localhost'),
            'http://localhost/absolute/url',
        ];

        yield 'url without frontend request attributes' => [
            'path/to/file',
            Request::create('http://localhost'),
            'http://localhost/path/to/file?v1',
        ];

        yield 'url without current request' => [
            'path/to/file',
            null,
            'http://localhost/path/to/file?v1',
        ];

        $request = Request::create('http://localhost');
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CHANNEL_ID, 'channelId');

        yield 'Frontend without theme id' => [
            'path/to/file',
            $request,
            'http://localhost/path/to/file?v1',
        ];

        $request = Request::create('http://localhost');
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CHANNEL_ID, 'channelId');
        $request->attributes->set(ChannelRequest::ATTRIBUTE_THEME_ID, 'themeId');

        yield 'theme path prefix is applied on frontend requests' => [
            'path/to/file',
            $request,
            'http://localhost/theme/650d1d46787c3451e2928388df4d6c8d/path/to/file?v1',
        ];

        yield 'theme id prefix is applied on frontend requests for assets' => [
            'assets/path/to/file',
            $request,
            'http://localhost/theme/themeId/assets/path/to/file?v1',
        ];
    }
}
