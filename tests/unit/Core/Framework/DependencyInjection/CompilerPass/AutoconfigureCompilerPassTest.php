<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\DependencyInjection\CompilerPass;

use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Framework\Api\Cors\CorsHeaderProviderInterface;
use Contena\Core\Framework\Api\Cors\CorsHeaders;
use Contena\Core\Framework\DataAbstractionLayer\Attribute\Entity;
use Contena\Core\Framework\DependencyInjection\CompilerPass\AutoconfigureCompilerPass;
use Contena\Core\Framework\Webhook\Hookable\HookableEntityInterface;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @internal
 */
#[CoversClass(AutoconfigureCompilerPass::class)]
class AutoconfigureCompilerPassTest extends TestCase
{
    public function testAutoConfigure(): void
    {
        $container = new ContainerBuilder();

        $container->addCompilerPass(new AutoconfigureCompilerPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 1000);
        $container->setDefinition('blog', (new Definition(BlogDefinition::class))->setPublic(true)->setAutoconfigured(true)->setAutowired(true));

        $container->compile(true);

        static::assertTrue($container->hasDefinition('blog'));
        static::assertTrue($container->getDefinition('blog')->hasTag('contena.entity.definition'));
    }

    public function testHookableEntityAutoConfigure(): void
    {
        $container = new ContainerBuilder();

        $container->addCompilerPass(new AutoconfigureCompilerPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 1000);
        $container->setDefinition('hookable_entity', (new Definition(ExampleHookableEntity::class))->setPublic(true)->setAutoconfigured(true)->setAutowired(true));

        $container->compile(true);

        static::assertTrue($container->hasDefinition('hookable_entity'));
        static::assertTrue($container->getDefinition('hookable_entity')->hasTag('contena.entity.hookable'));
    }

    public function testAliasing(): void
    {
        $container = new ContainerBuilder();

        $container->addCompilerPass(new AutoconfigureCompilerPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 1000);
        $definition = new Definition(ExampleService::class);
        $definition->setPublic(true);
        $definition->setAutoconfigured(true);
        $definition->setAutowired(true);

        $container->setDefinition('contena.filesystem.private', (new Definition(FilesystemOperator::class))->setPublic(true));
        $container->setDefinition('contena.filesystem.public', (new Definition(FilesystemOperator::class))->setPublic(true));

        $container->setDefinition('service', $definition);

        $container->compile(true);

        static::assertTrue($container->hasDefinition('service'));

        $arg1 = $definition->getArgument(0);
        static::assertInstanceOf(Reference::class, $arg1);
        static::assertSame('contena.filesystem.private', (string) $arg1);

        $arg2 = $definition->getArgument(1);
        static::assertInstanceOf(Reference::class, $arg2);
        static::assertSame('contena.filesystem.public', (string) $arg2);
    }

    public function testCorsHeaderProviderAutoConfigure(): void
    {
        $container = new ContainerBuilder();

        $container->addCompilerPass(new AutoconfigureCompilerPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 1000);
        $container->setDefinition('cors_header_provider', (new Definition(ExampleCorsHeaderProvider::class))->setPublic(true)->setAutoconfigured(true)->setAutowired(true));

        $container->compile(true);

        static::assertTrue($container->getDefinition('cors_header_provider')->hasTag(CorsHeaderProviderInterface::SERVICE_TAG));
    }

    public function testAttribute(): void
    {
        $container = new ContainerBuilder();

        $container->addCompilerPass(new AutoconfigureCompilerPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 1000);
        $definition = new Definition(ExampleEntity::class);
        $definition->setPublic(true);
        $definition->setAutoconfigured(true);
        $definition->setAutowired(true);

        $container->setDefinition(ExampleEntity::class, $definition);

        $container->compile();

        static::assertArrayHasKey('contena.entity', $container->getDefinition(ExampleEntity::class)->getTags());
    }
}

/**
 * @internal
 */
class ExampleHookableEntity implements HookableEntityInterface
{
}

/**
 * @internal
 */
class ExampleCorsHeaderProvider implements CorsHeaderProviderInterface
{
    public function provide(CorsHeaders $headers): void
    {
        $headers->addAllowed('sw-example');
    }
}

/**
 * @internal
 */
class ExampleService
{
    public function __construct(
        public FilesystemOperator $privateFilesystem,
        public FilesystemOperator $publicFilesystem
    ) {
    }
}

/**
 * @internal
 */
#[Entity('foo')]
class ExampleEntity
{
}
