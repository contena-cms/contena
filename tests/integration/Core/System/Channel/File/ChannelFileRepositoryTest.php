<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\Channel\File;

use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Aggregate\ChannelFile\ChannelFileCollection;
use Contena\Core\System\Channel\Aggregate\ChannelFile\ChannelFileEntity;
use Contena\Core\System\Channel\ChannelCollection;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\Test\TestDefaults;

/**
 * @internal
 */
class ChannelFileRepositoryTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testItStoresTemplateOverridesAsChannelScopedConfiguration(): void
    {
        $id = Uuid::randomHex();

        $repository = $this->getChannelFileRepository();
        $repository->create([
            [
                'id' => $id,
                'channelId' => TestDefaults::CHANNEL,
                'fileFamily' => 'agentic',
                'fileName' => 'llms.txt',
                'enabled' => true,
                'templateOverrides' => [
                    'Framework' => 'channel owner override',
                    'Ucp' => 'plugin override',
                ],
            ],
        ], Context::createDefaultContext());

        $entity = $repository->search(new Criteria([$id]), Context::createDefaultContext())->getEntities()->first();

        static::assertInstanceOf(ChannelFileEntity::class, $entity);
        static::assertSame(TestDefaults::CHANNEL, $entity->getChannelId());
        static::assertSame('agentic', $entity->getFileFamily());
        static::assertSame('llms.txt', $entity->getFileName());
        static::assertTrue($entity->isEnabled());

        $templateOverrides = $entity->getTemplateOverrides();
        ksort($templateOverrides);

        static::assertSame([
            'Framework' => 'channel owner override',
            'Ucp' => 'plugin override',
        ], $templateOverrides);
    }

    public function testChannelAssociationLoadsFiles(): void
    {
        $id = Uuid::randomHex();

        $this->getChannelFileRepository()->create([
            [
                'id' => $id,
                'channelId' => TestDefaults::CHANNEL,
                'fileFamily' => 'agentic',
                'fileName' => 'agents.md',
                'enabled' => false,
                'templateOverrides' => [],
            ],
        ], Context::createDefaultContext());

        $criteria = new Criteria([TestDefaults::CHANNEL])->addAssociation('channelFiles');
        $channel = $this->getChannelRepository()->search($criteria, Context::createDefaultContext())->getEntities()->first();

        static::assertInstanceOf(ChannelEntity::class, $channel);
        static::assertNotNull($channel->getChannelFiles());
        static::assertTrue($channel->getChannelFiles()->has($id));
    }

    /**
     * @return EntityRepository<ChannelFileCollection>
     */
    private function getChannelFileRepository(): EntityRepository
    {
        return static::getContainer()->get('channel_file.repository');
    }

    /**
     * @return EntityRepository<ChannelCollection>
     */
    private function getChannelRepository(): EntityRepository
    {
        return static::getContainer()->get('channel.repository');
    }
}
