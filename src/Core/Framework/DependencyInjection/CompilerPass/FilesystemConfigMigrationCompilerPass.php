<?php declare(strict_types=1);

namespace Contena\Core\Framework\DependencyInjection\CompilerPass;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class FilesystemConfigMigrationCompilerPass implements CompilerPassInterface
{
    private const array MIGRATED_FS = ['theme', 'asset', 'sitemap'];

    public function process(ContainerBuilder $container): void
    {
        foreach (self::MIGRATED_FS as $fs) {
            $key = \sprintf('contena.filesystem.%s', $fs);
            $urlKey = $key . '.url';
            $typeKey = $key . '.type';
            $configKey = $key . '.config';
            $visibilityKey = $key . '.visibility';

            if (!$container->hasParameter($visibilityKey)) {
                $container->setParameter($visibilityKey, '%contena.filesystem.public.visibility%');
            }

            if ($container->hasParameter($typeKey)) {
                continue;
            }

            // 6.1 always refers to the main application URL on theme, asset and sitemap.
            $container->setParameter($urlKey, '');
            $container->setParameter($key, '%contena.filesystem.public%');
            $container->setParameter($typeKey, '%contena.filesystem.public.type%');
            $container->setParameter($configKey, '%contena.filesystem.public.config%');
        }

        if (!$container->hasParameter('contena.filesystem.public.url')) {
            $container->setParameter('contena.filesystem.public.url', '%contena.cdn.url%');
        }

        if (!$container->hasParameter('contena.filesystem.public.visibility')) {
            $container->setParameter('contena.filesystem.public.visibility', 'public');
        }
    }
}
