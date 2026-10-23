<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity\UserSerializer;
use Contena\Core\Content\ImportExport\DataAbstractionLayer\Serializer\SerializerRegistry;
use Contena\Core\Content\ImportExport\Struct\Config;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\System\User\UserDefinition;

/**
 * @internal
 */
#[CoversClass(UserSerializer::class)]
class UserSerializerTest extends TestCase
{
    /**
     * @param array<string, string|array<string, array<string, array<string, string>>>> $data
     * @param array<string, string|array<string, string>> $expected
     * @param array<string, array<string, string>> $cacheEntities
     */
    #[DataProvider('provideUserData')]
    public function testDeserialize(
        array $data,
        array $expected,
        array $cacheEntities,
    ): void {
        $repositoryMock = static::createStub(EntityRepository::class);
        $serializer = new UserSerializer(
            $repositoryMock,
            $repositoryMock,
            $cacheEntities['cacheUserGroups'],
            $cacheEntities['cacheChannels'],
        );

        $userDefinition = new UserDefinition();
        $userDefinition->compile(static::createStub(DefinitionInstanceRegistry::class));

        $serializer->setRegistry(static::createStub(SerializerRegistry::class));

        $result = iterator_to_array($serializer->deserialize(new Config([], [], []), $userDefinition, $data));
        static::assertSame($expected, $result);
    }

    public static function provideUserData(): \Generator
    {
        yield 'test basic data' => [
            'data' => self::createData(),
            'expected' => self::createExpected(),
            'cacheEntities' => self::createCacheEntities(),
        ];
        yield 'none of the data processed by the deserializer is passed' => [
            'data' => [],
            'expected' => [],
            'cacheEntities' => [
                'cacheUserGroups' => [],
                'cacheChannels' => [],
            ],
        ];
        yield 'bounded sales channel and sales channel are different' => [
            'data' => self::createData([
                'boundChannel' => [
                    'translations' => [
                        'DEFAULT' => [
                            'name' => 'bound_channel_name',
                        ],
                    ],
                ],
            ]),
            'expected' => self::createExpected([
                'boundChannel' => [
                    'id' => 'boundChannelId',
                ],
            ]),
            'cacheEntities' => self::createCacheEntities([
                'cacheChannels' => [
                    'bound_channel_name' => 'boundChannelId',
                ],
            ]),
        ];
    }

    /**
     * @param array<string, string|array<string, array<string, array<string, string>>>> $overrides
     *
     * @return array<string, string|array<string, array<string, array<string, string>>>>
     */
    private static function createData(array $overrides = []): array
    {
        $data = array_merge([
            'group' => [
                'translations' => [
                    'DEFAULT' => [
                        'name' => 'group_name',
                    ],
                ],
            ],
            'channel' => [
                'translations' => [
                    'DEFAULT' => [
                        'name' => 'channel_name',
                    ],
                ],
            ],
        ], $overrides);

        return $data;
    }

    /**
     * @param array<string, string|array<string, string>> $overrides
     *
     * @return array<string, string|array<string, string>>
     */
    private static function createExpected(array $overrides = []): array
    {
        $data = array_merge([
            'group' => [
                'id' => 'groupId',
            ],
            'channel' => [
                'id' => 'channelId',
            ],
        ], $overrides);

        return $data;
    }

    /**
     * @param array<string, array<string, string>> $overrides
     *
     * @return array<string, array<string, string>>
     */
    private static function createCacheEntities(array $overrides = []): array
    {
        return array_merge_recursive([
            'cacheUserGroups' => [
                'group_name' => 'groupId',
            ],
            'cacheChannels' => [
                'channel_name' => 'channelId',
            ],
        ], $overrides);
    }
}
