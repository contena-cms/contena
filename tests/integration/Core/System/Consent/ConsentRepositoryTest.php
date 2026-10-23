<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\Consent;

use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Consent\ConsentRepository;
use Contena\Core\System\Consent\ConsentStatus;
use Contena\Core\System\Consent\Definition\BackendData;
use Contena\Core\System\Consent\Definition\BlogAnalytics;
use Contena\Core\System\Consent\DTO\ConsentStateRecord;

/**
 * @internal
 */
class ConsentRepositoryTest extends TestCase
{
    use IntegrationTestBehaviour;

    private ConsentRepository $repository;

    protected function setUp(): void
    {
        $this->repository = $this->getContainer()->get(ConsentRepository::class);
    }

    public function testUpdateConsentState(): void
    {
        $blogAnalytics = new BlogAnalytics();

        $admin_userId = $this->createUser('test-admin_user');
        $this->repository->updateConsentState($blogAnalytics, $admin_userId, ConsentStatus::ACCEPTED, $admin_userId, '2026-02-01');

        $states = $this->repository->fetchAllConsentStates();

        static::assertCount(1, $states);

        static::assertSame('test-admin_user', $states[0]->actor);
        static::assertSame($admin_userId, $states[0]->identifier);
        static::assertSame(ConsentStatus::ACCEPTED, $states[0]->status);
        static::assertSame($blogAnalytics->getName(), $states[0]->name);
        static::assertSame('2026-02-01', $states[0]->revision);
    }

    public function testUpdateSystemConsentState(): void
    {
        $backendData = new BackendData();

        $admin_userId = $this->createUser('test-admin_user');
        $this->repository->updateConsentState($backendData, 'system', ConsentStatus::ACCEPTED, $admin_userId);

        $states = $this->repository->fetchAllConsentStates();

        static::assertCount(1, $states);

        static::assertSame('test-admin_user', $states[0]->actor);
        static::assertSame('system', $states[0]->identifier);
        static::assertSame(ConsentStatus::ACCEPTED, $states[0]->status);
        static::assertSame('backend_data', $states[0]->name);
    }

    public function testUpdateConsentStateUpdatesExisting(): void
    {
        $tracking = new BlogAnalytics();

        $admin_userId = $this->createUser('test-admin_user');
        $this->repository->updateConsentState($tracking, $admin_userId, ConsentStatus::ACCEPTED, $admin_userId, '1.0.0');

        $states = $this->repository->fetchAllConsentStates();

        static::assertCount(1, $states);

        static::assertSame('test-admin_user', $states[0]->actor);
        static::assertSame($admin_userId, $states[0]->identifier);
        static::assertSame(ConsentStatus::ACCEPTED, $states[0]->status);
        static::assertSame('1.0.0', $states[0]->revision);

        $this->repository->updateConsentState($tracking, $admin_userId, ConsentStatus::REVOKED, $admin_userId);

        $states = $this->repository->fetchAllConsentStates();

        static::assertCount(1, $states);

        static::assertSame('test-admin_user', $states[0]->actor);
        static::assertSame($admin_userId, $states[0]->identifier);
        static::assertSame(ConsentStatus::REVOKED, $states[0]->status);
        static::assertNull($states[0]->revision);
    }

    public function testRevokeConsentStateClearsPassedRevision(): void
    {
        $tracking = new BlogAnalytics();

        $admin_userId = $this->createUser('test-admin_user');
        $this->repository->updateConsentState($tracking, $admin_userId, ConsentStatus::REVOKED, $admin_userId, '2.0.0');

        $states = $this->repository->fetchAllConsentStates();

        static::assertCount(1, $states);
        static::assertSame(ConsentStatus::DECLINED, $states[0]->status);
        static::assertNull($states[0]->revision);
    }

    public function testInitializesRevokedConsentsWithDeclinedState(): void
    {
        $tracking = new BlogAnalytics();

        $admin_userId = $this->createUser('test-admin_user');
        $this->repository->updateConsentState($tracking, $admin_userId, ConsentStatus::REVOKED, $admin_userId);

        $states = $this->repository->fetchAllConsentStates();

        static::assertCount(1, $states);

        static::assertSame('test-admin_user', $states[0]->actor);
        static::assertSame($admin_userId, $states[0]->identifier);
        static::assertSame(ConsentStatus::DECLINED, $states[0]->status);
    }

    public function testDoesNotOverrideDeclinedStateWithRevokedState(): void
    {
        $tracking = new BlogAnalytics();

        $admin_userId = $this->createUser('test-admin_user');
        $this->repository->updateConsentState($tracking, $admin_userId, ConsentStatus::REVOKED, $admin_userId);
        $this->repository->updateConsentState($tracking, $admin_userId, ConsentStatus::REVOKED, $admin_userId);

        $states = $this->repository->fetchAllConsentStates();

        static::assertCount(1, $states);

        static::assertSame('test-admin_user', $states[0]->actor);
        static::assertSame($admin_userId, $states[0]->identifier);
        static::assertSame(ConsentStatus::DECLINED, $states[0]->status);
    }

    public function testSetsRevokedStateIfStateWasAccepted(): void
    {
        $tracking = new BlogAnalytics();

        $admin_userId = $this->createUser('test-admin_user');
        $this->repository->updateConsentState($tracking, $admin_userId, ConsentStatus::ACCEPTED, $admin_userId);
        $this->repository->updateConsentState($tracking, $admin_userId, ConsentStatus::REVOKED, $admin_userId);

        $states = $this->repository->fetchAllConsentStates();

        static::assertCount(1, $states);

        static::assertSame('test-admin_user', $states[0]->actor);
        static::assertSame($admin_userId, $states[0]->identifier);
        static::assertSame(ConsentStatus::REVOKED, $states[0]->status);
    }

    public function testFetchAllConsentStates(): void
    {
        $blogAnalytics = new BlogAnalytics();
        $backendData = new BackendData();

        $admin_user1 = $this->createUser('first-admin_user');
        $this->repository->updateConsentState($backendData, 'system', ConsentStatus::ACCEPTED, $admin_user1);

        $admin_user2 = $this->createUser('second-admin_user');
        $this->repository->updateConsentState($blogAnalytics, $admin_user2, ConsentStatus::REVOKED, $admin_user2);

        $result = $this->repository->fetchAllConsentStates();

        static::assertCount(2, $result);
        static::assertContainsOnlyInstancesOf(ConsentStateRecord::class, $result);

        static::assertSame('backend_data', $result[0]->name);
        static::assertSame('system', $result[0]->identifier);
        static::assertSame('first-admin_user', $result[0]->actor);
        static::assertSame(ConsentStatus::ACCEPTED, $result[0]->status);

        static::assertSame($blogAnalytics->getName(), $result[1]->name);
        static::assertSame($admin_user2, $result[1]->identifier);
        static::assertSame('second-admin_user', $result[1]->actor);
        static::assertSame(ConsentStatus::DECLINED, $result[1]->status);
    }

    private function createUser(string $name): string
    {
        $admin_userId = Uuid::randomHex();
        $admin_userRepo = $this->getContainer()->get('admin_user.repository');

        $admin_userRepo->create([
            [
                'id' => $admin_userId,
                'username' => $name,
                'firstName' => 'Test',
                'lastName' => 'AdminUser',
                'email' => $name . '@example.com',
                'password' => 'contena',
                'locale' => [
                    'code' => 'de-DE-' . $name,
                    'name' => 'Test Locale',
                    'territory' => 'Test Territory',
                ],
                'title' => null,
                'admin' => true,
            ],
        ], Context::createDefaultContext());

        return $admin_userId;
    }
}
