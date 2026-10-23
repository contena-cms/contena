<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_6;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Document\Renderer\ZugferdEmbeddedRenderer;
use Contena\Core\Checkout\Document\Renderer\ZugferdRenderer;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Migration\V6_6\Migration1730790665ElectronicInvoice;

/**
 * @internal
 */
#[CoversClass(Migration1730790665ElectronicInvoice::class)]
class Migration1730790665ElectronicInvoiceTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1730790665, (new Migration1730790665ElectronicInvoice())->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        $this->connection->delete('document_type', ['technical_name' => ZugferdRenderer::TYPE]);
        $this->connection->delete('document_type', ['technical_name' => ZugferdEmbeddedRenderer::TYPE]);

        $migration = new Migration1730790665ElectronicInvoice();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $documentTypes = $this->connection
            ->executeQuery('SELECT `id` FROM `document_type` WHERE `technical_name` IN (:technicalNames)', [
                'technicalNames' => [ZugferdRenderer::TYPE, ZugferdEmbeddedRenderer::TYPE],
            ], [
                'technicalNames' => ArrayParameterType::STRING,
            ])->fetchAllAssociative();

        static::assertCount(2, $documentTypes);
    }
}
