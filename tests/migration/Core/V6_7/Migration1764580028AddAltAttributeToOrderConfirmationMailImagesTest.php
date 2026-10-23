<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Migration\V6_7\Migration1764580028AddAltAttributeToOrderConfirmationMailImages;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
#[CoversClass(Migration1764580028AddAltAttributeToOrderConfirmationMailImages::class)]
class Migration1764580028AddAltAttributeToOrderConfirmationMailImagesTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1764580028, (new Migration1764580028AddAltAttributeToOrderConfirmationMailImages())->getCreationTimestamp());
    }

    public function testMigrationOfUnmodifiedTranslation(): void
    {
        $migration = new Migration1764580028AddAltAttributeToOrderConfirmationMailImages();

        $error = [];
        try {
            $migration->update($this->connection);
            $migration->update($this->connection);
        } catch (\Throwable $e) {
            $error[] = $e->getMessage();
        }

        static::assertCount(0, $error, print_r($error, true));
    }
}
