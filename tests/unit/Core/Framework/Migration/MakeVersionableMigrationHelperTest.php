<?php

declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\ForeignKeyConstraint\ReferentialAction;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\MySQLSchemaManager;
use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Defaults;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Migration\MakeVersionableMigrationHelper;
use Contena\Core\System\Unit\Aggregate\UnitTranslation\UnitTranslationDefinition;
use Contena\Core\System\Unit\UnitDefinition;

/**
 * @internal
 */
#[CoversClass(MakeVersionableMigrationHelper::class)]
class MakeVersionableMigrationHelperTest extends TestCase
{
    public function testCreateSql(): void
    {
        $helper = new MakeVersionableMigrationHelper($this->createConnection());

        $relationData = $helper->getRelationData(UnitDefinition::ENTITY_NAME, 'id');
        static::assertEquals([
            'fk.unit_translation.unit_id' => [
                'TABLE_NAME' => 'unit_translation',
                'COLUMN_NAME' => ['unit_id'],
                'REFERENCED_TABLE_NAME' => 'unit',
                'REFERENCED_COLUMN_NAME' => ['id'],
            ],
            'fk.blog.unit_id' => [
                'TABLE_NAME' => 'blog',
                'COLUMN_NAME' => ['unit_id'],
                'REFERENCED_TABLE_NAME' => 'unit',
                'REFERENCED_COLUMN_NAME' => ['id'],
            ],
        ], $relationData);

        $sql = array_values($helper->createSql($relationData, UnitDefinition::ENTITY_NAME, 'version_id', Defaults::LIVE_VERSION));
        static::assertEquals([
            'ALTER TABLE `unit_translation` DROP FOREIGN KEY `fk.unit_translation.unit_id`',
            'ALTER TABLE `blog` DROP FOREIGN KEY `fk.blog.unit_id`',
            'ALTER TABLE `blog` DROP KEY `fk.blog.unit_id`',
            'ALTER TABLE `unit` DROP PRIMARY KEY, ADD `version_id` binary(16) NOT NULL DEFAULT 0x0fa91ce3e96a4bc2be4bd9ce752c3425 AFTER `id`, ADD PRIMARY KEY (`id`, `version_id`)',
            'ALTER TABLE `unit_translation` ADD `unit_version_id` binary(16) NOT NULL DEFAULT 0x0fa91ce3e96a4bc2be4bd9ce752c3425 AFTER `unit_id`',
            'ALTER TABLE `unit_translation` DROP PRIMARY KEY, ADD PRIMARY KEY (`unit_id`,`language_id`, `unit_version_id`)',
            'ALTER TABLE `unit_translation` ADD CONSTRAINT `fk.unit_translation.unit_id` FOREIGN KEY (`unit_id`, `unit_version_id`) REFERENCES `unit` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE',
            'ALTER TABLE `blog` ADD `unit_version_id` binary(16) NOT NULL DEFAULT 0x0fa91ce3e96a4bc2be4bd9ce752c3425 AFTER `unit_id`',
            'ALTER TABLE `blog` ADD CONSTRAINT `fk.blog.unit_id` FOREIGN KEY (`unit_id`, `unit_version_id`) REFERENCES `unit` (`id`, `version_id`) ON DELETE RESTRICT ON UPDATE CASCADE',
        ], $sql);
    }

    private function createConnection(): Connection
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('fetchAllAssociative')->willReturn([
            [
                'TABLE_NAME' => 'unit_translation',
                'COLUMN_NAME' => 'unit_id',
                'CONSTRAINT_NAME' => 'fk.unit_translation.unit_id',
                'REFERENCED_TABLE_NAME' => 'unit',
                'REFERENCED_COLUMN_NAME' => 'id',
            ],
            [
                'TABLE_NAME' => 'blog',
                'COLUMN_NAME' => 'unit_id',
                'CONSTRAINT_NAME' => 'fk.blog.unit_id',
                'REFERENCED_TABLE_NAME' => 'unit',
                'REFERENCED_COLUMN_NAME' => 'id',
            ],
        ]);

        $connection->expects($this->once())->method('createSchemaManager')->willReturn($this->createSchemaManager());

        return $connection;
    }

    private function createSchemaManager(): MySQLSchemaManager
    {
        $schemaManager = $this->createMock(MySQLSchemaManager::class);

        $schemaManager->expects($this->exactly(3))->method('introspectTablePrimaryKeyConstraint')
            ->willReturnCallback(static function (OptionallyQualifiedName $name): PrimaryKeyConstraint {
                $nameString = $name->getUnqualifiedName()->getValue();
                if ($nameString === UnitDefinition::ENTITY_NAME) {
                    return new PrimaryKeyConstraint(null, [UnqualifiedName::quoted('id')], true);
                }
                if ($nameString === UnitTranslationDefinition::ENTITY_NAME) {
                    return new PrimaryKeyConstraint(null, [UnqualifiedName::quoted('unit_id'), UnqualifiedName::quoted('language_id')], true);
                }
                if ($nameString === BlogDefinition::ENTITY_NAME) {
                    return new PrimaryKeyConstraint(null, [UnqualifiedName::quoted('id'), UnqualifiedName::quoted('version_id')], true);
                }
                static::fail('Missing configured return value for: ' . $nameString);
            });
        $schemaManager->expects($this->exactly(2))->method('introspectTableForeignKeyConstraintsByUnquotedName')->willReturnMap([
            [
                'unit_translation',
                null,
                [
                    ForeignKeyConstraint::editor()
                        ->setQuotedName('fk.unit_translation.language_id')
                        ->setQuotedReferencingColumnNames('language_id')
                        ->setQuotedReferencedTableName('language')
                        ->setQuotedReferencedColumnNames('id')
                        ->create(),
                    ForeignKeyConstraint::editor()
                        ->setUnquotedName('fk.unit_translation.unit_id')
                        ->setQuotedReferencingColumnNames('unit_id')
                        ->setQuotedReferencedTableName('unit')
                        ->setQuotedReferencedColumnNames('id')
                        ->setOnDeleteAction(ReferentialAction::CASCADE)
                        ->create(),
                ],
            ],
            [
                'blog',
                null,
                [
                    ForeignKeyConstraint::editor()
                        ->setQuotedName('fk.blog.canonical_product_id')
                        ->setQuotedReferencingColumnNames('canonical_product_id', 'canonical_product_version_id')
                        ->setQuotedReferencedTableName('blog')
                        ->setQuotedReferencedColumnNames('id', 'version_id')
                        ->create(),
                    ForeignKeyConstraint::editor()
                        ->setQuotedName('fk.blog.unit_id')
                        ->setQuotedReferencingColumnNames('unit_id')
                        ->setQuotedReferencedTableName('unit')
                        ->setQuotedReferencedColumnNames('id')
                        ->setOnUpdateAction(ReferentialAction::CASCADE)
                        ->setOnDeleteAction(ReferentialAction::RESTRICT)
                        ->create(),
                ],
            ],
        ]);
        $schemaManager->expects($this->exactly(2))->method('introspectTableIndexesByUnquotedName')->willReturnMap([
            [
                'unit_translation',
                null,
                [
                    Index::editor()
                        ->setQuotedName('fk.unit_translation.language_id')
                        ->setQuotedColumnNames('language_id')
                        ->create(),
                ],
            ],
            [
                'blog',
                null,
                [
                    Index::editor()
                        ->setQuotedName('fk.blog.unit_id')
                        ->setQuotedColumnNames('unit_id')
                        ->create(),
                ],
            ],
        ]);

        return $schemaManager;
    }
}
