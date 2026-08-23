<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Controller;

use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Frontend\Test\Controller\FrontendControllerTestBehaviour;

/**
 * @internal
 */
class BlogControllerTest extends TestCase
{
    use FrontendControllerTestBehaviour;
    use IntegrationTestBehaviour;

    private const string DEFAULT_BLOG_ID = '3bdbb2474ffec6bfc96342ec3f4a75a0';

    private const string DEFAULT_BLOG_LAYOUT_ID = '4c5521c0ef05a4a84f83cdbade6ae1f8';

    public function testBlogPageRendersAssignedContentLayout(): void
    {
        $response = $this->request('GET', 'blog/' . self::DEFAULT_BLOG_ID, []);
        $content = (string) $response->getContent();

        static::assertSame(200, $response->getStatusCode(), $content);
        static::assertStringContainsString('data-page-id="' . self::DEFAULT_BLOG_LAYOUT_ID . '"', $content);
        static::assertStringContainsString('<h1>Welcome to Contena</h1>', $content);
    }

    public function testActiveRouteParametersAreSafelyEscapedForJavaScript(): void
    {
        $response = $this->request('GET', 'blog/' . self::DEFAULT_BLOG_ID, []);
        $content = (string) $response->getContent();

        static::assertSame(200, $response->getStatusCode(), $content);
        static::assertStringContainsString('window.activeRouteParameters = JSON.parse(', $content);
        static::assertStringNotContainsString('window.activeRouteParameters = JSON.parse(\'null\')', $content);
        static::assertMatchesRegularExpression(
            '/window\\.activeRouteParameters = JSON\\.parse\\(\'([^\']*)\'\\);/',
            $content,
        );

        preg_match('/window\\.activeRouteParameters = JSON\\.parse\\(\'([^\']*)\'\\);/', $content, $matches);
        static::assertArrayHasKey(1, $matches);
        static::assertIsString($matches[1]);
        static::assertStringNotContainsString('<', $matches[1]);
        static::assertStringNotContainsString('>', $matches[1]);
        static::assertStringNotContainsString('&', $matches[1]);
    }
}
