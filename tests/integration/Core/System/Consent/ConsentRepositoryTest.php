<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\Consent;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Consent\ConsentRepository;
use Contena\Core\System\Consent\ConsentScope\AdminUser;
use Contena\Core\System\Consent\ConsentStatus;
use Contena\Core\System\Consent\Definition\BackendData;
use Contena\Core\System\Consent\DTO\ConsentStateRecord;
use Contena\Tests\Unit\Core\System\Consent\TestDefinition;

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
        $adminTracking = new TestDefinition('admin_tracking', AdminUser::NAME, latestRevision: '2026-02-01');

        $userId = $this->createUser('test-user');
        $this->repository->updateConsentState($adminTracking, $userId, ConsentStatus::ACCEPTED, $userId, '2026-02-01');

        $states = $this->repository->fetchAllConsentStates();

        static::assertCount(1, $states);

        static::assertSame('test-user', $states[0]->actor);
        static::assertSame($userId, $states[0]->identifier);
        static::assertSame(ConsentStatus::ACCEPTED, $states[0]->status);
        static::assertSame($adminTracking->getName(), $states[0]->name);
        static::assertSame('2026-02-01', $states[0]->revision);
    }

    public function testUpdateSystemConsentState(): void
    {
        $backendData = new BackendData();

        $userId = $this->createUser('test-user');
        $this->repository->updateConsentState($backendData, 'system', ConsentStatus::ACCEPTED, $userId);

        $states = $this->repository->fetchAllConsentStates();

        static::assertCount(1, $states);

        static::assertSame('test-user', $states[0]->actor);
        static::assertSame('system', $states[0]->identifier);
        static::assertSame(ConsentStatus::ACCEPTED, $states[0]->status);
        static::assertSame('backend_data', $states[0]->name);
    }

    public function testUpdateConsentStateUpdatesExisting(): void
    {
        $tracking = new TestDefinition('admin_tracking', AdminUser::NAME, latestRevision: '1.0.0');

        $userId = $this->createUser('test-user');
        $this->repository->updateConsentState($tracking, $userId, ConsentStatus::ACCEPTED, $userId, '1.0.0');

        $states = $this->repository->fetchAllConsentStates();

        static::assertCount(1, $states);

        static::assertSame('test-user', $states[0]->actor);
        static::assertSame($userId, $states[0]->identifier);
        static::assertSame(ConsentStatus::ACCEPTED, $states[0]->status);
        static::assertSame('1.0.0', $states[0]->revision);

        $this->repository->updateConsentState($tracking, $userId, ConsentStatus::REVOKED, $userId);

        $states = $this->repository->fetchAllConsentStates();

        static::assertCount(1, $states);

        static::assertSame('test-user', $states[0]->actor);
        static::assertSame($userId, $states[0]->identifier);
        static::assertSame(ConsentStatus::REVOKED, $states[0]->status);
        static::assertNull($states[0]->revision);
    }

    public function testRevokeConsentStateClearsPassedRevision(): void
    {
        $tracking = new TestDefinition('admin_tracking', AdminUser::NAME, latestRevision: '2.0.0');

        $userId = $this->createUser('test-user');
        $this->repository->updateConsentState($tracking, $userId, ConsentStatus::REVOKED, $userId, '2.0.0');

        $states = $this->repository->fetchAllConsentStates();

        static::assertCount(1, $states);
        static::assertSame(ConsentStatus::DECLINED, $states[0]->status);
        static::assertNull($states[0]->revision);
    }

    public function testInitializesRevokedConsentsWithDeclinedState(): void
    {
        $tracking = new TestDefinition('admin_tracking', AdminUser::NAME);

        $userId = $this->createUser('test-user');
        $this->repository->updateConsentState($tracking, $userId, ConsentStatus::REVOKED, $userId);

        $states = $this->repository->fetchAllConsentStates();

        static::assertCount(1, $states);

        static::assertSame('test-user', $states[0]->actor);
        static::assertSame($userId, $states[0]->identifier);
        static::assertSame(ConsentStatus::DECLINED, $states[0]->status);
    }

    public function testDoesNotOverrideDeclinedStateWithRevokedState(): void
    {
        $tracking = new TestDefinition('admin_tracking', AdminUser::NAME);

        $userId = $this->createUser('test-user');
        $this->repository->updateConsentState($tracking, $userId, ConsentStatus::REVOKED, $userId);
        $this->repository->updateConsentState($tracking, $userId, ConsentStatus::REVOKED, $userId);

        $states = $this->repository->fetchAllConsentStates();

        static::assertCount(1, $states);

        static::assertSame('test-user', $states[0]->actor);
        static::assertSame($userId, $states[0]->identifier);
        static::assertSame(ConsentStatus::DECLINED, $states[0]->status);
    }

    public function testSetsRevokedStateIfStateWasAccepted(): void
    {
        $tracking = new TestDefinition('admin_tracking', AdminUser::NAME);

        $userId = $this->createUser('test-user');
        $this->repository->updateConsentState($tracking, $userId, ConsentStatus::ACCEPTED, $userId);
        $this->repository->updateConsentState($tracking, $userId, ConsentStatus::REVOKED, $userId);

        $states = $this->repository->fetchAllConsentStates();

        static::assertCount(1, $states);

        static::assertSame('test-user', $states[0]->actor);
        static::assertSame($userId, $states[0]->identifier);
        static::assertSame(ConsentStatus::REVOKED, $states[0]->status);
    }

    public function testFetchAllConsentStates(): void
    {
        $adminTracking = new TestDefinition('admin_tracking', AdminUser::NAME);
        $backendData = new BackendData();

        $user1 = $this->createUser('first-user');
        $this->repository->updateConsentState($backendData, 'system', ConsentStatus::ACCEPTED, $user1);

        $user2 = $this->createUser('second-user');
        $this->repository->updateConsentState($adminTracking, $user2, ConsentStatus::REVOKED, $user2);

        $result = $this->repository->fetchAllConsentStates();

        static::assertCount(2, $result);
        static::assertContainsOnlyInstancesOf(ConsentStateRecord::class, $result);

        static::assertSame('backend_data', $result[0]->name);
        static::assertSame('system', $result[0]->identifier);
        static::assertSame('first-user', $result[0]->actor);
        static::assertSame(ConsentStatus::ACCEPTED, $result[0]->status);

        static::assertSame($adminTracking->getName(), $result[1]->name);
        static::assertSame($user2, $result[1]->identifier);
        static::assertSame('second-user', $result[1]->actor);
        static::assertSame(ConsentStatus::DECLINED, $result[1]->status);
    }

    private function createUser(string $name): string
    {
        $userId = Uuid::randomHex();
        $tenantId = Uuid::randomHex();
        $tenantCode = 'consent-' . \bin2hex(\random_bytes(4));

        $this->getContainer()->get('tenant.repository')->create([[
            'id' => $tenantId,
            'name' => 'Consent Test Tenant',
            'code' => $tenantCode,
            'status' => true,
        ]], Context::createDefaultContext());

        $localeId = $this->getContainer()->get(Connection::class)->fetchOne(
            'SELECT `locale_id` FROM `language` WHERE `id` = :id',
            ['id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)],
        );
        static::assertIsString($localeId);

        $userRepo = $this->getContainer()->get('user.repository');

        $userRepo->create([
            [
                'id' => $userId,
                'tenantId' => $tenantId,
                'username' => $name,
                'name' => 'Consent Test User',
                'email' => $name . '@example.com',
                'password' => 'contenaAdmin',
                'localeId' => Uuid::fromBytesToHex($localeId),
                'admin' => true,
            ],
        ], Context::createTenantContext($tenantId));

        return $userId;
    }
}
