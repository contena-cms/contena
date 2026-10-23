<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Migration\V6_7\Migration1780029093FixBlogComparisonTemplateBreadcrumb;
use Contena\Core\Test\TestDefaults;

/**
 * @internal
 */
#[CoversClass(Migration1780029093FixBlogComparisonTemplateBreadcrumb::class)]
class Migration1780029093FixBlogComparisonTemplateBreadcrumbTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const FIXTURES = __DIR__ . '/../../../../src/Core/Migration/Fixtures/blogComparison-export-profiles/issue-12852/';

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        $migration = new Migration1780029093FixBlogComparisonTemplateBreadcrumb();
        static::assertSame(1780029093, $migration->getCreationTimestamp());
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function vendorProvider(): iterable
    {
        // The bool mirrors the per-vendor `body.trim()` behaviour in
        // src/Administration/.../blog-export-templates/{vendor}/index.js.
        yield 'google' => ['google.xml', false];
        yield 'idealo' => ['idealo.csv', true];
        yield 'billiger' => ['billiger.csv', true];
    }

    #[DataProvider('vendorProvider')]
    public function testMigrationUpgradesUnmodifiedTemplate(string $name, bool $adminTrims): void
    {
        [$base, $ext] = explode('.', $name);
        $pre = $this->readFixture($base . '_old.' . $ext . '.twig');
        $post = $this->readFixture($base . '_new.' . $ext . '.twig');
        if ($adminTrims) {
            $pre = trim($pre);
            $post = trim($post);
        }
        static::assertNotSame($pre, $post, 'fixture sanity: pre and post must differ for ' . $name);

        $id = $this->prepareOldDatabaseEntry($pre);

        (new Migration1780029093FixBlogComparisonTemplateBreadcrumb())->update($this->connection);

        $row = $this->getCurrentBodyAndUpdateTimestamp($id);
        static::assertNotFalse($row);
        static::assertSame($post, $row['body']);
        static::assertNotNull($row['updatedAt']);
    }

    public function testMigrationLeavesUserModifiedTemplatesAlone(): void
    {
        $customTemplate = "<item>{{ blog.id }} -- customized</item>\n";
        $id = $this->prepareOldDatabaseEntry($customTemplate);

        (new Migration1780029093FixBlogComparisonTemplateBreadcrumb())->update($this->connection);

        $row = $this->getCurrentBodyAndUpdateTimestamp($id);
        static::assertNotFalse($row);
        static::assertSame($customTemplate, $row['body']);
    }

    public function testShippedAdminTemplatesMatchPostFixFixtures(): void
    {
        // Guard: ensure the snapshotted post-fix fixtures stay in sync with the
        // canonical admin starter templates. Drift between them silently breaks
        // future migrations.
        $adminRoot = __DIR__ . '/../../../../src/Administration/Resources/app/administration/src/module/sw-sales-channel/blog-export-templates/';

        foreach ([
            'google.xml' => 'google-blog-search-de/body.xml.twig',
            'idealo.csv' => 'idealo/body.csv.twig',
            'billiger.csv' => 'billiger-de/body.csv.twig',
        ] as $name => $adminPath) {
            [$base, $ext] = explode('.', $name);
            $fixture = $this->readFixture($base . '_new.' . $ext . '.twig');
            $admin = file_get_contents($adminRoot . $adminPath);
            static::assertNotFalse($admin);
            static::assertSame($admin, $fixture, "issue-12852 post-fix fixture for $name has drifted from the admin starter template at $adminPath");
        }
    }

    /**
     * @return array{body: string, updatedAt: ?string}|false
     */
    private function getCurrentBodyAndUpdateTimestamp(string $id): array|false
    {
        $sql = <<<'SQL'
            SELECT body_template AS body, updated_at AS updatedAt
            FROM blog_export
            WHERE id = ?
        SQL;

        /** @var array{body: string, updatedAt: ?string}|false $row */
        $row = $this->connection->fetchAssociative($sql, [$id]);

        return $row;
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

    private function readFixture(string $name): string
    {
        $content = file_get_contents(self::FIXTURES . $name);
        static::assertNotFalse($content);

        return $content;
    }
}
