<?php

namespace Driebit\PrepperBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html#cookbook-bundles-extension-config-class}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('driebit_prepper');

        $rootNode
            ->children()
                ->arrayNode('stores')
                    ->useAttributeAsKey('name')
                    ->addDefaultChildrenIfNoneSet('default')
                    ->prototype('array')
                         ->children()
                            ->scalarNode('name')->isRequired()->end()
                            ->scalarNode('type')->defaultValue('file')->end()
                            ->scalarNode('path')->defaultValue('%kernel.cache_dir%/prepper')->end()
                        ->end()
                    ->end()
                ->end()
                ->scalarNode('default_store')->defaultValue('default')->end();

        $this->addFixtureLoaderSection($rootNode);

        $rootNode
            ->end();

        return $treeBuilder;

    }

    public function addFixtureLoaderSection(ArrayNodeDefinition $rootNode)
    {
        $orm = $rootNode
            ->children()
                ->arrayNode('fixture_loader')
                    ->isRequired()
                    ->cannotBeEmpty()
                    ->children()
                        ->arrayNode('orm')
                            ->children()
                                ->scalarNode('entity_manager')->defaultValue('doctrine.orm.entity_manager')->end();

        $this->addFixtureLoaderCommonSection($orm);

        $mongo = $orm
                            ->end()
                        ->end()
                        ->arrayNode('mongo')
                            ->children()
                                ->scalarNode('document_manager')->defaultValue('')->end();

        $this->addFixtureLoaderCommonSection($mongo);

        $mongo
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    public function addFixtureLoaderCommonSection(NodeBuilder $fixtureLoader)
    {
        $fixtureLoader
            ->arrayNode('resetters')
                ->children()
                    ->arrayNode('sqlite')
                        ->canBeEnabled()
                        ->children()
                            ->scalarNode('entity_manager')->defaultValue('driebit_prepper.fixture_loader.entity_manager')->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
            ->arrayNode('caches')
                ->cannotBeEmpty()
                ->children()
                    ->arrayNode('reference')
                        ->canBeEnabled()
                        ->children()
                            ->scalarNode('repository')->defaultValue('driebit_prepper.reference_repository')->end()
                            ->scalarNode('store')->defaultValue('driebit_prepper.cache.store.default')->end()
                        ->end()
                    ->end()
                    ->arrayNode('sqlite_copy')
                        ->canBeEnabled()
                        ->children()
                            ->scalarNode('entity_manager')->defaultValue('doctrine.orm.entity_manager')->end()
                            ->scalarNode('store')->defaultValue('driebit_prepper.cache.store.default')->end()
                        ->end()
                    ->end()
                    ->arrayNode('mongo_dump')
                        ->canBeEnabled()
                        ->children()
                            ->scalarNode('store')->defaultValue('driebit_prepper.cache.store.default')->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }
}



