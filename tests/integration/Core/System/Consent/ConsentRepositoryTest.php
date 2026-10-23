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
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

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

        $adminUserId = $this->createUser('test-admin_user');
        $this->repository->updateConsentState($blogAnalytics, $adminUserId, ConsentStatus::ACCEPTED, $adminUserId, '2026-02-01');

        $states = $this->repository->fetchAllConsentStates();

        static::assertCount(1, $states);

        static::assertSame('test-admin_user', $states[0]->actor);
        static::assertSame($adminUserId, $states[0]->identifier);
        static::assertSame(ConsentStatus::ACCEPTED, $states[0]->status);
        static::assertSame($blogAnalytics->getName(), $states[0]->name);
        static::assertSame('2026-02-01', $states[0]->revision);
    }

    public function testUpdateSystemConsentState(): void
    {
        $backendData = new BackendData();

        $adminUserId = $this->createUser('test-admin_user');
        $this->repository->updateConsentState($backendData, 'system', ConsentStatus::ACCEPTED, $adminUserId);

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

        $adminUserId = $this->createUser('test-admin_user');
        $this->repository->updateConsentState($tracking, $adminUserId, ConsentStatus::ACCEPTED, $adminUserId, '1.0.0');

        $states = $this->repository->fetchAllConsentStates();

        static::assertCount(1, $states);

        static::assertSame('test-admin_user', $states[0]->actor);
        static::assertSame($adminUserId, $states[0]->identifier);
        static::assertSame(ConsentStatus::ACCEPTED, $states[0]->status);
        static::assertSame('1.0.0', $states[0]->revision);

        $this->repository->updateConsentState($tracking, $adminUserId, ConsentStatus::REVOKED, $adminUserId);

        $states = $this->repository->fetchAllConsentStates();

        static::assertCount(1, $states);

        static::assertSame('test-admin_user', $states[0]->actor);
        static::assertSame($adminUserId, $states[0]->identifier);
        static::assertSame(ConsentStatus::REVOKED, $states[0]->status);
        static::assertNull($states[0]->revision);
    }

    public function testRevokeConsentStateClearsPassedRevision(): void
    {
        $tracking = new BlogAnalytics();

        $adminUserId = $this->createUser('test-admin_user');
        $this->repository->updateConsentState($tracking, $adminUserId, ConsentStatus::REVOKED, $adminUserId, '2.0.0');

        $states = $this->repository->fetchAllConsentStates();

        static::assertCount(1, $states);
        static::assertSame(ConsentStatus::DECLINED, $states[0]->status);
        static::assertNull($states[0]->revision);
    }

    public function testInitializesRevokedConsentsWithDeclinedState(): void
    {
        $tracking = new BlogAnalytics();

        $adminUserId = $this->createUser('test-admin_user');
        $this->repository->updateConsentState($tracking, $adminUserId, ConsentStatus::REVOKED, $adminUserId);

        $states = $this->repository->fetchAllConsentStates();

        static::assertCount(1, $states);

        static::assertSame('test-admin_user', $states[0]->actor);
        static::assertSame($adminUserId, $states[0]->identifier);
        static::assertSame(ConsentStatus::DECLINED, $states[0]->status);
    }

    public function testDoesNotOverrideDeclinedStateWithRevokedState(): void
    {
        $tracking = new BlogAnalytics();

        $adminUserId = $this->createUser('test-admin_user');
        $this->repository->updateConsentState($tracking, $adminUserId, ConsentStatus::REVOKED, $adminUserId);
        $this->repository->updateConsentState($tracking, $adminUserId, ConsentStatus::REVOKED, $adminUserId);

        $states = $this->repository->fetchAllConsentStates();

        static::assertCount(1, $states);

        static::assertSame('test-admin_user', $states[0]->actor);
        static::assertSame($adminUserId, $states[0]->identifier);
        static::assertSame(ConsentStatus::DECLINED, $states[0]->status);
    }

    public function testSetsRevokedStateIfStateWasAccepted(): void
    {
        $tracking = new BlogAnalytics();

        $adminUserId = $this->createUser('test-admin_user');
        $this->repository->updateConsentState($tracking, $adminUserId, ConsentStatus::ACCEPTED, $adminUserId);
        $this->repository->updateConsentState($tracking, $adminUserId, ConsentStatus::REVOKED, $adminUserId);

        $states = $this->repository->fetchAllConsentStates();

        static::assertCount(1, $states);

        static::assertSame('test-admin_user', $states[0]->actor);
        static::assertSame($adminUserId, $states[0]->identifier);
        static::assertSame(ConsentStatus::REVOKED, $states[0]->status);
    }

    public function testFetchAllConsentStates(): void
    {
        $blogAnalytics = new BlogAnalytics();
        $backendData = new BackendData();

        $adminUser1 = $this->createUser('first-admin_user');
        $this->repository->updateConsentState($backendData, 'system', ConsentStatus::ACCEPTED, $adminUser1);

        $adminUser2 = $this->createUser('second-admin_user');
        $this->repository->updateConsentState($blogAnalytics, $adminUser2, ConsentStatus::REVOKED, $adminUser2);

        $result = $this->repository->fetchAllConsentStates();

        static::assertCount(2, $result);
        static::assertContainsOnlyInstancesOf(ConsentStateRecord::class, $result);

        static::assertSame('backend_data', $result[0]->name);
        static::assertSame('system', $result[0]->identifier);
        static::assertSame('first-admin_user', $result[0]->actor);
        static::assertSame(ConsentStatus::ACCEPTED, $result[0]->status);

        static::assertSame($blogAnalytics->getName(), $result[1]->name);
        static::assertSame($adminUser2, $result[1]->identifier);
        static::assertSame('second-admin_user', $result[1]->actor);
        static::assertSame(ConsentStatus::DECLINED, $result[1]->status);
    }

    private function createUser(string $name): string
    {
        $adminUserId = Uuid::randomHex();
        $adminUserRepo = $this->getContainer()->get('admin_user.repository');

        $adminUserRepo->create([
            [
                'id' => $adminUserId,
                'username' => $name,
                'name' => 'Test',
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

        return $adminUserId;
    }
}
