<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\User\Event\UserPasswordChangedEvent;
use Contena\Core\Content\Flow\Dispatching\Action\SendMailAction;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Contena\Core\Migration\V6_7\Migration1763377575SendEmailAfterPasswordChangeFlow;

/**
 * @internal
 */
#[CoversClass(Migration1763377575SendEmailAfterPasswordChangeFlow::class)]
class Migration1763377575SendEmailAfterPasswordChangeFlowTest extends TestCase
{
    use KernelTestBehaviour;

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1763377575, (new Migration1763377575SendEmailAfterPasswordChangeFlow())->getCreationTimestamp());
    }

    public function testTimestamp(): void
    {
        $migration = new Migration1763377575SendEmailAfterPasswordChangeFlow();
        static::assertSame(1763377575, $migration->getCreationTimestamp());
    }

    public function testUpdate(): void
    {
        $connection = KernelLifecycleManager::getConnection();

        $migration = new Migration1763377575SendEmailAfterPasswordChangeFlow();

        $this->rollback($connection);

        $migration->update($connection);
        $migration->update($connection);

        $flow = $connection->fetchAllAssociative('SELECT * FROM `flow` WHERE `event_name` = :name', ['name' => UserPasswordChangedEvent::EVENT_NAME]);
        static::assertCount(1, $flow);

        $flowSequence = $connection->fetchAllAssociative('SELECT * FROM `flow_sequence` WHERE `flow_id` = :id', ['id' => $flow[0]['id']]);
        static::assertIsArray($flowSequence);
        static::assertCount(1, $flowSequence);
        static::assertArrayHasKey('action_name', $flowSequence[0]);
        static::assertSame(SendMailAction::ACTION_NAME, $flowSequence[0]['action_name']);

        $flowTemplate = $connection->fetchAllAssociative('SELECT * FROM `flow_template` WHERE JSON_EXTRACT(config, \'$.eventName\') = :eventName', [
            'eventName' => UserPasswordChangedEvent::EVENT_NAME,
        ]);
        static::assertCount(1, $flowTemplate);
        static::assertSame('User password changed', $flowTemplate[0]['name']);
    }

    private function rollback(Connection $connection): void
    {
        $deletedFlow = $connection->executeStatement(
            'DELETE FROM `flow` WHERE `event_name` = :name',
            ['name' => UserPasswordChangedEvent::EVENT_NAME]
        );

        static::assertSame(1, $deletedFlow);
    }
}
