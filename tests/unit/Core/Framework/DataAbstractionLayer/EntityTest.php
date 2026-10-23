<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\DataAbstractionLayer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Aggregate\BlogPrice\BlogPriceDefinition;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\FieldVisibility;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainDefinition;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainEntity;

/**
 * @internal
 */
#[CoversClass(Entity::class)]
class EntityTest extends TestCase
{
    /**
     * @return \Generator<array{class-string<Entity>, string}>
     */
    public static function apiAliasDefaultsDataProvider(): iterable
    {
        yield [
            'foo',
        ];

        yield [
            'foo_bar',
        ];

        yield [
            'foo_bar',
        ];

        yield [
            'blog_entity_relation',
        ];

        yield [
            BlogEntity::class,
            BlogDefinition::ENTITY_NAME,
        ];

        yield [
            BlogPriceDefinition::ENTITY_NAME,
        ];

        yield [
            ChannelDomainEntity::class,
            ChannelDomainDefinition::ENTITY_NAME,
        ];
    }

    #[DataProvider('apiAliasDefaultsDataProvider')]
    public function testApiAlias(string $class, string $expected): void
    {
        /** @var Entity $entity */
        $entity = new $class();

        static::assertSame($expected, $entity->getApiAlias());
    }

    public function testCustomApiAliasHasPrecedence(): void
    {
        $entity = new FooBarEntity();
        $entity->internalSetEntityData('custom_entity_name', new FieldVisibility([]));

        static::assertSame('custom_entity_name', $entity->getApiAlias());
    }
}

/**
 * @internal
 */
class FooEntity extends Entity
{
}

/**
 * @internal
 */
class FooBarEntity extends Entity
{
}

/**
 * @internal
 */
class FooBar extends Entity
{
}

/**
 * @internal
 */
class BlogEntityRelationEntity extends Entity
{
}
