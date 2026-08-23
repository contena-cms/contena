<?php declare(strict_types=1);

namespace Contena\Frontend;

use Contena\Core\Framework\Bundle;
use Contena\Frontend\DependencyInjection\DisableTemplateCachePass;
use Contena\Frontend\DependencyInjection\FrontendMigrationReplacementCompilerPass;
use Contena\Frontend\DependencyInjection\ThemeCompilerAssetCompilerPass;
use Contena\Frontend\DependencyInjection\TwigComponentBundlePass;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

/**
 * Generic HTML frontend bundle.
 *
 * The bundle intentionally contains no commerce concepts. Plugins can provide
 * themes, Twig components and routes through the normal Contena bundle API.
 *
 * @internal
 */
final class Frontend extends Bundle implements Framework\ThemeInterface
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $this->buildDefaultConfig($container);

        $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/DependencyInjection'));
        $loader->load('content-system.php');
        $loader->load('seo.php');
        $loader->load('services.php');
        $loader->load('captcha.php');
        $loader->load('system.php');
        $loader->load('theme.php');
        $loader->load('mcp.php');

        $container->setParameter('frontendRoot', $this->getPath());
        $container->addCompilerPass(new DisableTemplateCachePass());
        $container->addCompilerPass(new FrontendMigrationReplacementCompilerPass());
        $container->addCompilerPass(new ThemeCompilerAssetCompilerPass());
        $container->addCompilerPass(new TwigComponentBundlePass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 100);
    }
}
