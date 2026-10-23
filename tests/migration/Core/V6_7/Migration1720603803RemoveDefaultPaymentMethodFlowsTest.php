<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Migration\V6_7\Migration1720603803RemoveDefaultPaymentMethodFlows;

/**
 * @internal
 */
#[CoversClass(Migration1720603803RemoveDefaultPaymentMethodFlows::class)]
class Migration1720603803RemoveDefaultPaymentMethodFlowsTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1720603803, (new Migration1720603803RemoveDefaultPaymentMethodFlows())->getCreationTimestamp());
    }

    public function testUpdate(): void
    {
        $initialInvalidFlows = $this->getInvalidFlows();
        $this->addTestFlows();

        $migration = new Migration1720603803RemoveDefaultPaymentMethodFlows();
        $migration->update(static::getContainer()->get(Connection::class));
        $migration->update(static::getContainer()->get(Connection::class));

        static::assertSame($initialInvalidFlows + 1, $this->getInvalidFlows());
    }

    public function testUpdateDestructive(): void
    {
        $this->addTestFlows();
        static::assertSame(1, $this->getDefaultPaymentMethodChangedFlows());

        $migration = new Migration1720603803RemoveDefaultPaymentMethodFlows();
        $migration->updateDestructive(static::getContainer()->get(Connection::class));
        $migration->updateDestructive(static::getContainer()->get(Connection::class));

        static::assertSame(0, $this->getDefaultPaymentMethodChangedFlows());
    }

    private function getInvalidFlows(): int
    {
        return (int) static::getContainer()->get(Connection::class)->fetchOne(
            'SELECT COUNT(*) FROM `flow` WHERE `active` = 0 AND `invalid` = 1',
        );
    }

    private function getDefaultPaymentMethodChangedFlows(): int
    {
        return (int) static::getContainer()->get(Connection::class)->fetchOne(
            'SELECT COUNT(*) FROM `flow` WHERE `event_name` = "checkout.user.changed-payment-method"'
        );
    }

    private function addTestFlows(): void
    {
        static::getContainer()->get(Connection::class)->insert('flow', [
            'id' => Uuid::randomBytes(),
            'name' => 'flowChangedUserDefaultPaymentMethod',
            'event_name' => 'checkout.user.changed-payment-method',
            'active' => 1,
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
    }
}
