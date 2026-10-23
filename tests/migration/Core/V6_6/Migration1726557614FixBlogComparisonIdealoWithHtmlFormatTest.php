<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_6;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Migration\V6_6\Migration1726557614FixBlogComparisonIdealoWithHtmlFormat;
use Contena\Core\Test\TestDefaults;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
#[CoversClass(Migration1726557614FixBlogComparisonIdealoWithHtmlFormat::class)]
class Migration1726557614FixBlogComparisonIdealoWithHtmlFormatTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1726557614, (new Migration1726557614FixBlogComparisonIdealoWithHtmlFormat())->getCreationTimestamp());
    }

    public function testCreationTimestamp(): void
    {
        $migration = new Migration1726557614FixBlogComparisonIdealoWithHtmlFormat();
        static::assertSame(1726557614, $migration->getCreationTimestamp());
    }

    /**
     * @param array{oldTemplate: string, expectedTemplate: string} $testData
     */
    #[DataProvider('dataProvider')]
    public function testMigration(array $testData): void
    {
        $currentEntryId = $this->prepareOldDatabaseEntry($testData['oldTemplate']);

        $migration = new Migration1726557614FixBlogComparisonIdealoWithHtmlFormat();
        $migration->update($this->connection);
        $migration->update($this->connection);

        /** @var array{body: string, updatedAt: string|null} $currentEntry */
        $currentEntry = $this->getCurrentBodyAndUpdateTimestamp($currentEntryId);

        static::assertSame($testData['expectedTemplate'], $currentEntry['body']);
        if ($testData['expectedTemplate'] === $testData['oldTemplate']) {
            static::assertNull($currentEntry['updatedAt']);
        }
    }

    /**
     * @return iterable<string, array{array{oldTemplate: string, expectedTemplate: string}}>
     */
    public static function dataProvider(): iterable
    {
        /** @var string $oldTemplate */
        $oldTemplate = file_get_contents(__DIR__ . '/../../../../src/Core/Migration/Fixtures/blogComparison-export-profiles/next-37658/old-template-idealo.csv.twig');
        /** @var string $newTemplate */
        $newTemplate = file_get_contents(__DIR__ . '/../../../../src/Core/Migration/Fixtures/blogComparison-export-profiles/next-37658/new-template-idealo.csv.twig');

        yield 'provider old template do not update for expected' => [['oldTemplate' => 'do_not_update_for_modified_template', 'expectedTemplate' => 'do_not_update_for_modified_template']];
        yield 'provider old template old template expected template template' => [['oldTemplate' => $oldTemplate, 'expectedTemplate' => $newTemplate]];
    }

    /**
     * @return array<string, mixed>|false
     */
    private function getCurrentBodyAndUpdateTimestamp(string $id): array|false
    {
        $SQL = <<<'SQL'
            SELECT body_template AS body, updated_at AS updatedAt
            FROM blog_export
            WHERE id = ?
        SQL;

        return $this->connection->fetchAssociative($SQL, [$id]);
    }

    private function prepareOldDatabaseEntry(string $body): string
    {
        $id = Uuid::randomBytes();
        $contentStreamID = Uuid::randomBytes();

        $this->connection->insert('content_stream', [
            'id' => $contentStreamID,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $this->connection->insert(
            'blog_export',
            [
                'id' => $id,
                'content_stream_id' => $contentStreamID,
                'channel_id' => Uuid::fromHexToBytes(TestDefaults::CHANNEL),
                'file_name' => Uuid::randomHex(),
                'access_key' => 'SWPEM3BKR1DJWNHEYTJQQU84AQ',
                'encoding' => 'UTF-8',
                'file_format' => 'CSV',
                '`interval`' => 300,
                'body_template' => $body,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'currency_id' => Uuid::fromHexToBytes(Defaults::CURRENCY),
            ],
            [
                'id' => Types::BINARY,
                'content_stream_id' => Types::BINARY,
                'channel_id' => Types::BINARY,
                'currency_id' => Types::BINARY,
            ]
        );

        return $id;
    }
}
