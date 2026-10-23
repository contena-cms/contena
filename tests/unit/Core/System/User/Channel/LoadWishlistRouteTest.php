<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\User\Channel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\User\UserEntity;
use Contena\Core\System\User\Extension\LoadWishlistRouteExtension;
use Contena\Core\System\User\Channel\LoadWishlistRoute;
use Contena\Core\System\User\Channel\LoadWishlistRouteResponse;
use Contena\Core\Content\Blog\Channel\AbstractBlogCloseoutFilterFactory;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\Entity\ChannelRepository;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(LoadWishlistRoute::class)]
class LoadWishlistRouteTest extends TestCase
{
    public function testPublishesExtension(): void
    {
        $request = new Request();
        $context = Generator::generateChannelContext();
        $criteria = new Criteria();
        $user = new UserEntity();
        $response = static::createStub(LoadWishlistRouteResponse::class);

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('load-wishlist-route.load.pre', static function (LoadWishlistRouteExtension $extension) use ($request, $context, $criteria, $user, $response): void {
            static::assertSame(['request' => $request, 'context' => $context, 'criteria' => $criteria, 'user' => $user], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = new LoadWishlistRoute(
            static::createStub(EntityRepository::class),
            static::createStub(ChannelRepository::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(SystemConfigService::class),
            static::createStub(AbstractBlogCloseoutFilterFactory::class),
            new ExtensionDispatcher($dispatcher),
        );

        static::assertSame($response, $route->load($request, $context, $criteria, $user));
    }
}
