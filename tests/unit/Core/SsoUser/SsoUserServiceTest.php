<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\SsoUser;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Sso\SsoUser\SsoUserService;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\User\UserCollection;
use Contena\Core\System\User\UserEntity;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SsoUserService::class)]
class SsoUserServiceTest extends TestCase
{
    public function testInviteUserWillCreateNewUser(): void
    {
        $userRepository = $this->createMock(EntityRepository::class);
        $userRepository->expects($this->once())->method('search');
        $userRepository->expects($this->once())->method('create');

        $ssoUserService = new SsoUserService($userRepository);

        $ssoUserService->inviteUser('test@example.com', Uuid::randomHex(), Context::createDefaultContext());
    }

    public function testInviteUserWillNotCreateNewUser(): void
    {
        $userEntity = new UserEntity();
        $userEntity->setUniqueIdentifier(Uuid::randomHex());
        $userEntity->setEmail('test@example.foo');
        $userEntity->setFirstName('FirstName');
        $userEntity->setLastName('LastName');
        $userEntity->setUsername('UserName');

        $searchResult = $this->createMock(EntitySearchResult::class);
        $searchResult->expects($this->once())->method('getEntities')->willReturn(new UserCollection([$userEntity]));

        $userRepository = $this->createMock(EntityRepository::class);
        $userRepository->expects($this->once())->method('search')->willReturn($searchResult);
        $userRepository->expects($this->never())->method('create');

        $ssoUserService = new SsoUserService($userRepository);

        $ssoUserService->inviteUser('test@example.com', Uuid::randomHex(), Context::createDefaultContext());
    }
}
