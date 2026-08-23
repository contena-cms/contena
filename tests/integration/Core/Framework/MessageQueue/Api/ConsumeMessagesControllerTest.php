<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\MessageQueue\Api;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskDefinition;
use Contena\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\QueueTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Tests\Integration\Core\Framework\MessageQueue\fixtures\TestTask;

/**
 * @internal
 */
class ConsumeMessagesControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;
    use QueueTestBehaviour;

    public function testConsumeMessages(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        $connection->executeStatement('DELETE FROM scheduled_task');

        // queue a task
        $repo = static::getContainer()->get('scheduled_task.repository');
        $taskId = Uuid::randomHex();
        $repo->create([
            [
                'id' => $taskId,
                'name' => 'test',
                'scheduledTaskClass' => TestTask::class,
                'runInterval' => 300,
                'defaultRunInterval' => 300,
                'status' => ScheduledTaskDefinition::STATUS_SCHEDULED,
                'nextExecutionTime' => new \DateTime()->modify('-1 second'),
            ],
        ], Context::createDefaultContext());

        $url = '/api/_action/scheduled-task/run';
        $client = $this->getBrowser();
        $client->request('POST', $url);

        // consume the queued task
        $url = '/api/_action/message-queue/consume';
        $client = $this->getBrowser();
        $client->request('POST', $url, ['receiver' => 'async']);

        $response = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(200, $client->getResponse()->getStatusCode(), \print_r($response, true));
        static::assertArrayHasKey('handledMessages', $response);
        static::assertIsInt($response['handledMessages']);
        static::assertSame(1, $response['handledMessages']);
    }
}
