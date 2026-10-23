<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\Channel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Extension\BlogListRouteExtension;
use Contena\Core\Content\Blog\Channel\BlogListResponse;
use Contena\Core\Content\Blog\Channel\BlogListRoute;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\Entity\ChannelRepository;
use Contena\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[CoversClass(BlogListRoute::class)]
class BlogListRouteTest extends TestCase
{
    public function testPublishesExtension(): void
    {
        $criteria = new Criteria();
        $context = Generator::generateChannelContext();
        $response = static::createStub(BlogListResponse::class);

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('blog-list-route.load.pre', static function (BlogListRouteExtension $extension) use ($criteria, $context, $response): void {
            static::assertSame(['criteria' => $criteria, 'context' => $context], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = new BlogListRoute(
            static::createStub(ChannelRepository::class),
            new ExtensionDispatcher($dispatcher),
        );

        static::assertSame($response, $route->load($criteria, $context));
    }
}
