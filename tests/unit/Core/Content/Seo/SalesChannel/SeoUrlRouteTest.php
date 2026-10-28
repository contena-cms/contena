<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Seo\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Seo\Extension\SeoUrlRouteExtension;
use Contena\Core\Content\Seo\SalesChannel\SeoUrlRoute;
use Contena\Core\Content\Seo\SalesChannel\SeoUrlRouteResponse;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Contena\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(SeoUrlRoute::class)]
class SeoUrlRouteTest extends TestCase
{
    public function testPublishesExtension(): void
    {
        $request = new Request();
        $context = Generator::generateSalesChannelContext();
        $criteria = new Criteria();
        $response = static::createStub(SeoUrlRouteResponse::class);

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('seo-url-route.load.pre', static function (SeoUrlRouteExtension $extension) use ($request, $context, $criteria, $response): void {
            static::assertSame(['request' => $request, 'context' => $context, 'criteria' => $criteria], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = new SeoUrlRoute(
            static::createStub(SalesChannelRepository::class),
            new ExtensionDispatcher($dispatcher),
        );

        static::assertSame($response, $route->load($request, $context, $criteria));
    }
}
