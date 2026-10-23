<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Breadcrumb\Channel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Breadcrumb\Channel\BreadcrumbRouteResponse;
use Contena\Core\Content\Breadcrumb\Struct\Breadcrumb;
use Contena\Core\Content\Breadcrumb\Struct\BreadcrumbCollection;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
#[CoversClass(BreadcrumbRouteResponse::class)]
class BreadcrumbRouteResponseTest extends TestCase
{
    public function testBreadcrumbIsCorrectlyConstructed(): void
    {
        $breadcrumb1 = new Breadcrumb('Home', '/');
        $breadcrumb2 = new Breadcrumb('Blogs', '/blogs');
        $breadcrumb3 = new Breadcrumb('Home', '/blogs/electronics');

        $breadcrumbData = [
            $breadcrumb1,
            $breadcrumb2,
            $breadcrumb3,
        ];

        $breadcrumb = new BreadcrumbCollection($breadcrumbData);
        $breadcrumbRouteResponse = new BreadcrumbRouteResponse($breadcrumb);

        static::assertSame($breadcrumb, $breadcrumbRouteResponse->getBreadcrumbCollection());
    }
}
