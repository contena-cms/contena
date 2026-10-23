<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Api\Sync;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Aggregate\BlogCategory\BlogCategoryDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Category\CategoryDefinition;
use Contena\Core\Framework\Api\ApiException;
use Contena\Core\Framework\Api\Sync\AbstractFkResolver;
use Contena\Core\Framework\Api\Sync\SyncFkResolver;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Contena\Core\System\Channel\ChannelDefinition;
use Contena\Core\System\Tax\TaxDefinition;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(SyncFkResolver::class)]
class SyncFkResolverTest extends TestCase
{
    public function testResolveWithDummy(): void
    {
        $payload = [
            // many-to-one-case
            'taxId' => ['resolver' => 'dummy', 'value' => 't1'],

            // many-to-many-case
            'categories' => [
                ['id' => ['resolver' => 'dummy', 'value' => 'c1']],
                ['id' => ['resolver' => 'dummy', 'value' => 'c2']],
            ],

            // nesting case
            'visibilities' => [
                ['visibility' => 1, 'channelId' => ['resolver' => 'dummy', 'value' => 's1']],
            ],
        ];

        $resolver = new SyncFkResolver(
            new StaticDefinitionInstanceRegistry(
                [
                    BlogDefinition::class,
                    TaxDefinition::class,
                    BlogVisibilityDefinition::class,
                    ChannelDefinition::class,
                    CategoryDefinition::class,
                    BlogCategoryDefinition::class,
                ],
                static::createStub(ValidatorInterface::class),
                static::createStub(EntityWriteGatewayInterface::class)
            ),
            [new DummyFkResolver()]
        );

        $resolved = $resolver->resolve('ops-1', 'blog', [$payload]);

        $expected = [
            'taxId' => 't1',
            'categories' => [
                ['id' => 'c1'],
                ['id' => 'c2'],
            ],
            'visibilities' => [
                ['visibility' => 1, 'channelId' => 's1'],
            ],
        ];

        static::assertCount(1, $resolved);
        static::assertSame($expected, $resolved[0]);
    }

    /**
     * @param array<array<string, mixed>> $payload
     * @param array<string> $expected
     */
    #[DataProvider('missingResolverProvider')]
    public function testMissingResolverThrowsException(array $payload, array $expected): void
    {
        $resolver = new SyncFkResolver(
            new StaticDefinitionInstanceRegistry(
                [BlogDefinition::class, TaxDefinition::class, CategoryDefinition::class, BlogCategoryDefinition::class],
                static::createStub(ValidatorInterface::class),
                static::createStub(EntityWriteGatewayInterface::class)
            ),
            [new DummyFkResolver(), new DoNothingResolver()]
        );

        try {
            $resolver->resolve('ops-1', 'blog', $payload);

            static::fail('Case should fail');
        } catch (ApiException $exception) {
            static::assertSame(ApiException::API_INVALID_SYNC_RESOLVERS, $exception->getErrorCode());

            foreach ($expected as $pointer) {
                static::assertStringContainsString($pointer, $exception->getMessage());
            }
        }
    }

    public static function missingResolverProvider(): \Generator
    {
        yield 'Single record, single id missing' => [
            'payload' => [
                [
                    'taxId' => [
                        'resolver' => 'do-nothing',
                        'value' => 't1',
                    ],
                ],
            ],
            'expected' => ['ops-1/0/taxId'],
        ];

        yield 'Single record, multiple ids missing' => [
            'payload' => [
                [
                    'taxId' => [
                        'resolver' => 'do-nothing',
                        'value' => 't1',
                    ],
                    'categories' => [
                        ['id' => ['resolver' => 'do-nothing', 'value' => 'c1']],
                        ['id' => ['resolver' => 'do-nothing', 'value' => 'c2']],
                    ],
                ],
            ],
            'expected' => [
                'ops-1/0/taxId',
                'ops-1/0/categories/0/id',
                'ops-1/0/categories/1/id',
            ],
        ];

        yield 'Multiple records, single id missing' => [
            'payload' => [
                [
                    'taxId' => [
                        'resolver' => 'do-nothing',
                        'value' => 't1',
                    ],
                ],
                [
                    'taxId' => [
                        'resolver' => 'do-nothing',
                        'value' => 't2',
                    ],
                ],
            ],
            'expected' => [
                'ops-1/0/taxId',
                'ops-1/1/taxId',
            ],
        ];

        yield 'Multiple records, multiple ids missing' => [
            'payload' => [
                [
                    'taxId' => [
                        'resolver' => 'do-nothing',
                        'value' => 't1',
                    ],
                    'categories' => [
                        ['id' => ['resolver' => 'do-nothing', 'value' => 'c1']],
                        ['id' => ['resolver' => 'do-nothing', 'value' => 'c2']],
                    ],
                ],
                [
                    'taxId' => [
                        'resolver' => 'do-nothing',
                        'value' => 't2',
                    ],
                    'categories' => [
                        ['id' => ['resolver' => 'do-nothing', 'value' => 'c3']],
                        ['id' => ['resolver' => 'do-nothing', 'value' => 'c4']],
                    ],
                ],
            ],
            'expected' => [
                'ops-1/0/taxId',
                'ops-1/0/categories/0/id',
                'ops-1/0/categories/1/id',
                'ops-1/1/taxId',
                'ops-1/1/categories/0/id',
                'ops-1/1/categories/1/id',
            ],
        ];
    }

    public function testMissingOnNull(): void
    {
        $resolver = new SyncFkResolver(
            new StaticDefinitionInstanceRegistry(
                [BlogDefinition::class, TaxDefinition::class, CategoryDefinition::class, BlogCategoryDefinition::class],
                static::createStub(ValidatorInterface::class),
                static::createStub(EntityWriteGatewayInterface::class)
            ),
            [new DummyFkResolver(), new DoNothingResolver()]
        );

        $payload = [
            'taxId' => [
                'resolver' => 'do-nothing',
                'value' => 't1',
                'nullOnMissing' => true,
            ],
        ];

        $resolved = $resolver->resolve('ops-1', 'blog', [$payload]);

        static::assertSame([['taxId' => null]], $resolved);
    }
}

/**
 * @internal
 */
class DummyFkResolver extends AbstractFkResolver
{
    public static function getName(): string
    {
        return 'dummy';
    }

    public function resolve(array $map): array
    {
        foreach ($map as $value) {
            $value->resolved = $value->value;
        }

        return $map;
    }
}

/**
 * @internal
 */
class DoNothingResolver extends AbstractFkResolver
{
    public static function getName(): string
    {
        return 'do-nothing';
    }

    public function resolve(array $map): array
    {
        return $map;
    }
}
