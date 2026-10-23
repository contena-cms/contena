<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_6;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Migration\V6_6\Migration1731576063UpdateBlogComparisonTemplate;
use Contena\Core\Test\TestDefaults;

/**
 * @internal
 */
#[CoversClass(Migration1731576063UpdateBlogComparisonTemplate::class)]
class Migration1731576063UpdateBlogComparisonTemplateTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        $migration = new Migration1731576063UpdateBlogComparisonTemplate();
        static::assertSame(1731576063, $migration->getCreationTimestamp());
    }

    public function testMigrationOverridesTemplates(): void
    {
        $fixturePath = __DIR__ . '/../../../../src/Core/Migration/Fixtures/blogComparison-export-profiles/next-39314/';
        $templateOld = file_get_contents($fixturePath . 'google_old.xml.twig');
        $templateExpected = file_get_contents($fixturePath . 'google_new.xml.twig');

        static::assertNotFalse($templateOld);
        static::assertNotFalse($templateExpected);

        $affectedTemplateId = $this->prepareOldDatabaseEntry($templateOld);

        $migration = new Migration1731576063UpdateBlogComparisonTemplate();
        $migration->update($this->connection);

        $currentEntry = $this->getCurrentBodyAndUpdateTimestamp($affectedTemplateId);

        static::assertNotFalse($currentEntry);
        static::assertSame($templateExpected, $currentEntry['body']);
        static::assertNotNull($currentEntry['updatedAt']);
    }

    /**
     * @return array<string, string>|false
     */
    private function getCurrentBodyAndUpdateTimestamp(string $id): array|false
    {
        $getBlogExportSQL = <<<'SQL'
            SELECT body_template AS body, updated_at AS updatedAt
            FROM blog_export
            WHERE id = ?
        SQL;

        return $this->connection->fetchAssociative($getBlogExportSQL, [$id]);
    }

    private function prepareOldDatabaseEntry(string $body): string
    {
        $id = Uuid::randomBytes();
        $contentStreamId = Uuid::randomBytes();

        $this->connection->insert('content_stream', [
            'id' => $contentStreamId,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $this->connection->insert(
            'blog_export',
            [
                'id' => $id,
                'content_stream_id' => $contentStreamId,
                'channel_id' => Uuid::fromHexToBytes(TestDefaults::CHANNEL),
                'file_name' => Uuid::randomHex(),
                'access_key' => 'SWPERXF3DUTXS0JGRWRWWDHMTA',
                'encoding' => 'UTF-8',
                'file_format' => 'test',
                '`interval`' => 300,
                'body_template' => $body,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'currency_id' => Uuid::fromHexToBytes(Defaults::CURRENCY),
            ],
            [
                'id' => 'binary',
                'content_stream_id' => 'binary',
                'channel_id' => 'binary',
                'currency_id' => 'binary',
            ]
        );

        return $id;
    }
}
