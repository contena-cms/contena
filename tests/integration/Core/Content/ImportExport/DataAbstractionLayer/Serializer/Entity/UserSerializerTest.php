<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity;

use PHPUnit\Framework\TestCase;
use Contena\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity\UserSerializer;
use Contena\Core\Content\ImportExport\DataAbstractionLayer\Serializer\SerializerRegistry;
use Contena\Core\Content\ImportExport\Struct\Config;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\System\Channel\ChannelCollection;
use Contena\Core\System\User\Aggregate\UserGroup\UserGroupCollection;
use Contena\Core\System\User\UserCollection;
use Contena\Core\System\User\UserDefinition;
use Contena\Core\Framework\Test\TestCaseBase\ChannelApiTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
class UserSerializerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use ChannelApiTestBehaviour;

    /**
     * @var EntityRepository<UserGroupCollection>
     */
    private EntityRepository $userGroupRepository;

    /**
     * @var EntityRepository<ChannelCollection>
     */
    private EntityRepository $channelRepository;

    /**
     * @var EntityRepository<UserCollection>
     */
    private EntityRepository $userRepository;

    private UserSerializer $serializer;

    private string $userGroupId = 'a536fe4ef675470f8cddfcc7f8360e4b';

    protected function setUp(): void
    {
        $this->userGroupRepository = static::getContainer()->get('user_group.repository');
        $this->channelRepository = static::getContainer()->get('channel.repository');
        $this->userRepository = static::getContainer()->get('user.repository');
        $serializerRegistry = static::getContainer()->get(SerializerRegistry::class);

        $this->serializer = new UserSerializer(
            $this->userGroupRepository,
            $this->channelRepository
        );
        $this->serializer->setRegistry($serializerRegistry);
    }

    public function testSimple(): void
    {
        $channel = $this->createChannel();
        $this->createUserGroup();

        $config = new Config([], [], []);
        $user = [
            'group' => [
                'translations' => [
                    'DEFAULT' => [
                        'name' => 'test user group',
                    ],
                ],
            ],
            'channel' => [
                'translations' => [
                    'DEFAULT' => [
                        'name' => $channel['name'],
                    ],
                ],
            ],
            'boundChannel' => [
                'translations' => [
                    'DEFAULT' => [
                        'name' => $channel['name'],
                    ],
                ],
            ],
        ];

        $deserialized = $this->serializer->deserialize($config, $this->userRepository->getDefinition(), $user);

        static::assertIsNotArray($deserialized);

        $deserialized = \iterator_to_array($deserialized);

        static::assertSame($this->userGroupId, $deserialized['group']['id']);
        static::assertSame($channel['id'], $deserialized['channel']['id']);
        static::assertSame($channel['id'], $deserialized['boundChannel']['id']);
    }

    public function testSupportsOnlyCountry(): void
    {
        $serializer = new UserSerializer(
            $this->userGroupRepository,
            $this->channelRepository
        );

        $definitionRegistry = static::getContainer()->get(DefinitionInstanceRegistry::class);
        foreach ($definitionRegistry->getDefinitions() as $definition) {
            $entity = $definition->getEntityName();

            if ($entity === UserDefinition::ENTITY_NAME) {
                static::assertTrue($serializer->supports($entity));
            } else {
                static::assertFalse(
                    $serializer->supports($entity),
                    UserDefinition::class . ' should not support ' . $entity
                );
            }
        }
    }

    private function createUserGroup(): void
    {
        $this->userGroupRepository->upsert([
            [
                'id' => $this->userGroupId,
                'name' => 'test user group',
            ],
        ], Context::createDefaultContext());
    }
}
