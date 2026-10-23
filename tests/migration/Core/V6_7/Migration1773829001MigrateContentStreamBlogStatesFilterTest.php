<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Migration\IndexerQueuer;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Migration\V6_7\Migration1773829001MigrateBlogStreamBlogStatesFilter;

/**
 * @internal
 */
#[CoversClass(Migration1773829001MigrateBlogStreamBlogStatesFilter::class)]
class Migration1773829001MigrateBlogStreamBlogStatesFilterTest extends TestCase
{
    private Connection $connection;

    private string $streamId;

    private string $simpleFilterId;

    private string $qualifiedFilterId;

    private string $fallbackFilterId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();
        $this->streamId = Uuid::randomBytes();
        $this->simpleFilterId = Uuid::randomBytes();
        $this->qualifiedFilterId = Uuid::randomBytes();
        $this->fallbackFilterId = Uuid::randomBytes();
    }

    protected function tearDown(): void
    {
        $this->connection->delete('content_stream_filter', ['id' => $this->simpleFilterId]);
        $this->connection->delete('content_stream_filter', ['id' => $this->qualifiedFilterId]);
        $this->connection->delete('content_stream_filter', ['id' => $this->fallbackFilterId]);
        $this->connection->delete('content_stream', ['id' => $this->streamId]);

        parent::tearDown();
    }

    public function testGetCreationTimestamp(): void
    {
        $migration = new Migration1773829001MigrateBlogStreamBlogStatesFilter();

        static::assertSame(1773829001, $migration->getCreationTimestamp());
    }

    public function testUpdateIsNoOp(): void
    {
        $createdAt = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $this->connection->insert('content_stream', [
            'id' => $this->streamId,
            'api_filter' => null,
            'invalid' => 0,
            'created_at' => $createdAt,
            'updated_at' => null,
        ]);

        $this->connection->insert('content_stream_filter', [
            'id' => $this->simpleFilterId,
            'content_stream_id' => $this->streamId,
            'parent_id' => null,
            'type' => 'equalsAny',
            'field' => 'states',
            'operator' => null,
            'value' => 'is-download',
            'parameters' => null,
            'position' => 1,
            'custom_fields' => null,
            'created_at' => $createdAt,
            'updated_at' => null,
        ]);

        $migration = new Migration1773829001MigrateBlogStreamBlogStatesFilter();
        $migration->update($this->connection);

        // update() must not convert filters, conversion was moved to updateDestructive()
        static::assertSame(
            'states',
            $this->connection->fetchOne(
                'SELECT `field` FROM `content_stream_filter` WHERE `id` = :id',
                ['id' => $this->simpleFilterId]
            )
        );
    }

    public function testMigration(): void
    {
        $createdAt = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $this->connection->insert('content_stream', [
            'id' => $this->streamId,
            'api_filter' => null,
            'invalid' => 0,
            'created_at' => $createdAt,
            'updated_at' => null,
        ]);

        $this->connection->insert('content_stream_filter', [
            'id' => $this->simpleFilterId,
            'content_stream_id' => $this->streamId,
            'parent_id' => null,
            'type' => 'equalsAny',
            'field' => 'states',
            'operator' => null,
            'value' => 'is-download|is-physical',
            'parameters' => null,
            'position' => 1,
            'custom_fields' => null,
            'created_at' => $createdAt,
            'updated_at' => null,
        ]);

        $this->connection->insert('content_stream_filter', [
            'id' => $this->qualifiedFilterId,
            'content_stream_id' => $this->streamId,
            'parent_id' => null,
            'type' => 'equalsAny',
            'field' => 'blog.states',
            'operator' => null,
            'value' => 'is-physical|is-legacy',
            'parameters' => null,
            'position' => 2,
            'custom_fields' => null,
            'created_at' => $createdAt,
            'updated_at' => null,
        ]);

        $this->connection->insert('content_stream_filter', [
            'id' => $this->fallbackFilterId,
            'content_stream_id' => $this->streamId,
            'parent_id' => null,
            'type' => 'equalsAny',
            'field' => 'states',
            'operator' => null,
            'value' => 'is-legacy-only',
            'parameters' => null,
            'position' => 3,
            'custom_fields' => null,
            'created_at' => $createdAt,
            'updated_at' => null,
        ]);

        $migration = new Migration1773829001MigrateBlogStreamBlogStatesFilter();

        // make sure the migration is idempotent
        $migration->updateDestructive($this->connection);
        $migration->updateDestructive($this->connection);

        $simpleFilter = $this->connection->fetchAssociative(
            'SELECT `field`, `value` FROM `content_stream_filter` WHERE `id` = :id',
            ['id' => $this->simpleFilterId]
        );

        static::assertIsArray($simpleFilter);
        static::assertSame('type', $simpleFilter['field']);
        static::assertSame('digital|physical', $simpleFilter['value']);

        $qualifiedFilter = $this->connection->fetchAssociative(
            'SELECT `field`, `value` FROM `content_stream_filter` WHERE `id` = :id',
            ['id' => $this->qualifiedFilterId]
        );

        static::assertIsArray($qualifiedFilter);
        static::assertSame('blog.states', $qualifiedFilter['field']);
        static::assertSame('is-physical|is-legacy', $qualifiedFilter['value']);

        $fallbackFilter = $this->connection->fetchAssociative(
            'SELECT `field`, `value` FROM `content_stream_filter` WHERE `id` = :id',
            ['id' => $this->fallbackFilterId]
        );

        static::assertIsArray($fallbackFilter);
        static::assertSame('states', $fallbackFilter['field']);
        static::assertSame('is-legacy-only', $fallbackFilter['value']);

        static::assertSame(
            '2',
            (string) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM `content_stream_filter` WHERE `content_stream_id` = :streamId AND `field` IN (:fields)',
                ['streamId' => $this->streamId, 'fields' => ['states', 'blog.states']],
                ['fields' => ArrayParameterType::STRING]
            )
        );

        $indexers = (new IndexerQueuer($this->connection))->getIndexers();
        static::assertArrayHasKey('content_stream.indexer', $indexers);
    }
}
