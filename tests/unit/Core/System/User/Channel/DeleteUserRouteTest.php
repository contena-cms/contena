<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\User\Channel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\System\Channel\NoContentResponse;
use Contena\Core\System\User\Channel\DeleteUserRoute;
use Contena\Core\System\User\Extension\DeleteUserRouteExtension;
use Contena\Core\System\User\UserEntity;
use Contena\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[CoversClass(DeleteUserRoute::class)]
class DeleteUserRouteTest extends TestCase
{
    public function testPublishesExtension(): void
    {
        $context = Generator::generateChannelContext();
        $user = new UserEntity();
        $response = new NoContentResponse();

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('delete-user-route.delete.pre', static function (DeleteUserRouteExtension $extension) use ($context, $user, $response): void {
            static::assertSame(['context' => $context, 'user' => $user], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = new DeleteUserRoute(
            static::createStub(EntityRepository::class),
            new ExtensionDispatcher($dispatcher),
        );

        static::assertSame($response, $route->delete($context, $user));
    }
}
