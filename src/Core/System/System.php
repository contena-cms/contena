<?php declare(strict_types=1);

namespace Contena\Core\System;

use Contena\Core\Framework\Bundle;
use Contena\Core\System\DependencyInjection\CompilerPass\ChannelEntityCompilerPass;
use Contena\Core\System\DependencyInjection\CompilerPass\NumberRangeIncrementerCompilerPass;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

/**
 * @internal
 */
class System extends Bundle
{
    public function getTemplatePriority(): int
    {
        return -1;
    }

    /**
     * {@inheritdoc}
     */
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $configLocator = new FileLocator(__DIR__ . '/DependencyInjection/');

        $phpLoader = new PhpFileLoader($container, $configLocator);
        $phpLoader->load('country.php');
        $phpLoader->load('region.php');
        $phpLoader->load('organization.php');
        $phpLoader->load('position.php');
        $phpLoader->load('locale.php');
        $phpLoader->load('snippet.php');
        $phpLoader->load('channel.php');
        $phpLoader->load('member.php');
        $phpLoader->load('user.php');
        $phpLoader->load('integration.php');
        $phpLoader->load('state_machine.php');
        $phpLoader->load('configuration.php');
        $phpLoader->load('number_range.php');
        $phpLoader->load('tag.php');
        $phpLoader->load('consent.php');
        $phpLoader->load('data_dictionary.php');
        $phpLoader->load('tenant.php');

        if ($container->getParameter('kernel.environment') === 'test') {
            $phpLoader->load('services_test.php');
        }

        $container->addCompilerPass(new ChannelEntityCompilerPass());
        $container->addCompilerPass(new NumberRangeIncrementerCompilerPass());
    }
}
