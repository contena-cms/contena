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
        $adminUserRepository = $this->createMock(EntityRepository::class);
        $adminUserRepository->expects($this->once())->method('search');
        $adminUserRepository->expects($this->once())->method('create');

        $ssoUserService = new SsoUserService($adminUserRepository);

        $ssoUserService->inviteUser('test@example.com', Uuid::randomHex(), Context::createDefaultContext());
    }

    public function testInviteUserWillNotCreateNewUser(): void
    {
        $adminUserEntity = new AdminUserEntity();
        $adminUserEntity->setUniqueIdentifier(Uuid::randomHex());
        $adminUserEntity->setEmail('test@example.foo');
        $adminUserEntity->setName('FirstName');
        $adminUserEntity->setLastName('LastName');
        $adminUserEntity->setUsername('AdminUserName');

        $searchResult = $this->createMock(EntitySearchResult::class);
        $searchResult->expects($this->once())->method('getEntities')->willReturn(new AdminUserCollection([$adminUserEntity]));

        $adminUserRepository = $this->createMock(EntityRepository::class);
        $adminUserRepository->expects($this->once())->method('search')->willReturn($searchResult);
        $adminUserRepository->expects($this->never())->method('create');

        $ssoUserService = new SsoUserService($adminUserRepository);

        $ssoUserService->inviteUser('test@example.com', Uuid::randomHex(), Context::createDefaultContext());
    }
}
