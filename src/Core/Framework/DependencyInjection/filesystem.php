<?php declare(strict_types=1);

namespace Contena\Core\Framework\DependencyInjection;

use League\Flysystem\FilesystemOperator;
use Contena\Core\Framework\Adapter\Asset\AssetInstallCommand;
use Contena\Core\Framework\Adapter\Asset\FallbackUrlPackage;
use Contena\Core\Framework\Adapter\Asset\FlysystemLastModifiedVersionStrategy;
use Contena\Core\Framework\Adapter\Filesystem\Adapter\AwsS3v3Factory;
use Contena\Core\Framework\Adapter\Filesystem\Adapter\GoogleStorageFactory;
use Contena\Core\Framework\Adapter\Filesystem\Adapter\LocalFactory;
use Contena\Core\Framework\Adapter\Filesystem\FilesystemFactory;
use Contena\Core\Framework\Adapter\Filesystem\Plugin\CopyBatchInputFactory;
use Contena\Core\Framework\Plugin\Util\AssetService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    // Filesystem
    $services->set(FilesystemFactory::class)
        ->args([
            tagged_iterator('contena.filesystem.factory'),
        ]);

    $services->set('contena.filesystem.public', FilesystemOperator::class)
        ->public()
        ->factory([service(FilesystemFactory::class), 'factory'])
        ->args([
            param('contena.filesystem.public'),
        ]);

    $services->set('contena.filesystem.private', FilesystemOperator::class)
        ->public()
        ->factory([service(FilesystemFactory::class), 'privateFactory'])
        ->args([
            param('contena.filesystem.private'),
        ]);

    $services->set('contena.filesystem.temp', FilesystemOperator::class)
        ->public()
        ->factory([service(FilesystemFactory::class), 'privateFactory'])
        ->args([
            param('contena.filesystem.temp'),
        ]);

    $services->set('contena.filesystem.theme', FilesystemOperator::class)
        ->public()
        ->factory([service(FilesystemFactory::class), 'factory'])
        ->args([
            param('contena.filesystem.theme'),
        ]);

    $services->set('contena.filesystem.sitemap', FilesystemOperator::class)
        ->public()
        ->factory([service(FilesystemFactory::class), 'factory'])
        ->args([
            param('contena.filesystem.sitemap'),
        ]);

    $services->set('contena.filesystem.asset', FilesystemOperator::class)
        ->public()
        ->factory([service(FilesystemFactory::class), 'factory'])
        ->args([
            param('contena.filesystem.asset'),
        ]);

    $services->set(FilesystemFactory::class . '.local', LocalFactory::class)
        ->tag('contena.filesystem.factory');

    $services->set(FilesystemFactory::class . '.amazon_s3', AwsS3v3Factory::class)
        ->args([
            param('contena.filesystem.batch_write_size'),
            service('contena.filesystem.s3.client')->nullOnInvalid(),
        ])
        ->tag('contena.filesystem.factory');

    $services->set(FilesystemFactory::class . '.google_storage', GoogleStorageFactory::class)
        ->tag('contena.filesystem.factory');

    $services->set('console.command.assets_install', AssetInstallCommand::class)
        ->args([
            service('kernel'),
            service(AssetService::class),
        ])
        ->tag('console.command');

    // Assets
    $services->set('contena.asset.public', FallbackUrlPackage::class)
        ->lazy()
        ->args([
            [
                param('contena.filesystem.public.url'),
            ],
            service('assets.empty_version_strategy'),
            service('request_stack')->nullOnInvalid(),
        ])
        ->tag('contena.asset', ['asset' => 'public']);

    $services->set('contena.asset.public.version_strategy', FlysystemLastModifiedVersionStrategy::class)
        ->args([
            'theme-metaData',
            service('contena.filesystem.public'),
            service('cache.object'),
        ]);

    $services->set('contena.asset.theme.version_strategy', FlysystemLastModifiedVersionStrategy::class)
        ->args([
            'theme-metaData',
            service('contena.filesystem.theme'),
            service('cache.object'),
        ]);

    $services->set('contena.asset.asset.version_strategy', FlysystemLastModifiedVersionStrategy::class)
        ->args([
            'asset-metaData',
            service('contena.filesystem.asset'),
            service('cache.object'),
        ]);

    $services->set('contena.asset.asset', FallbackUrlPackage::class)
        ->lazy()
        ->args([
            [
                param('contena.filesystem.asset.url'),
            ],
            service('contena.asset.asset.version_strategy'),
            service('request_stack')->nullOnInvalid(),
        ])
        ->tag('contena.asset', ['asset' => 'asset']);

    $services->set('contena.asset.asset_without_versioning', FallbackUrlPackage::class)
        ->lazy()
        ->args([
            [
                param('contena.filesystem.asset.url'),
            ],
            service('assets.empty_version_strategy'),
            service('request_stack')->nullOnInvalid(),
        ]);

    $services->set('contena.asset.sitemap', FallbackUrlPackage::class)
        ->lazy()
        ->args([
            [
                param('contena.filesystem.sitemap.url'),
            ],
            service('assets.empty_version_strategy'),
            service('request_stack')->nullOnInvalid(),
        ])
        ->tag('contena.asset', ['asset' => 'sitemap']);

    $services->set(CopyBatchInputFactory::class);
};
