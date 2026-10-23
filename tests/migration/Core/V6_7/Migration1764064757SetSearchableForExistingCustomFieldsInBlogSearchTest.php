<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Migration\V6_7\Migration1764064757SetSearchableForExistingCustomFieldsInBlogSearch;

/**
 * @internal
 */
#[CoversClass(Migration1764064757SetSearchableForExistingCustomFieldsInBlogSearch::class)]
class Migration1764064757SetSearchableForExistingCustomFieldsInBlogSearchTest extends TestCase
{
    private readonly Connection $connection;


    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->migration = new Migration1764064757SetSearchableForExistingCustomFieldsInBlogSearch();

        try {
            $this->connection->executeStatement('ALTER TABLE `custom_field` ADD COLUMN `include_in_search` TINYINT(1) NOT NULL DEFAULT 0;');
        } catch (\Throwable) {
            // Column already exists, ignore
        }
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1764064757, $this->migration->getCreationTimestamp());
    }

    public function testSetSearchableForCustomFieldsInBlogSearch(): void
    {
        $customFieldSetId = Uuid::randomBytes();
        $customFieldId1 = Uuid::randomBytes();
        $customFieldId2 = Uuid::randomBytes();
        $customFieldId3 = Uuid::randomBytes(); // Not in blog_search_config_field
        $blogSearchConfigId = Uuid::randomBytes();
        $blogSearchConfigFieldId1 = Uuid::randomBytes();
        $blogSearchConfigFieldId2 = Uuid::randomBytes();

        $now = (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $uniqueSetName = 'test_set_' . Uuid::randomHex();
        $this->connection->insert('custom_field_set', [
            'id' => $customFieldSetId,
            'name' => $uniqueSetName,
            'config' => '{}',
            'active' => 1,
            'global' => 0,
            'created_at' => $now,
        ]);

        $uniqueFieldName1 = 'test_field_1_' . Uuid::randomHex();
        $this->connection->insert('custom_field', [
            'id' => $customFieldId1,
            'name' => $uniqueFieldName1,
            'type' => 'text',
            'config' => '{}',
            'active' => 1,
            'set_id' => $customFieldSetId,
            'include_in_search' => 0,
            'created_at' => $now,
        ]);

        $uniqueFieldName2 = 'test_field_2_' . Uuid::randomHex();
        $this->connection->insert('custom_field', [
            'id' => $customFieldId2,
            'name' => $uniqueFieldName2,
            'type' => 'int',
            'config' => '{}',
            'active' => 1,
            'set_id' => $customFieldSetId,
            'include_in_search' => 0,
            'created_at' => $now,
        ]);

        $uniqueFieldName3 = 'test_field_3_' . Uuid::randomHex();
        $this->connection->insert('custom_field', [
            'id' => $customFieldId3,
            'name' => $uniqueFieldName3,
            'type' => 'text',
            'config' => '{}',
            'active' => 1,
            'set_id' => $customFieldSetId,
            'include_in_search' => 0,
            'created_at' => $now,
        ]);

        $languageId = $this->connection->fetchOne(
            'SELECT id FROM language LIMIT 1'
        );

        if (!$languageId) {
            static::markTestSkipped('No language found in database');
        }

        // Check if blog_search_config already exists for this language
        $existingConfigId = $this->connection->fetchOne(
            'SELECT id FROM blog_search_config WHERE language_id = :languageId',
            ['languageId' => $languageId]
        );

        if ($existingConfigId) {
            $blogSearchConfigId = $existingConfigId;
        } else {
            $this->connection->insert('blog_search_config', [
                'id' => $blogSearchConfigId,
                'language_id' => $languageId,
                'and_logic' => 1,
                'min_search_length' => 2,
                'created_at' => $now,
            ]);
        }

        $this->connection->insert('blog_search_config_field', [
            'id' => $blogSearchConfigFieldId1,
            'blog_search_config_id' => $blogSearchConfigId,
            'custom_field_id' => $customFieldId1,
            'field' => 'customFields.' . $uniqueFieldName1,
            'tokenize' => 0,
            'searchable' => 1,
            'ranking' => 100,
            'created_at' => $now,
        ]);

        $this->connection->insert('blog_search_config_field', [
            'id' => $blogSearchConfigFieldId2,
            'blog_search_config_id' => $blogSearchConfigId,
            'custom_field_id' => $customFieldId2,
            'field' => 'customFields.' . $uniqueFieldName2,
            'tokenize' => 1,
            'searchable' => 1,
            'ranking' => 200,
            'created_at' => $now,
        ]);

        // Verify custom fields have include_in_search = 0 before migration
        $includeInSearch1 = $this->connection->fetchOne(
            'SELECT `include_in_search` FROM `custom_field` WHERE `id` = :id',
            ['id' => $customFieldId1]
        );
        $includeInSearch2 = $this->connection->fetchOne(
            'SELECT `include_in_search` FROM `custom_field` WHERE `id` = :id',
            ['id' => $customFieldId2]
        );
        $includeInSearch3 = $this->connection->fetchOne(
            'SELECT `include_in_search` FROM `custom_field` WHERE `id` = :id',
            ['id' => $customFieldId3]
        );

        static::assertSame('0', $includeInSearch1);
        static::assertSame('0', $includeInSearch2);
        static::assertSame('0', $includeInSearch3);

        $this->migration->update($this->connection);
        $this->migration->update($this->connection);

        $includeInSearch1 = $this->connection->fetchOne(
            'SELECT `include_in_search` FROM `custom_field` WHERE `id` = :id',
            ['id' => $customFieldId1]
        );
        $includeInSearch2 = $this->connection->fetchOne(
            'SELECT `include_in_search` FROM `custom_field` WHERE `id` = :id',
            ['id' => $customFieldId2]
        );

        static::assertSame('1', $includeInSearch1, 'Custom field 1 should be included in search');
        static::assertSame('1', $includeInSearch2, 'Custom field 2 should be included in search');

        $includeInSearch3 = $this->connection->fetchOne(
            'SELECT `include_in_search` FROM `custom_field` WHERE `id` = :id',
            ['id' => $customFieldId3]
        );

        static::assertSame('0', $includeInSearch3, 'Custom field 3 should not be included in search');
    }
}
