<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Elasticsearch\Admin\Indexer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Event\NestedEventCollection;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\User\UserDefinition;
use Contena\Core\System\User\UserEntity;
use Contena\Elasticsearch\Admin\Indexer\UserAdminSearchIndexer;

/**
 * @internal
 */
#[CoversClass(UserAdminSearchIndexer::class)]
class UserAdminSearchIndexerTest extends TestCase
{
    private UserAdminSearchIndexer $searchIndexer;

    protected function setUp(): void
    {
        $this->searchIndexer = new UserAdminSearchIndexer(
            static::createStub(Connection::class),
            static::createStub(IteratorFactory::class),
            static::createStub(EntityRepository::class),
            100
        );
    }

    public function testGetUpdatedIds(): void
    {
        $indexer = new UserAdminSearchIndexer(
            static::createStub(Connection::class),
            static::createStub(IteratorFactory::class),
            static::createStub(EntityRepository::class),
            100
        );

        $userId = Uuid::randomHex();

        $event = new EntityWrittenContainerEvent(
            Context::createDefaultContext(),
            new NestedEventCollection([
            ]),
            []
        );

        static::assertSame([$userId], $indexer->getUpdatedIds($event));
    }

    public function testGetEntity(): void
    {
        static::assertSame(UserDefinition::ENTITY_NAME, $this->searchIndexer->getEntity());
    }

    public function testGetName(): void
    {
        static::assertSame('user-listing', $this->searchIndexer->getName());
    }

    public function testGetDecoratedShouldThrowException(): void
    {
        static::expectException(DecorationPatternException::class);
        $this->searchIndexer->getDecorated();
    }

    public function testGlobalData(): void
    {
        $context = Context::createDefaultContext();
        $repository = static::createStub(EntityRepository::class);
        $user = new UserEntity();
        $user->setUniqueIdentifier(Uuid::randomHex());
        $repository->method('search')->willReturn(
            new EntitySearchResult(
                'user',
                1,
                new EntityCollection([$user]),
                null,
                new Criteria(),
                $context
            )
        );

        $indexer = new UserAdminSearchIndexer(
            static::createStub(Connection::class),
            static::createStub(IteratorFactory::class),
            $repository,
            100
        );

        $result = [
            'total' => 1,
            'hits' => [
                ['id' => '809c1844f4734243b6aa04aba860cd45'],
            ],
        ];

        $data = $indexer->globalData($result, $context);

        static::assertSame($result['total'], $data['total']);
    }

    public function testFetching(): void
    {
        $connection = $this->getConnection();

        $indexer = new UserAdminSearchIndexer(
            $connection,
            static::createStub(IteratorFactory::class),
            static::createStub(EntityRepository::class),
            100
        );

        $id = '809c1844f4734243b6aa04aba860cd45';
        $documents = $indexer->fetch([$id]);

        static::assertArrayHasKey($id, $documents);

        /** @var array<string, mixed> $document */
        $document = $documents[$id];

        static::assertSame($id, $document['id']);
        static::assertSame('firstname lastname test@example.com 12345 test tag viet nam da nang street 123 test test 809c1844f4734243b6aa04aba860cd45', $document['text']);
        static::assertTrue($document['active']);
        static::assertSame('test@example.com', $document['email']);
        static::assertSame('firstname', $document['firstName']);
        static::assertSame('lastname', $document['lastName']);
        static::assertSame('12345', $document['userNumber']);
        static::assertIsArray($document['defaultBillingAddress']);
        static::assertSame('bb11223344556677889900aabbccddee', $document['defaultBillingAddress']['id']);
        static::assertSame('cc11223344556677889900aabbccddee', $document['defaultBillingAddress']['countryId']);
        static::assertIsArray($document['defaultShippingAddress']);
        static::assertSame('dd11223344556677889900aabbccddee', $document['defaultShippingAddress']['id']);
        static::assertSame('ee11223344556677889900aabbccddee', $document['defaultShippingAddress']['countryId']);
        static::assertIsArray($document['tags']);
    }

    private function getConnection(): Connection
    {
        $connection = static::createStub(Connection::class);

        $connection->method('fetchAllAssociative')->willReturn(
            [
                [
                    'id' => '809c1844f4734243b6aa04aba860cd45',
                    'tags' => 'test Tag',
                    'tagIds' => 'a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6',
                    'country' => 'Viet Nam',
                    'address_first_name' => null,
                    'address_last_name' => null,
                    'address_company' => null,
                    'city' => 'Da Nang',
                    'zipcode' => null,
                    'street' => 'street',
                    'phone_number' => '123',
                    'additional_address_line1' => 'test',
                    'additional_address_line2' => 'test',
                    'first_name' => 'firstname',
                    'last_name' => 'lastname',
                    'email' => 'test@example.com',
                    'company' => null,
                    'user_number' => '12345',
                    'active' => 1,
                    'affiliateCode' => null,
                    'campaignCode' => null,
                    'groupId' => 'aabbccdd11223344556677889900aabb',
                    'salutationId' => null,
                    'boundChannelId' => null,
                    'requestedGroupId' => null,
                    'defaultBillingAddressId' => 'bb11223344556677889900aabbccddee',
                    'defaultBillingAddressCountryId' => 'cc11223344556677889900aabbccddee',
                    'defaultShippingAddressId' => 'dd11223344556677889900aabbccddee',
                    'defaultShippingAddressCountryId' => 'ee11223344556677889900aabbccddee',
                    'createdAt' => '2024-01-01 00:00:00.000',
                ],
            ],
        );

        return $connection;
    }
}
