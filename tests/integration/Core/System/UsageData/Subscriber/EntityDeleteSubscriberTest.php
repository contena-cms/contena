<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\UsageData\Subscriber;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Test\Blog\BlogBuilder;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\AdminUser\AdminUserCollection;
use Contena\Core\System\Consent\ConsentStatus;
use Contena\Core\System\Consent\Definition\BackendData;
use Contena\Core\System\Consent\Service\ConsentService;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Test\TestDefaults;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
class EntityDeleteSubscriberTest extends TestCase
{
    use ClockSensitiveTrait;
    use IntegrationTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);

        /** @var MockHttpClient $client */
        $client = static::getContainer()->get('contena.usage_data.gateway.client');
        $client->setResponseFactory(static function (string $method, string $url): ResponseInterface {
            if (\str_ends_with($url, '/killswitch')) {
                $body = json_encode(['killswitch' => false]);
                static::assertIsString($body);

                return new MockResponse($body);
            }

            return new MockResponse();
        });
    }

    public function testHandleDeleteEventWritesSinglePrimaryKeyToDatabase(): void
    {
        static::mockTime(new \DateTimeImmutable('2023-08-30 00:00:00.000'));
        $this->setConsentState(ConsentStatus::ACCEPTED);

        $blogIds = new IdsCollection();

        $blogBuilder = new BlogBuilder($blogIds, 'blog-to-delete', 1);
        $blogBuilder->price(3.14);

        /** @var EntityRepository<BlogCollection> $blogRepository */
        $blogRepository = static::getContainer()->get('blog.repository');

        $blogRepository->create([$blogBuilder->build()], Context::createDefaultContext());

        $blogRepository->delete([['id' => $blogIds->get('blog-to-delete')]], Context::createDefaultContext());

        $result = $this->connection->executeQuery('SELECT `entity_name`, `entity_ids`, `deleted_at` FROM `usage_data_entity_deletion`')->fetchAssociative();

        static::assertIsArray($result);
        static::assertArrayHasKey('entity_ids', $result);
        static::assertIsString($result['entity_ids']);

        $result['entity_ids'] = \json_decode($result['entity_ids'], true, flags: \JSON_THROW_ON_ERROR);

        static::assertSame([
            'entity_name' => 'blog',
            'entity_ids' => ['id' => $blogIds->get('blog-to-delete')],
            'deleted_at' => '2023-08-30 00:00:00.000',
        ], $result);
    }

    public function testDoesNotTriggerWhenDeletingNonLiveVersionSinglePrimaryKey(): void
    {
        static::mockTime(new \DateTimeImmutable('2023-08-30 00:00:00.000'));
        $this->setConsentState(ConsentStatus::REVOKED);

        $blogIds = new IdsCollection();

        $blogBuilder = new BlogBuilder($blogIds, 'blog-to-delete', 1);
        $blogBuilder->price(3.14);
        $blog = $blogBuilder->build();
        // non live version should not trigger the subscriber
        $blog['versionId'] = Uuid::randomHex();

        /** @var EntityRepository<BlogCollection> $blogRepository */
        $blogRepository = static::getContainer()->get('blog.repository');

        $blogRepository->create([$blog], Context::createDefaultContext());

        $blogRepository->delete([['id' => $blogIds->get('blog-to-delete')]], Context::createDefaultContext());

        $result = $this->connection->executeQuery('SELECT `entity_name`, `entity_ids`, `deleted_at` FROM `usage_data_entity_deletion`')->fetchAssociative();

        // no blog should have been written to the DB as no live version was deleted
        static::assertFalse($result);
    }

    public function testDoesNotTriggerWhenDeletingNonLiveVersionCombinedPrimaryKeys(): void
    {
        static::mockTime(new \DateTimeImmutable('2023-08-30 00:00:00.000'));
        $this->setConsentState(ConsentStatus::REVOKED);

        $idsCollection = new IdsCollection();

        // at least one has no live version --> should not trigger the subscriber
        $blog = $this->insertTestBlog($idsCollection, Uuid::randomHex());
        $category = $this->insertTestCategory($idsCollection, Defaults::LIVE_VERSION);

        /** @var EntityRepository<EntityCollection<Entity>> $blogCategoryRepository */
        $blogCategoryRepository = static::getContainer()->get('blog_category.repository');
        $blogCategoryRepository->create([
            [
                'blogId' => $blog['id'],
                'blogVersionId' => $blog['versionId'],
                'categoryId' => $category['id'],
                'categoryVersionId' => $category['versionId'],
            ],
        ], Context::createDefaultContext());

        $blogCategoryRepository->delete([
            [
                'blogId' => $blog['id'],
                'categoryId' => $category['id'],
            ],
        ], Context::createDefaultContext());

        $result = $this->connection->executeQuery('SELECT `entity_name`, `entity_ids`, `deleted_at` FROM `usage_data_entity_deletion`')->fetchAssociative();
        static::assertFalse($result);
    }

    public function testHandleDeleteEventWritesCombinedPrimaryKeysToDatabase(): void
    {
        static::mockTime(new \DateTimeImmutable('2023-08-30 00:00:00.000'));

        $this->setConsentState(ConsentStatus::ACCEPTED);

        $adminUserId = Uuid::randomHex();
        $adminUserData = [
            'id' => $adminUserId,
            'email' => 'foo@bar.com',
            'name' => 'Firstname',
            'lastName' => 'Lastname',
            'password' => TestDefaults::HASHED_PASSWORD,
            'username' => 'foobar',
            'localeId' => static::getContainer()->get(Connection::class)->fetchOne('SELECT LOWER(HEX(id)) FROM locale LIMIT 1'),
        ];

        /** @var EntityRepository<AdminUserCollection> $adminUserRepository */
        $adminUserRepository = static::getContainer()->get('admin_user.repository');
        $adminUserRepository->create([$adminUserData], Context::createDefaultContext());

        $aclRoleId = Uuid::randomHex();
        $aclRoleData = [
            'id' => $aclRoleId,
            'name' => 'foobar',
            'privileges' => [
                'admin_user:read',
                'admin_user:create',
                'admin_user:update',
                'admin_user:delete',
            ],
        ];

        $aclRoleRepository = static::getContainer()->get('acl_role.repository');
        $aclRoleRepository->create([$aclRoleData], Context::createDefaultContext());

        /** @var EntityRepository<EntityCollection<Entity>> $aclUserRoleRepository */
        $aclUserRoleRepository = static::getContainer()->get('acl_user_role.repository');
        $aclUserRoleRepository->create([
            [
                'admin_userId' => $adminUserId,
                'aclRoleId' => $aclRoleId,
            ],
        ], Context::createDefaultContext());

        $aclUserRoleRepository->delete([
            [
                'admin_userId' => $adminUserId,
                'aclRoleId' => $aclRoleId,
            ],
        ], Context::createDefaultContext());

        $result = $this->connection->executeQuery('SELECT `entity_name`, `entity_ids`, `deleted_at` FROM `usage_data_entity_deletion`')->fetchAssociative();
        static::assertIsArray($result);
        static::assertArrayHasKey('entity_ids', $result);
        static::assertIsString($result['entity_ids']);

        $result['entity_ids'] = \json_decode($result['entity_ids'], true, flags: \JSON_THROW_ON_ERROR);

        static::assertSame([
            'entity_name' => 'acl_user_role',
            'entity_ids' => [
                'admin_userId' => $adminUserId,
                'aclRoleId' => $aclRoleId,
            ],
            'deleted_at' => '2023-08-30 00:00:00.000',
        ], $result);
    }

    /**
     * @return array<mixed>
     */
    private function insertTestBlog(IdsCollection $idsCollection, string $versionId): array
    {
        $blogBuilder = new BlogBuilder($idsCollection, 'blog-1', 1);
        $blogBuilder->price(3.14);

        /** @var EntityRepository<BlogCollection> $blogRepository */
        $blogRepository = static::getContainer()->get('blog.repository');

        $blog = $blogBuilder->build();
        $blog['versionId'] = $versionId;

        $blogRepository->create([$blog], Context::createDefaultContext());

        return $blog;
    }

    /**
     * @return array{id: string, name: string, versionId: string}
     */
    private function insertTestCategory(IdsCollection $idsCollection, string $versionId): array
    {
        $categoryRepository = static::getContainer()->get('category.repository');

        $category = [
            'id' => $idsCollection->get('category-1'),
            'name' => 'category',
            'versionId' => $versionId,
        ];

        $categoryRepository->create([$category], Context::createDefaultContext());

        return $category;
    }

    private function setConsentState(ConsentStatus $status): void
    {
        $this->connection->executeStatement(
            'DELETE FROM consent_state WHERE name = :name AND identifier = :identifier',
            ['name' => BackendData::NAME, 'identifier' => 'system']
        );

        if ($status !== ConsentStatus::ACCEPTED) {
            static::getContainer()->get(ConsentService::class)->reset();

            return;
        }

        $this->connection->executeStatement(
            'INSERT INTO consent_state (id, name, identifier, state, actor, updated_at)
            VALUES (:id, :name, :identifier, :state, :actor, :updatedAt)',
            [
                'id' => Uuid::randomBytes(),
                'name' => BackendData::NAME,
                'identifier' => 'system',
                'state' => 'accepted',
                'actor' => 'test',
                'updatedAt' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ],
            ['id' => ParameterType::BINARY]
        );

        static::getContainer()->get(ConsentService::class)->reset();
    }
}
