<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Cms\Channel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Cms\CmsPageEntity;
use Contena\Core\Content\Cms\Channel\CmsRouteResponse;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
#[CoversClass(CmsRouteResponse::class)]
class CmsRouteResponseTest extends TestCase
{
    public function testGetCmsPage(): void
    {
        $expected = new CmsPageEntity();
        $response = new CmsRouteResponse($expected);

        $actual = $response->getCmsPage();
        static::assertSame($expected, $actual);
    }
}
