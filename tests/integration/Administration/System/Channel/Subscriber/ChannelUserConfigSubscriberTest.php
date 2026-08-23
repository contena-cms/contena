<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Administration\System\Channel\Subscriber;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Administration\System\Channel\Subscriber\ChannelUserConfigSubscriber;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Test\TestCaseBase\ChannelFunctionalTestBehaviour;
use Contena\Core\Framework\Test\TestCaseHelper\TestUser;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\User\Aggregate\UserConfig\UserConfigCollection;

/**
 * @internal
 */
class ChannelUserConfigSubscriberTest extends TestCase
{
    use ChannelFunctionalTestBehaviour;

    public function testDeleteWillRemoveUserConfigs(): void
    {
        $admin = TestUser::createNewTestUser(static::getContainer()->get(Connection::class), ['channel:read']);
        $context = Context::createDefaultContext();

        $channelId1 = Uuid::randomHex();
        $channelId2 = Uuid::randomHex();

        /** @var EntityRepository<UserConfigCollection> $userConfigRepository */
        $userConfigRepository = static::getContainer()->get('user_config.repository');
        $userConfigId = Uuid::randomHex();
        $userConfigRepository->create([
            [
                'id' => $userConfigId,
                'userId' => $admin->getUserId(),
                'key' => ChannelUserConfigSubscriber::CONFIG_KEY,
                'value' => [$channelId1, $channelId2],
                'createdAt' => new \DateTime(),
            ],
        ], $context);

        $search = $userConfigRepository->search(new Criteria([$userConfigId]), $context)
            ->getEntities()
            ->first();

        static::assertNotNull($search);
        static::assertIsArray($search->getValue());
        static::assertCount(2, $search->getValue());

        $this->createChannel(['id' => $channelId1]);
        $this->createChannel(['id' => $channelId2]);

        $channelRepository = static::getContainer()->get('channel.repository');
        $channelRepository->delete([['id' => $channelId1], ['id' => $channelId2]], $context);

        $search = $userConfigRepository->search(new Criteria([$userConfigId]), $context)
            ->getEntities()
            ->first();

        static::assertNotNull($search);
        static::assertIsArray($search->getValue());
        static::assertCount(0, $search->getValue());
    }
}
