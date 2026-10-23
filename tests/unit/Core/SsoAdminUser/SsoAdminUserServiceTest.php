<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\SsoUser;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Sso\SsoUser\SsoUserService;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\AdminUser\AdminUserCollection;
use Contena\Core\System\AdminUser\AdminUserEntity;

/**
 * @internal
 */
#[CoversClass(SsoUserService::class)]
class SsoUserServiceTest extends TestCase
{
    public function testInviteUserWillCreateNewUser(): void
    {
        $admin_userRepository = $this->createMock(EntityRepository::class);
        $admin_userRepository->expects($this->once())->method('search');
        $admin_userRepository->expects($this->once())->method('create');

        $ssoUserService = new SsoUserService($admin_userRepository);

        $ssoUserService->inviteUser('test@example.com', Uuid::randomHex(), Context::createDefaultContext());
    }

    public function testInviteUserWillNotCreateNewUser(): void
    {
        $admin_userEntity = new AdminUserEntity();
        $admin_userEntity->setUniqueIdentifier(Uuid::randomHex());
        $admin_userEntity->setEmail('test@example.foo');
        $admin_userEntity->setFirstName('FirstName');
        $admin_userEntity->setLastName('LastName');
        $admin_userEntity->setUsername('AdminUserName');

        $searchResult = $this->createMock(EntitySearchResult::class);
        $searchResult->expects($this->once())->method('getEntities')->willReturn(new AdminUserCollection([$admin_userEntity]));

        $admin_userRepository = $this->createMock(EntityRepository::class);
        $admin_userRepository->expects($this->once())->method('search')->willReturn($searchResult);
        $admin_userRepository->expects($this->never())->method('create');

        $ssoUserService = new SsoUserService($admin_userRepository);

        $ssoUserService->inviteUser('test@example.com', Uuid::randomHex(), Context::createDefaultContext());
    }
}
