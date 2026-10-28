<?php declare(strict_types=1);

namespace Contena\Storefront\DependencyInjection;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Contena\Storefront\Theme\ThemeAssetPackage;
use Contena\Storefront\Theme\Twig\ThemeInheritanceBuilder;
use Contena\Storefront\Theme\Twig\ThemeInheritanceBuilderInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Filesystem\Filesystem;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

































    $services->set('contena.asset.theme', ThemeAssetPackage::class)
        ->lazy()
        ->args([
            [
                param('contena.filesystem.theme.url'),
            ],
            service('contena.asset.theme.version_strategy'),
            service('request_stack'),
        ])
        ->tag('contena.asset', ['asset' => 'theme']);


















    $services->set(ThemeInheritanceBuilderInterface::class, ThemeInheritanceBuilder::class)
        ->args([
        ]);










};
