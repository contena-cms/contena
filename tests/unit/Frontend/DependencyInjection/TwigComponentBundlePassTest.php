<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Frontend\DependencyInjection\TwigComponentBundlePass;
use Contena\Frontend\Frontend;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[CoversClass(TwigComponentBundlePass::class)]
class TwigComponentBundlePassTest extends TestCase
{
    public function testProcessDoesNothingWhenTwigComponentParameterNotSet(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', []);

        new TwigComponentBundlePass()->process($container);

        static::assertFalse($container->hasParameter('ux.twig_component.component_defaults'));
    }

    public function testProcessDoesNothingWhenDefaultsIsNotArray(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('ux.twig_component.component_defaults', 'not-an-array');
        $container->setParameter('kernel.bundles', []);

        new TwigComponentBundlePass()->process($container);

        static::assertSame('not-an-array', $container->getParameter('ux.twig_component.component_defaults'));
    }

    public function testProcessDoesNothingWhenKernelBundlesIsNotArray(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('ux.twig_component.component_defaults', []);
        $container->setParameter('kernel.bundles', 'not-an-array');

        new TwigComponentBundlePass()->process($container);

        static::assertSame([], $container->getParameter('ux.twig_component.component_defaults'));
    }

    public function testProcessDoesNothingWhenKernelBundlesMetadataIsNotArray(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('ux.twig_component.component_defaults', []);
        $container->setParameter('kernel.bundles', []);
        $container->setParameter('kernel.bundles_metadata', 'not-an-array');

        new TwigComponentBundlePass()->process($container);

        static::assertSame([], $container->getParameter('ux.twig_component.component_defaults'));
    }

    public function testProcessRegistersNamespaceForBundleWithComponentsDirectory(): void
    {
        $bundlePath = '/some/frontend/path';
        $filesystem = static::createStub(Filesystem::class);
        $filesystem->method('exists')->willReturn(true);

        $container = new ContainerBuilder();
        $container->setParameter('ux.twig_component.component_defaults', []);
        $container->setParameter('kernel.bundles', ['Frontend' => Frontend::class]);
        $container->setParameter('kernel.bundles_metadata', [
            'Frontend' => ['path' => $bundlePath, 'namespace' => 'Contena\\Frontend'],
        ]);

        new TwigComponentBundlePass($filesystem)->process($container);

        $defaults = $container->getParameter('ux.twig_component.component_defaults');
        static::assertIsArray($defaults);
        $namespace = 'Contena\\Frontend\\Resources\\views\\components\\';
        static::assertArrayHasKey($namespace, $defaults);
        static::assertSame('@Frontend/components', $defaults[$namespace]['template_directory']);
        static::assertSame('Frontend', $defaults[$namespace]['name_prefix']);
    }

    public function testProcessDoesNotOverwriteAlreadyRegisteredNamespace(): void
    {
        $bundlePath = '/some/frontend/path';
        $existingConfig = ['template_directory' => 'custom', 'name_prefix' => 'Custom'];
        $namespace = 'Contena\\Frontend\\Resources\\views\\components\\';
        $filesystem = static::createStub(Filesystem::class);
        $filesystem->method('exists')->willReturn(true);

        $container = new ContainerBuilder();
        $container->setParameter('ux.twig_component.component_defaults', [$namespace => $existingConfig]);
        $container->setParameter('kernel.bundles', ['Frontend' => Frontend::class]);
        $container->setParameter('kernel.bundles_metadata', [
            'Frontend' => ['path' => $bundlePath, 'namespace' => 'Contena\\Frontend'],
        ]);

        new TwigComponentBundlePass($filesystem)->process($container);

        $defaults = $container->getParameter('ux.twig_component.component_defaults');
        static::assertIsArray($defaults);
        static::assertSame($existingConfig, $defaults[$namespace]);
    }

    public function testProcessSkipsNonContenaBundles(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('ux.twig_component.component_defaults', []);
        $container->setParameter('kernel.bundles', ['StdClass' => \stdClass::class]);
        $container->setParameter('kernel.bundles_metadata', []);

        new TwigComponentBundlePass()->process($container);

        static::assertSame([], $container->getParameter('ux.twig_component.component_defaults'));
    }

    public function testProcessSkipsBundleWithoutComponentsDirectory(): void
    {
        $bundlePath = '/some/path/without/components';
        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->expects($this->once())->method('exists')
            ->with($bundlePath . '/Resources/views/components')->willReturn(false);

        $container = new ContainerBuilder();
        $container->setParameter('ux.twig_component.component_defaults', []);
        $container->setParameter('kernel.bundles', ['Frontend' => Frontend::class]);
        $container->setParameter('kernel.bundles_metadata', [
            'Frontend' => ['path' => $bundlePath, 'namespace' => 'Contena\\Frontend'],
        ]);

        new TwigComponentBundlePass($filesystem)->process($container);

        static::assertSame([], $container->getParameter('ux.twig_component.component_defaults'));
    }

    public function testProcessSkipsNonExistentClass(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('ux.twig_component.component_defaults', []);
        $container->setParameter('kernel.bundles', ['Ghost' => 'NonExistent\\GhostBundle']);
        $container->setParameter('kernel.bundles_metadata', []);

        new TwigComponentBundlePass()->process($container);

        static::assertSame([], $container->getParameter('ux.twig_component.component_defaults'));
    }

    public function testProcessSkipsBundleWithMissingMetadata(): void
    {
        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->expects($this->never())->method('exists');
        $container = new ContainerBuilder();
        $container->setParameter('ux.twig_component.component_defaults', []);
        $container->setParameter('kernel.bundles', ['Frontend' => Frontend::class]);
        $container->setParameter('kernel.bundles_metadata', []);

        new TwigComponentBundlePass($filesystem)->process($container);

        static::assertSame([], $container->getParameter('ux.twig_component.component_defaults'));
    }
}
