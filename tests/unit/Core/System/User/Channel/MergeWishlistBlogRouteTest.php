<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\User\Channel;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\System\Channel\Entity\ChannelRepository;
use Contena\Core\System\Channel\SuccessResponse;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\System\User\Channel\MergeWishlistBlogRoute;
use Contena\Core\System\User\Extension\MergeWishlistBlogRouteExtension;
use Contena\Core\System\User\UserEntity;
use Contena\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[CoversClass(MergeWishlistBlogRoute::class)]
class MergeWishlistBlogRouteTest extends TestCase
{
    public function testPublishesExtension(): void
    {
        $data = new RequestDataBag();
        $context = Generator::generateChannelContext();
        $user = new UserEntity();
        $response = new SuccessResponse();

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('merge-wishlist-blog-route.merge.pre', static function (MergeWishlistBlogRouteExtension $extension) use ($data, $context, $user, $response): void {
            static::assertSame(['data' => $data, 'context' => $context, 'user' => $user], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = new MergeWishlistBlogRoute(
            static::createStub(EntityRepository::class),
            static::createStub(ChannelRepository::class),
            static::createStub(SystemConfigService::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(Connection::class),
            new ExtensionDispatcher($dispatcher),
        );

        static::assertSame($response, $route->merge($data, $context, $user));
    }
}
