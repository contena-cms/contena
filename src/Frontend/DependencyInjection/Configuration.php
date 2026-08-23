<?php declare(strict_types=1);

namespace Contena\Frontend\DependencyInjection;

use Contena\Frontend\Framework\Routing\ClearSiteDataListener;
use Contena\Frontend\Theme\ConfigLoader\DatabaseAvailableThemeProvider;
use Contena\Frontend\Theme\ConfigLoader\DatabaseConfigLoader;
use Contena\Frontend\Theme\MD5ThemePathBuilder;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('frontend');

        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->arrayNode('theme')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('config_loader_id')->defaultValue(DatabaseConfigLoader::class)->end()
                        ->scalarNode('available_theme_provider')->defaultValue(DatabaseAvailableThemeProvider::class)->end()
                        ->scalarNode('theme_path_builder_id')->defaultValue(MD5ThemePathBuilder::class)->end()
                        ->arrayNode('allowed_scss_values')->performNoDeepMerging()
                            ->defaultValue(['^\$.*'])
                            ->scalarPrototype()->end()
                        ->end()
                        ->booleanNode('validate_on_compile')->defaultFalse()->end()
                    ->end()
                ->end()
                ->arrayNode('router')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('allowed_routes')
                            ->prototype('string')->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('security')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('clear_site_data_on_logout')
                            ->info('Directives sent as `Clear-Site-Data` header when a member logs out. Empty by default, meaning no header is sent.')
                            ->performNoDeepMerging()
                            ->defaultValue([])
                            ->scalarPrototype()->end()
                            ->validate()
                                ->ifTrue(static fn (array $directives): bool => array_diff($directives, ClearSiteDataListener::ALLOWED_DIRECTIVES) !== [])
                                ->thenInvalid(
                                    'Invalid Clear-Site-Data directive in %s. Allowed values are "cache", "cookies" and "storage". '
                                    . '"executionContexts", "clientHints" and "*" are not supported.'
                                )
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
