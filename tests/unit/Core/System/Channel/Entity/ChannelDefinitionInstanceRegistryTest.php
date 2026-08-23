<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Channel\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\System\Channel\Entity\ChannelDefinitionInstanceRegistry;
use Contena\Core\System\Channel\Exception\ChannelRepositoryNotFoundException;
use Symfony\Component\DependencyInjection\Container;

/**
 * @internal
 */
#[CoversClass(ChannelDefinitionInstanceRegistry::class)]
class ChannelDefinitionInstanceRegistryTest extends TestCase
{
    public function testRegister(): void
    {
        $registry = new ChannelDefinitionInstanceRegistry('channel_definition.', new Container(), [], []);
        $registry->register(new BlogDefinition());

        static::assertInstanceOf(BlogDefinition::class, $registry->get(BlogDefinition::class));
        static::assertTrue($registry->has(BlogDefinition::ENTITY_NAME));
        static::assertInstanceOf(BlogDefinition::class, $registry->getByEntityName(BlogDefinition::ENTITY_NAME));
        static::assertInstanceOf(BlogDefinition::class, $registry->getByEntityClass(new BlogEntity()));
    }

    public function testThrowsWhenChannelRepositoryIsMissing(): void
    {
        $registry = new ChannelDefinitionInstanceRegistry('channel_definition.', new Container(), [], []);

        $this->expectException(ChannelRepositoryNotFoundException::class);
        $registry->getChannelRepository('blog');
    }
}
