<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\User\Channel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\User\UserEntity;
use Contena\Core\System\User\Extension\RemoveWishlistBlogRouteExtension;
use Contena\Core\System\User\Channel\RemoveWishlistBlogRoute;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\SuccessResponse;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[CoversClass(RemoveWishlistBlogRoute::class)]
class RemoveWishlistBlogRouteTest extends TestCase
{
    public function testPublishesExtension(): void
    {
        $blogId = Uuid::randomHex();
        $context = Generator::generateChannelContext();
        $user = new UserEntity();
        $response = new SuccessResponse();

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('remove-wishlist-blog-route.delete.pre', static function (RemoveWishlistBlogRouteExtension $extension) use ($blogId, $context, $user, $response): void {
            static::assertSame(['blogId' => $blogId, 'context' => $context, 'user' => $user], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = new RemoveWishlistBlogRoute(
            static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class),
            static::createStub(SystemConfigService::class),
            static::createStub(EventDispatcherInterface::class),
            new ExtensionDispatcher($dispatcher),
        );

        static::assertSame($response, $route->delete($blogId, $context, $user));
    }
}
