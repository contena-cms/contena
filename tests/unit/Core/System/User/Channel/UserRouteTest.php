<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\User\Channel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\System\User\Channel\UserResponse;
use Contena\Core\System\User\Channel\UserRoute;
use Contena\Core\System\User\Extension\UserRouteExtension;
use Contena\Core\System\User\UserEntity;
use Contena\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(UserRoute::class)]
class UserRouteTest extends TestCase
{
    public function testPublishesExtension(): void
    {
        $request = new Request();
        $context = Generator::generateChannelContext();
        $criteria = new Criteria();
        $user = new UserEntity();
        $response = new UserResponse(new UserEntity());

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('user-route.load.pre', static function (UserRouteExtension $extension) use ($request, $context, $criteria, $user, $response): void {
            static::assertSame(['request' => $request, 'context' => $context, 'criteria' => $criteria, 'user' => $user], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = new UserRoute(
            static::createStub(EntityRepository::class),
            new ExtensionDispatcher($dispatcher),
        );

        static::assertSame($response, $route->load($request, $context, $criteria, $user));
    }
}
