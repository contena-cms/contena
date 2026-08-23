<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Content;
use Contena\Core\Content\Media\Subscriber\MediaVisibilityRestrictionSubscriber;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 */
#[CoversClass(Content::class)]
class ContentTest extends TestCase
{
    public function testBuild(): void
    {
        $content = new Content();

        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'test');

        static::assertEmpty($container->getResources());

        $content->build($container);

        static::assertNotEmpty($container->getResources());

        $resourceFiles = [];
        foreach ($container->getResources() as $resource) {
            static::assertInstanceOf(FileResource::class, $resource);
            $resourceFiles[] = basename($resource->getResource());
        }

        $expectedResources = [
            'media.php',
            'media_path.php',
            'media_test.php',
            'installed.json',
            'ThumbnailProcessorCompilerPass.php',
        ];

        static::assertSame($expectedResources, $resourceFiles);
    }

    public function testStatelessMediaVisibilitySubscriberIsNotResettable(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'test');

        new Content()->build($container);

        $definition = $container->getDefinition(MediaVisibilityRestrictionSubscriber::class);

        static::assertSame([], $definition->getArguments());
        static::assertFalse($definition->hasTag('kernel.reset'));
        static::assertTrue($definition->hasTag('kernel.event_subscriber'));
    }
}
