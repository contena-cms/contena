<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\Channel\Context\Cleanup;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Context\Cleanup\CleanupChannelContextTaskHandler;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Test\TestDefaults;

/**
 * @internal
 */
class CleanupChannelContextTaskHandlerTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private CleanupChannelContextTaskHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->handler = static::getContainer()->get(CleanupChannelContextTaskHandler::class);
    }

    public function testCleanup(): void
    {
        static::getContainer()->get(Connection::class)->executeStatement('DELETE FROM channel_api_context');

        $ids = new IdsCollection();

        $this->createChannelContext($ids->create('context-1'));

        $date = new \DateTime();
        $date->modify('-121 day');
        $this->createChannelContext($ids->create('context-2'), $date);

        $this->handler->run();

        $contexts = static::getContainer()->get(Connection::class)
            ->fetchFirstColumn('SELECT token FROM channel_api_context');

        static::assertCount(1, $contexts);
        static::assertContains($ids->get('context-1'), $contexts);
    }

    private function createChannelContext(string $token, ?\DateTime $date = null): void
    {
        $payload = [
            'token' => $token,
            'payload' => json_encode([
                'key' => 'value',
                'expired' => false,
            ], \JSON_THROW_ON_ERROR),
            'channel_id' => Uuid::fromHexToBytes(TestDefaults::CHANNEL),
            'updated_at' => ($date ?? new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ];

        static::getContainer()->get(Connection::class)->insert('channel_api_context', $payload);
    }
}
