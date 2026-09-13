<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Api\Cors;

use Contena\Core\Framework\Api\Cors\CoreCorsHeaderProvider;
use Contena\Core\Framework\Api\Cors\CorsHeaders;
use Contena\Core\PlatformRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(CoreCorsHeaderProvider::class)]
class CoreCorsHeaderProviderTest extends TestCase
{
    /**
     * @var list<string>
     */
    private const array PLATFORM_HEADERS = [
        'Content-Type',
        'Authorization',
        PlatformRequest::HEADER_CONTEXT_TOKEN,
        PlatformRequest::HEADER_ACCESS_KEY,
        PlatformRequest::HEADER_LANGUAGE_ID,
        PlatformRequest::HEADER_VERSION_ID,
        PlatformRequest::HEADER_INHERITANCE,
        PlatformRequest::HEADER_INDEXING_BEHAVIOR,
        PlatformRequest::HEADER_INCLUDE_SEO_URLS,
        PlatformRequest::HEADER_MCP_SESSION_ID,
        PlatformRequest::HEADER_MCP_PROTOCOL_VERSION,
    ];

    public function testContributesThePlatformHeadersToBothLists(): void
    {
        $headers = new CorsHeaders();

        new CoreCorsHeaderProvider()->provide($headers);

        static::assertSame(self::PLATFORM_HEADERS, $headers->getAllowed());
        static::assertSame(self::PLATFORM_HEADERS, $headers->getExposed());
    }

    public function testContributedHeadersCanBeRemovedAgain(): void
    {
        $headers = new CorsHeaders();

        new CoreCorsHeaderProvider()->provide($headers);
        $headers->removeAllowed(PlatformRequest::HEADER_INHERITANCE);

        static::assertNotContains(PlatformRequest::HEADER_INHERITANCE, $headers->getAllowed());
        static::assertContains(PlatformRequest::HEADER_INHERITANCE, $headers->getExposed());
    }
}
