<?php declare(strict_types=1);

namespace Contena\Core\Content;

use Contena\Core\Content\Mail\MailerConfigurationCompilerPass;
use Contena\Core\Content\Media\DependencyInjection\ThumbnailProcessorCompilerPass;
use Contena\Core\Framework\Bundle;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

/**
 * @internal
 */
class Content extends Bundle
{
    /**
     * {@inheritdoc}
     */
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/DependencyInjection/'));
        $loader->load('media.php');
        $loader->load('media_path.php');
        $loader->load('mail.php');
        $loader->load('mail_template.php');
        $loader->load('rule.php');
        $loader->load('flow.php');
        $loader->load('cookie.php');
        $loader->load('breadcrumb.php');
        $loader->load('category.php');
        $loader->load('landing_page.php');
        $loader->load('blog.php');
        $loader->load('sitemap.php');
        $loader->load('shared.php');
        if ($container->getParameter('kernel.environment') === 'test') {
            $loader->load('media_test.php');
        }

        $container->addCompilerPass(new ThumbnailProcessorCompilerPass());
        $container->addCompilerPass(new MailerConfigurationCompilerPass());
    }
}
