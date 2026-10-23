<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\User\Channel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\User\Aggregate\UserGroup\UserGroupEntity;
use Contena\Core\System\User\Channel\UserGroupRegistrationSettingsRoute;
use Contena\Core\System\User\Channel\UserGroupRegistrationSettingsRouteResponse;
use Contena\Core\System\User\Extension\UserGroupRegistrationSettingsRouteExtension;
use Contena\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[CoversClass(UserGroupRegistrationSettingsRoute::class)]
class UserGroupRegistrationSettingsRouteTest extends TestCase
{
    public function testPublishesExtension(): void
    {
        $userGroupId = Uuid::randomHex();
        $context = Generator::generateChannelContext();
        $response = new UserGroupRegistrationSettingsRouteResponse(new UserGroupEntity());

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('user-group-registration-settings-route.load.pre', static function (UserGroupRegistrationSettingsRouteExtension $extension) use ($userGroupId, $context, $response): void {
            static::assertSame(['userGroupId' => $userGroupId, 'context' => $context], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = new UserGroupRegistrationSettingsRoute(
            static::createStub(EntityRepository::class),
            new ExtensionDispatcher($dispatcher),
        );

        static::assertSame($response, $route->load($userGroupId, $context));
    }
}
