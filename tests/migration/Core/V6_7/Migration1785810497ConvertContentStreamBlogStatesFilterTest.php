<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Migration\IndexerQueuer;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Migration\V6_7\Migration1785810497ConvertBlogStreamBlogStatesFilter;

/**
 * @internal
 */
#[CoversClass(Migration1785810497ConvertBlogStreamBlogStatesFilter::class)]
class Migration1785810497ConvertBlogStreamBlogStatesFilterTest extends TestCase
{
    private Connection $connection;

    private string $streamId;

    private string $simpleFilterId;

    private string $qualifiedFilterId;

    private string $partiallyUnknownFilterId;

    private string $unknownFilterId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();
        $this->streamId = Uuid::randomBytes();
        $this->simpleFilterId = Uuid::randomBytes();
        $this->qualifiedFilterId = Uuid::randomBytes();
        $this->partiallyUnknownFilterId = Uuid::randomBytes();
        $this->unknownFilterId = Uuid::randomBytes();
    }

    protected function tearDown(): void
    {
        $this->connection->delete('content_stream_filter', ['id' => $this->simpleFilterId]);
        $this->connection->delete('content_stream_filter', ['id' => $this->qualifiedFilterId]);
        $this->connection->delete('content_stream_filter', ['id' => $this->partiallyUnknownFilterId]);
        $this->connection->delete('content_stream_filter', ['id' => $this->unknownFilterId]);
        $this->connection->delete('content_stream', ['id' => $this->streamId]);

        parent::tearDown();
    }

    public function testGetCreationTimestamp(): void
    {
        $migration = new Migration1785810497ConvertBlogStreamBlogStatesFilter();

        static::assertSame(1785810497, $migration->getCreationTimestamp());
    }

    public function testUpdateConvertsLegacyFilters(): void
    {
        $createdAt = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $this->connection->insert('content_stream', [
            'id' => $this->streamId,
            'api_filter' => null,
            'invalid' => 0,
            'created_at' => $createdAt,
            'updated_at' => null,
        ]);

        $this->insertFilter($this->simpleFilterId, 1, 'states', 'is-download|is-physical', $createdAt);
        $this->insertFilter($this->qualifiedFilterId, 2, 'blog.states', 'is-download', $createdAt);
        $this->insertFilter($this->partiallyUnknownFilterId, 3, 'blog.states', 'is-physical|is-legacy', $createdAt);
        $this->insertFilter($this->unknownFilterId, 4, 'states', 'is-legacy-only', $createdAt);

        $migration = new Migration1785810497ConvertBlogStreamBlogStatesFilter();

        // make sure the migration is idempotent
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertSame(['type', 'digital|physical'], $this->fetchFilter($this->simpleFilterId));
        static::assertSame(['blog.type', 'digital'], $this->fetchFilter($this->qualifiedFilterId));

        // filters mentioning a state without a blog type counterpart are left untouched for manual review
        static::assertSame(['blog.states', 'is-physical|is-legacy'], $this->fetchFilter($this->partiallyUnknownFilterId));
        static::assertSame(['states', 'is-legacy-only'], $this->fetchFilter($this->unknownFilterId));

        $indexers = (new IndexerQueuer($this->connection))->getIndexers();
        static::assertArrayHasKey('content_stream.indexer', $indexers);
    }

    private function insertFilter(string $id, int $position, string $field, string $value, string $createdAt): void
    {
        $this->connection->insert('content_stream_filter', [
            'id' => $id,
            'content_stream_id' => $this->streamId,
            'parent_id' => null,
            'type' => 'equalsAny',
            'field' => $field,
            'operator' => null,
            'value' => $value,
            'parameters' => null,
            'position' => $position,
            'custom_fields' => null,
            'created_at' => $createdAt,
            'updated_at' => null,
        ]);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function fetchFilter(string $id): array
    {
        $filter = $this->connection->fetchAssociative(
            'SELECT `field`, `value` FROM `content_stream_filter` WHERE `id` = :id',
            ['id' => $id]
        );

        static::assertIsArray($filter);
        static::assertIsString($filter['field']);
        static::assertIsString($filter['value']);

        return [$filter['field'], $filter['value']];
    }
}
