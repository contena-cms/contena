<?php declare(strict_types=1);

namespace Contena\Frontend;

use Contena\Core\Framework\Bundle;
use Contena\Frontend\DependencyInjection\DisableTemplateCachePass;
use Contena\Frontend\DependencyInjection\FrontendMigrationReplacementCompilerPass;
use Contena\Frontend\DependencyInjection\ThemeCompilerAssetCompilerPass;
use Contena\Frontend\DependencyInjection\TwigComponentBundlePass;
use Contena\Frontend\Framework\ThemeInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

/**
 * @internal
 */
class Frontend extends Bundle implements ThemeInterface
{
    /**
     * {@inheritdoc}
     */
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $this->buildDefaultConfig($container);

        $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/DependencyInjection'));
        $loader->load('services.php');
        $loader->load('captcha.php');
        $loader->load('seo.php');
        $loader->load('controller.php');
        $loader->load('theme.php');
        $loader->load('system.php');
        $loader->load('mcp.php');

        $container->setParameter('frontendRoot', $this->getPath());

        $container->addCompilerPass(new DisableTemplateCachePass());
        $container->addCompilerPass(new FrontendMigrationReplacementCompilerPass());
        $container->addCompilerPass(new ThemeCompilerAssetCompilerPass());
        // Auto-register Twig component namespaces for all bundles
        // Must run before Symfony's TwigComponentPass processes the configuration
        $container->addCompilerPass(new TwigComponentBundlePass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 100);
    }
}
