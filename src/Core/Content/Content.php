<?php declare(strict_types=1);

namespace Contena\Core\Content;

use Contena\Core\Content\Mail\MailerConfigurationCompilerPass;
use Contena\Core\Content\Media\DependencyInjection\ThumbnailProcessorCompilerPass;
use Contena\Core\Framework\Bundle;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
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

        $phpLoader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/DependencyInjection/'));
        $phpLoader->load('breadcrumb.php');
        $phpLoader->load('category.php');
        $phpLoader->load('cookie.php');
        $phpLoader->load('media.php');
        $phpLoader->load('media_path.php');
        $phpLoader->load('blog.php');
        $phpLoader->load('rule.php');
        $phpLoader->load('blog_stream.php');
        $phpLoader->load('property.php');
        $phpLoader->load('cms.php');
        $phpLoader->load('mail.php');
        $phpLoader->load('mail_template.php');
        $phpLoader->load('import_export.php');
        $phpLoader->load('sitemap.php');
        $phpLoader->load('landing_page.php');
        $phpLoader->load('flow.php');
        $phpLoader->load('shared.php');


        if ($container->getParameter('kernel.environment') === 'test') {
            $phpLoader->load('media_test.php');
        }

        $container->addCompilerPass(new MailerConfigurationCompilerPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 0);
        $container->addCompilerPass(new ThumbnailProcessorCompilerPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 0);
    }
}
