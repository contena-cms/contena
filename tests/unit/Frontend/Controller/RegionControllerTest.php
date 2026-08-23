<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\Test\Generator;
use Contena\Frontend\Controller\RegionController;
use Contena\Frontend\Pagelet\Region\RegionDataPagelet;
use Contena\Frontend\Pagelet\Region\RegionDataPageletLoader;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(RegionController::class)]
class RegionControllerTest extends TestCase
{
    private RegionDataPageletLoader&MockObject $pageletLoader;

    private RegionControllerTestClass $controller;

    protected function setUp(): void
    {
        $this->pageletLoader = static::createMock(RegionDataPageletLoader::class);
        $this->controller = new RegionControllerTestClass($this->pageletLoader);
    }

    public function testGetRegionDataUsesCountryIdFromQuery(): void
    {
        $request = new Request(['countryId' => 'query-country-id']);
        $context = Generator::generateChannelContext();

        $this->pageletLoader->expects($this->once())
            ->method('load')
            ->with('query-country-id', null, $request, $context)
            ->willReturn(new RegionDataPagelet());

        $this->controller->getRegionData($request, $context);
    }

    public function testGetRegionDataUsesParentIdFromQuery(): void
    {
        $request = new Request(['countryId' => 'query-country-id', 'parentId' => 'query-parent-id']);
        $context = Generator::generateChannelContext();

        $this->pageletLoader->expects($this->once())
            ->method('load')
            ->with('query-country-id', 'query-parent-id', $request, $context)
            ->willReturn(new RegionDataPagelet());

        $this->controller->getRegionData($request, $context);
    }

    public function testGetRegionDataThrowsExceptionWithoutCountryId(): void
    {
        $this->pageletLoader->expects($this->never())
            ->method('load');

        $this->expectExceptionObject(RoutingException::missingRequestParameter('countryId'));

        $this->controller->getRegionData(new Request(), Generator::generateChannelContext());
    }
}

/**
 * @internal
 */
class RegionControllerTestClass extends RegionController
{
    use FrontendControllerMockTrait;
}
