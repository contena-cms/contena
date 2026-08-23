<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Theme\BundleConfig;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Contena\Frontend\Theme\BundleConfig\FrontendBundleConfigStyleFileResolver;
use Contena\Frontend\Theme\FrontendPluginConfiguration\FileCollection;
use Contena\Frontend\Theme\FrontendPluginConfiguration\FrontendPluginConfiguration;
use Contena\Frontend\Theme\FrontendPluginConfiguration\FrontendPluginConfigurationCollection;
use Contena\Frontend\Theme\FrontendPluginRegistry;

/**
 * @internal
 */
#[CoversClass(FrontendBundleConfigStyleFileResolver::class)]
class FrontendBundleConfigStyleFileResolverTest extends TestCase
{
    #[TestDox('resolveStyleFiles() returns an empty array when the registry has no configuration for the technical name')]
    public function testResolveStyleFilesReturnsEmptyWhenConfigurationMissing(): void
    {
        $registry = static::createStub(FrontendPluginRegistry::class);
        $registry->method('getConfigurations')->willReturn(new FrontendPluginConfigurationCollection());

        $resolver = new FrontendBundleConfigStyleFileResolver($registry);

        static::assertSame([], $resolver->resolveStyleFiles('CtPlugin', 'custom/plugins/CtPlugin'));
    }

    #[TestDox('resolveStyleFiles() returns style file paths joined against the bundle basePath under Resources/')]
    public function testResolveStyleFilesJoinsConfiguredPathsToBasePath(): void
    {
        $configuration = new FrontendPluginConfiguration('CtPlugin');
        $configuration->setStyleFiles(FileCollection::createFromArray([
            'app/frontend/src/scss/base.scss',
            'app/frontend/src/scss/overrides.scss',
        ]));

        $registry = static::createStub(FrontendPluginRegistry::class);
        $registry->method('getConfigurations')
            ->willReturn(new FrontendPluginConfigurationCollection([$configuration]));

        $resolver = new FrontendBundleConfigStyleFileResolver($registry);

        static::assertSame(
            [
                'custom/plugins/CtPlugin/Resources/app/frontend/src/scss/base.scss',
                'custom/plugins/CtPlugin/Resources/app/frontend/src/scss/overrides.scss',
            ],
            $resolver->resolveStyleFiles('CtPlugin', 'custom/plugins/CtPlugin'),
        );
    }
}
