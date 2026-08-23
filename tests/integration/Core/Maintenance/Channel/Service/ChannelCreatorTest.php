<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Maintenance\Channel\Service;

use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Maintenance\Channel\Service\ChannelCreator;
use Contena\Core\System\Channel\ChannelCollection;

/**
 * @internal
 */
class ChannelCreatorTest extends TestCase
{
    use IntegrationTestBehaviour;

    private ChannelCreator $channelCreator;

    /**
     * @var EntityRepository<ChannelCollection>
     */
    private EntityRepository $channelRepository;

    protected function setUp(): void
    {
        $this->channelCreator = static::getContainer()->get(ChannelCreator::class);
        $this->channelRepository = static::getContainer()->get('channel.repository');
    }

    public function testCreateChannel(): void
    {
        $id = Uuid::randomHex();
        $this->channelCreator->createChannel($id, 'test', Defaults::CHANNEL_TYPE_API);

        $channel = $this->channelRepository->search(new Criteria([$id]), Context::createDefaultContext())->getEntities()->first();

        static::assertNotNull($channel);
        static::assertSame('test', $channel->getName());
        static::assertSame(Defaults::CHANNEL_TYPE_API, $channel->getTypeId());
    }
}
