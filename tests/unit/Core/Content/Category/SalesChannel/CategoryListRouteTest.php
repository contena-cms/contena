<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Category\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Category\Extension\CategoryListRouteExtension;
use Contena\Core\Content\Category\SalesChannel\CategoryListRoute;
use Contena\Core\Content\Category\SalesChannel\CategoryListRouteResponse;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Contena\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(CategoryListRoute::class)]
class CategoryListRouteTest extends TestCase
{
    public function testPublishesExtension(): void
    {
        $criteria = new Criteria();
        $context = Generator::generateSalesChannelContext();
        $response = static::createStub(CategoryListRouteResponse::class);

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('category-list-route.load.pre', static function (CategoryListRouteExtension $extension) use ($criteria, $context, $response): void {
            static::assertSame(['criteria' => $criteria, 'context' => $context], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = new CategoryListRoute(
            static::createStub(SalesChannelRepository::class),
            new ExtensionDispatcher($dispatcher),
        );

        static::assertSame($response, $route->load($criteria, $context));
    }
}
