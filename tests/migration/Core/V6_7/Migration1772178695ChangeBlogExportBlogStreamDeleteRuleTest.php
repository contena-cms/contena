<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\ForeignKeyConstraint\ReferentialAction;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Migration\V6_7\Migration1772178695ChangeBlogExportBlogStreamDeleteRule;

/**
 * @internal
 */
#[CoversClass(Migration1772178695ChangeBlogExportBlogStreamDeleteRule::class)]
class Migration1772178695ChangeBlogExportBlogStreamDeleteRuleTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();

        $this->dropBlogStreamForeignKey();
        $this->connection->executeStatement(
            <<<'SQL'
            ALTER TABLE `blog_export`
                ADD CONSTRAINT `fk.blog_export.blog_stream_id`
                    FOREIGN KEY (`blog_stream_id`) REFERENCES `blog_stream` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
SQL
        );
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1772178695, (new Migration1772178695ChangeBlogExportBlogStreamDeleteRule())->getCreationTimestamp());
    }

    public function testMigrationChangesDeleteRuleToRestrict(): void
    {
        $migration = new Migration1772178695ChangeBlogExportBlogStreamDeleteRule();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $fks = $this->connection->createSchemaManager()->introspectTableForeignKeyConstraintsByQuotedName('blog_export');
        $fk = current(array_filter($fks, fn (ForeignKeyConstraint $fk) => $fk->getObjectName() !== null && $fk->getObjectName()->toSQL($this->connection->getDatabasePlatform()) === '`fk.blog_export.blog_stream_id`'));

        static::assertInstanceOf(ForeignKeyConstraint::class, $fk);
        static::assertContains(
            $fk->getOnDeleteAction(),
            [ReferentialAction::RESTRICT, ReferentialAction::NO_ACTION]
        );
    }

    public function testTimestampIsCorrect(): void
    {
        $migration = new Migration1772178695ChangeBlogExportBlogStreamDeleteRule();
        static::assertSame(1772178695, $migration->getCreationTimestamp());
    }

    private function dropBlogStreamForeignKey(): void
    {
        try {
            $this->connection->executeStatement(
                'ALTER TABLE `blog_export` DROP FOREIGN KEY `fk.blog_export.blog_stream_id`;'
            );
        } catch (\Throwable) {
        }
    }
}
