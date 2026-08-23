<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Api\Sync;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Aggregate\BlogCategory\BlogCategoryDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogMedia\BlogMediaDefinition;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Category\CategoryDefinition;
use Contena\Core\Framework\Api\ApiException;
use Contena\Core\Framework\Api\Sync\AbstractFkResolver;
use Contena\Core\Framework\Api\Sync\SyncFkResolver;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
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
            'coverId' => ['resolver' => 'dummy', 'value' => 'm1'],
            'categories' => [
                ['id' => ['resolver' => 'dummy', 'value' => 'c1']],
                ['id' => ['resolver' => 'dummy', 'value' => 'c2']],
            ],
        ];

        $resolver = $this->createResolver([BlogDefinition::class, BlogMediaDefinition::class, CategoryDefinition::class, BlogCategoryDefinition::class], [new DummyFkResolver()]);

        $resolved = $resolver->resolve('ops-1', 'blog', [$payload]);

        static::assertSame([
            [
                'coverId' => 'm1',
                'categories' => [['id' => 'c1'], ['id' => 'c2']],
            ],
        ], $resolved);
    }

    /**
     * @param array<array<string, mixed>> $payload
     * @param array<string> $expected
     */
    #[DataProvider('missingResolverProvider')]
    public function testMissingResolverThrowsException(array $payload, array $expected): void
    {
        $resolver = $this->createResolver([BlogDefinition::class, BlogMediaDefinition::class, CategoryDefinition::class, BlogCategoryDefinition::class], [new DummyFkResolver(), new DoNothingResolver()]);

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
        yield 'single record, single id missing' => [
            [['coverId' => ['resolver' => 'do-nothing', 'value' => 'm1']]],
            ['ops-1/0/coverId'],
        ];

        yield 'single record, multiple ids missing' => [
            [[
                'coverId' => ['resolver' => 'do-nothing', 'value' => 'm1'],
                'categories' => [
                    ['id' => ['resolver' => 'do-nothing', 'value' => 'c1']],
                    ['id' => ['resolver' => 'do-nothing', 'value' => 'c2']],
                ],
            ]],
            ['ops-1/0/coverId', 'ops-1/0/categories/0/id', 'ops-1/0/categories/1/id'],
        ];

        yield 'multiple records, single id missing' => [
            [
                ['coverId' => ['resolver' => 'do-nothing', 'value' => 'm1']],
                ['coverId' => ['resolver' => 'do-nothing', 'value' => 'm2']],
            ],
            ['ops-1/0/coverId', 'ops-1/1/coverId'],
        ];

        yield 'multiple records, multiple ids missing' => [
            [
                [
                    'coverId' => ['resolver' => 'do-nothing', 'value' => 'm1'],
                    'categories' => [
                        ['id' => ['resolver' => 'do-nothing', 'value' => 'c1']],
                        ['id' => ['resolver' => 'do-nothing', 'value' => 'c2']],
                    ],
                ],
                [
                    'coverId' => ['resolver' => 'do-nothing', 'value' => 'm2'],
                    'categories' => [
                        ['id' => ['resolver' => 'do-nothing', 'value' => 'c3']],
                        ['id' => ['resolver' => 'do-nothing', 'value' => 'c4']],
                    ],
                ],
            ],
            [
                'ops-1/0/coverId',
                'ops-1/0/categories/0/id',
                'ops-1/0/categories/1/id',
                'ops-1/1/coverId',
                'ops-1/1/categories/0/id',
                'ops-1/1/categories/1/id',
            ],
        ];
    }

    public function testMissingOnNull(): void
    {
        $resolver = $this->createResolver([BlogDefinition::class, BlogMediaDefinition::class], [new DummyFkResolver(), new DoNothingResolver()]);

        $payload = [
            'coverId' => [
                'resolver' => 'do-nothing',
                'value' => 'm1',
                'nullOnMissing' => true,
            ],
        ];

        static::assertSame([['coverId' => null]], $resolver->resolve('ops-1', 'blog', [$payload]));
    }

    /**
     * @param list<class-string<EntityDefinition>> $definitions
     * @param list<AbstractFkResolver> $resolvers
     */
    private function createResolver(array $definitions, array $resolvers): SyncFkResolver
    {
        return new SyncFkResolver(
            new StaticDefinitionInstanceRegistry(
                $definitions,
                static::createStub(ValidatorInterface::class),
                static::createStub(EntityWriteGatewayInterface::class),
            ),
            $resolvers,
        );
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
