<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Category\Channel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Category\Channel\CategoryListRoute;
use Contena\Core\Content\Category\Channel\CategoryListRouteResponse;
use Contena\Core\Content\Category\Extension\CategoryListRouteExtension;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\System\Channel\Entity\ChannelRepository;
use Contena\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[CoversClass(CategoryListRoute::class)]
class CategoryListRouteTest extends TestCase
{
    public function testPublishesExtension(): void
    {
        $criteria = new Criteria();
        $context = Generator::generateChannelContext();
        $response = static::createStub(CategoryListRouteResponse::class);

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('category-list-route.load.pre', static function (CategoryListRouteExtension $extension) use ($criteria, $context, $response): void {
            static::assertSame(['criteria' => $criteria, 'context' => $context], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = new CategoryListRoute(
            static::createStub(ChannelRepository::class),
            new ExtensionDispatcher($dispatcher),
        );

        static::assertSame($response, $route->load($criteria, $context));
    }
}
