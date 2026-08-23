<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Elasticsearch\Admin\Indexer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Event\NestedEventCollection;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Member\MemberDefinition;
use Contena\Core\System\Member\MemberEntity;
use Contena\Elasticsearch\Admin\Indexer\MemberAdminSearchIndexer;

/**
 * @internal
 */
#[CoversClass(MemberAdminSearchIndexer::class)]
class MemberAdminSearchIndexerTest extends TestCase
{
    private MemberAdminSearchIndexer $searchIndexer;

    protected function setUp(): void
    {
        $this->searchIndexer = new MemberAdminSearchIndexer(
            static::createStub(Connection::class),
            static::createStub(IteratorFactory::class),
            static::createStub(EntityRepository::class),
            100
        );
    }

    public function testGetUpdatedIds(): void
    {
        $indexer = new MemberAdminSearchIndexer(
            static::createStub(Connection::class),
            static::createStub(IteratorFactory::class),
            static::createStub(EntityRepository::class),
            100
        );

        $memberId = Uuid::randomHex();

        /** @var NestedEventCollection<EntityWrittenEvent<string|array<string, string>>> $events */
        $events = new NestedEventCollection([
            new EntityWrittenEvent('member', [
                new EntityWriteResult($memberId, ['name' => 'Jane Doe'], 'member', EntityWriteResult::OPERATION_UPDATE),
            ], Context::createDefaultContext()),
            new EntityWrittenEvent('member_address', [
                new EntityWriteResult(Uuid::randomHex(), ['memberId' => $memberId, 'firstName' => 'A'], 'member_address', EntityWriteResult::OPERATION_UPDATE),
            ], Context::createDefaultContext()),
            new EntityWrittenEvent('member_tag', [
                new EntityWriteResult(['memberId' => $memberId, 'tagId' => Uuid::randomHex()], ['tagId' => Uuid::randomHex()], 'member_tag', EntityWriteResult::OPERATION_UPDATE),
            ], Context::createDefaultContext()),
        ]);
        $event = new EntityWrittenContainerEvent(
            Context::createDefaultContext(),
            $events,
            []
        );

        static::assertSame([$memberId], $indexer->getUpdatedIds($event));
    }

    public function testGetEntity(): void
    {
        static::assertSame(MemberDefinition::ENTITY_NAME, $this->searchIndexer->getEntity());
    }

    public function testGetName(): void
    {
        static::assertSame('member-listing', $this->searchIndexer->getName());
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
        $member = new MemberEntity();
        $member->setUniqueIdentifier(Uuid::randomHex());
        $repository->method('search')->willReturn(
            new EntitySearchResult(
                1,
                new EntityCollection([$member]),
                null,
                new Criteria(),
                $context
            )
        );

        $indexer = new MemberAdminSearchIndexer(
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

        $indexer = new MemberAdminSearchIndexer(
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
        static::assertSame('tenant-a', $document['tenantId']);
        static::assertSame('member name 987654321 test@example.com 12345 test tag viet nam ha noi address firstname address lastname da nang street 550000 123 line 1 line 2 809c1844f4734243b6aa04aba860cd45', $document['text']);
        static::assertSame(['member name', '987654321', 'test@example.com'], $document['completion']);
        static::assertTrue($document['active']);
        static::assertSame('test@example.com', $document['email']);
        static::assertSame('Member Name', $document['name']);
        static::assertSame('987654321', $document['phoneNumber']);
        static::assertSame('12345', $document['memberNumber']);
        static::assertSame('aabbccdd11223344556677889900aabb', $document['groupId']);
        static::assertSame('bbccddaa11223344556677889900aabb', $document['channelId']);
        static::assertSame('ccddaabb11223344556677889900aabb', $document['languageId']);
        static::assertSame('ddaabbcc11223344556677889900aabb', $document['requestedGroupId']);
        static::assertSame([['id' => 'a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6', '_count' => 1]], $document['tags']);
    }

    private function getConnection(): Connection
    {
        $connection = static::createStub(Connection::class);

        $connection->method('fetchAllAssociative')->willReturn(
            [
                [
                    'id' => '809c1844f4734243b6aa04aba860cd45',
                    'tenantId' => 'tenant-a',
                    'tags' => 'test Tag',
                    'tagIds' => 'a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6',
                    'country' => 'Viet Nam',
                    'region' => 'Ha Noi',
                    'address_first_name' => 'Address Firstname',
                    'address_last_name' => 'Address Lastname',
                    'city' => 'Da Nang',
                    'zipcode' => '550000',
                    'street' => 'street',
                    'phone_number' => '123',
                    'additional_address_line1' => 'Line 1',
                    'additional_address_line2' => 'Line 2',
                    'name' => 'Member Name',
                    'member_phone_number' => '987654321',
                    'email' => 'test@example.com',
                    'member_number' => '12345',
                    'active' => 1,
                    'groupId' => 'aabbccdd11223344556677889900aabb',
                    'channelId' => 'bbccddaa11223344556677889900aabb',
                    'languageId' => 'ccddaabb11223344556677889900aabb',
                    'requestedGroupId' => 'ddaabbcc11223344556677889900aabb',
                    'createdAt' => '2024-01-01 00:00:00.000',
                ],
            ],
        );

        return $connection;
    }
}
