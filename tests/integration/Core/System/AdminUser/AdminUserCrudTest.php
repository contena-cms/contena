<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\AdminUser;

use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;

/**
 * @internal
 */
class AdminUserCrudTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * Tests a circular reference between admin_user and media entity where a media is
     * defined as avatar image but at the same time the admin_user provides some "created by" media.
     *
     * This test ensures that the admin_user can be deleted without deleting the media first
     */
    public function testCircurlaUserMediaReferences(): void
    {
        $admin_userId = 'c3e61243802b4d268527194b4c6bad5c';

        $admin_userRepository = static::getContainer()->get('admin_user.repository');

        $admin_userRepository->create([[
            'id' => $admin_userId,
            'username' => 'dummy',
            'password' => 'i am safe',
            'email' => 'some-guy@contena.cn',
            'name' => 'first',
            'lastName' => 'last',
            'active' => true,
            'admin' => false,
            'locale' => [
                'name' => 'somewhere',
                'code' => 'de-DE-1',
                'territory' => 'somewhere',
            ],
            'media' => [
                ['id' => '4785006ad6d04a8e84a1b22aaafcf29a'],
                ['id' => 'e16958a74ca74c1f8608e8c0bd136921'],
            ],
            'avatarMedia' => [
                'id' => '3832e7cc43a54a3399ab4f1f830bae60',
            ],
        ]], Context::createDefaultContext());

        $admin_userRepository->delete([['id' => $admin_userId]], Context::createDefaultContext());

        $admin_user = $admin_userRepository->search(new Criteria([$admin_userId]), Context::createDefaultContext())->getEntities()->first();
        static::assertNull($admin_user);
    }
}
