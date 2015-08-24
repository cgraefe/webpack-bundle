<?php
namespace Hostnet\Bundle\WebpackBridge\DependencyInjection;

use Hostnet\Component\WebpackBridge\Configuration\Config\ConfigInterface;
use Hostnet\Component\WebpackBridge\Configuration\Loader\LoaderInterface;
use Hostnet\Component\WebpackBridge\Configuration\Plugin\PluginInterface;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * @author Harold Iedema <hiedema@hostnet.nl>
 */
class Configuration implements ConfigurationInterface
{
    const CONFIG_ROOT = 'webpack';

    private $bundles;
    private $plugins;

    /**
     * @param array $bundles
     * @param array $plugins
     */
    public function __construct(array $bundles = [], array $plugins = [])
    {
        $this->bundles = $bundles;
        $this->plugins = $plugins;
    }

    /** {@inheritdoc} */
    public function getConfigTreeBuilder()
    {
        $tree_builder = new TreeBuilder();
        $root_node    = $tree_builder->root(self::CONFIG_ROOT);
        $children     = $root_node->children();

        $root_node->fixXmlConfig('bundle');

        $this->addNodeJSConfiguration($children);
        $this->addParentConfiguration($children);
        $this->addBundleConfiguration($children);
        $this->addLoaderConfiguration($children);
        $this->addPluginConfiguration($children);

        $children->end();

        return $tree_builder;
    }

    /**
     * Adds node-js specific configuration to the tree builder.
     *
     * @param NodeBuilder $node
     */
    private function addNodeJSConfiguration(NodeBuilder $node)
    {
        $node
            ->arrayNode('node')
            ->addDefaultsIfNotSet()
            ->children()
                ->arrayNode('binary')
                    ->addDefaultsIfNotSet()
                    ->beforeNormalization()
                    ->ifString()
                        ->then(function($value) { return
                            [
                                'win32'     => $value,
                                'win64'     => $value,
                                'linux_x32' => $value,
                                'linux_x64' => $value,
                                'darwin'    => $value,
                                'fallback'  => $value
                            ];
                        })
                    ->end()
                    ->children()
                        ->scalarNode('win32')->defaultValue('node')->end()
                        ->scalarNode('win64')->defaultValue('node')->end()
                        ->scalarNode('linux_x32')->defaultValue('node')->end()
                        ->scalarNode('linux_x64')->defaultValue('node')->end()
                        ->scalarNode('darwin')->defaultValue('node')->end()
                        ->scalarNode('fallback')->defaultValue('node')->end()
                    ->end()
                ->end()
                ->scalarNode('npm_packages_path')->defaultNull()->end()
                ->scalarNode('node_modules_path')->defaultNull()->end()
            ->end();
    }

    /**
     * Adds generic configuration to the tree builder in the parent (root) node.
     *
     * @param NodeBuilder $node
     */
    private function addParentConfiguration(NodeBuilder $node)
    {
        $this->applyConfigurationFromClass(ConfigInterface::class, $node);
    }

    /**
     * Adds bundle configuration to the tree builder.
     *
     * @param NodeBuilder $node
     */
    private function addBundleConfiguration(NodeBuilder $node)
    {
        $node
            ->arrayNode('bundles')
                ->defaultValue($this->bundles)
                ->prototype('scalar')
                    ->validate()
                    ->ifNotInArray($this->bundles)
                    ->thenInvalid('%s is not a valid bundle.')
                ->end()
            ->end();
    }

    /**
     * @param NodeBuilder $node
     */
    private function addPluginConfiguration(NodeBuilder $node)
    {
        $children = $node
            ->arrayNode('plugins')
            ->addDefaultsIfNotSet()
            ->children();

        $this->applyConfigurationFromClass(PluginInterface::class, $children);
        $children->end();
    }

    /**
     * Adds loader configuration to the tree builder.
     *
     * @param NodeBuilder $node
     */
    private function addLoaderConfiguration(NodeBuilder $node)
    {
        $children = $node
            ->arrayNode('loaders')
            ->addDefaultsIfNotSet()
            ->children();

        $this->applyConfigurationFromClass(LoaderInterface::class, $children);
        $children->end();
    }

    /**
     * @param string      $interface
     * @param NodeBuilder $node_builder
     */
    private function applyConfigurationFromClass($interface, NodeBuilder $node_builder)
    {
        foreach ($this->plugins as $name => $class_name) {
            // Only accept plugins of type PluginInterface.
            if (! in_array($interface, class_implements($class_name))) {
                continue;
            }

            /* @var $class_name \Hostnet\Component\WebpackBridge\Configuration\ConfigExtensionInterface */
            $class_name::applyConfiguration($node_builder);
        }
    }
}
