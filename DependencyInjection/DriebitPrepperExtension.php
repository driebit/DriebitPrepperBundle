<?php

namespace Driebit\PrepperBundle\DependencyInjection;

use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;

/**
 * This is the class that loads and manages your bundle configuration
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class DriebitPrepperExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));

        $this->loadCacheStores($container, $loader, $config['stores']);

        if (isset($config['fixture_loader']['orm'])) {
            $this->loadOrmFixtureLoader($container, $loader, $config['fixture_loader']['orm']);
        }

        if (isset($config['fixture_loader']['mongo'])) {
            $loader->load('fixture_loader_mongo');
        }

        // Set default alias
        $container->setAlias($this->getAlias() . '.default_fixture_loader', $this->getDefaultFixtureLoader($container));
    }

    private function getDefaultFixtureLoader(ContainerBuilder $container)
    {
        $definitions = array(
            '.fixture_loader.cacheable',
            '.fixture_loader.resettable',
            '.fixture_loader.orm'
        );

        foreach ($definitions as $definition) {
            $fullDefinition = $this->getAlias() . $definition;
            if ($container->hasDefinition($fullDefinition)) {
                return $fullDefinition;
            }
        }
    }

    private function loadCacheStores(ContainerBuilder $container, LoaderInterface $loader, array $config)
    {
        foreach ($config as $key => $storeConfig) {
            $loader->load('cache/store/' . $storeConfig['type'] . '.xml');

            $definition = $container->getDefinition('driebit_prepper.cache.file_store.abstract')
                ->replaceArgument(0, $storeConfig['path']);
            $container->setDefinition($this->getAlias() . '.cache.store.' . $key, $definition)
                ->setAbstract(false);
        }
    }

    private function loadOrmFixtureLoader(ContainerBuilder $container, LoaderInterface $loader, array $config)
    {
        $loader->load('fixture_loader_orm.xml');
        $fixtureLoaderId = $this->getAlias() . '.fixture_loader.orm';
        $definition = $container->getDefinition($fixtureLoaderId)
            ->replaceArgument(0, new Reference($config['entity_manager']));

        if (count($config['resetters'] > 0)) {
            $loader->load('resetter/chain.xml');
            $resetterChain = $container->getDefinition($this->getAlias() . '.resetter.chain');

            $fixtureLoaderId = $this->getAlias() . '.fixture_loader.orm.resettable';
            $definition = $container->getDefinition($fixtureLoaderId)
                ->replaceArgument(0, new Reference($this->getAlias() . '.fixture_loader.orm'))
                ->replaceArgument(1, $resetterChain)
                ->setAbstract(false);

            if ($config['resetters']['sqlite']['enabled']) {
                $loader->load('resetter/sqlite.xml');
                $restter = $container->getDefinition($this->getAlias() . '.resetter.sqlite')
                    ->replaceArgument(0, new Reference($config['entity_manager']));
                $resetterChain->addMethodCall('addResetter', array(new Reference($this->getAlias() . '.resetter.sqlite')));
            }
        }

        if (count($config['caches'] > 0)) {
            $loader->load('fixture_loader/cacheable.xml');
            $cacheChain = $container->getDefinition($this->getAlias() . '.fixture_loader.cache_chain');
            $cacheable = $container->getDefinition($this->getAlias() . '.fixture_loader.cacheable')
                ->replaceArgument(0, $definition)
                ->replaceArgument(1, $cacheChain);

            if ($config['caches']['reference']['enabled']) {
                $loader->load('cache/reference_cache.xml');
                $definition = $container->getDefinition($this->getAlias() . '.cache.reference')
                    ->replaceArgument(0, new Reference($config['caches']['reference']['repository']))
                    ->replaceArgument(1, new Reference($config['caches']['reference']['store']));
                $cacheChain->addMethodCall('addCache', array($definition));
            }

            if ($config['caches']['sqlite_copy']['enabled']) {
                $loader->load('cache/sqlite_copy_cache.xml');
                $id = $this->getAlias() . '.cache.sqlite_copy';
                $definition = $container->getDefinition($id)
                    ->replaceArgument(0, new Reference($config['caches']['sqlite_copy']['entity_manager']))
                    ->replaceArgument(1, new Reference($config['caches']['sqlite_copy']['store']));
                $cacheChain->addMethodCall('addCache', array(new Reference($id)));
            }
        }
    }
}
