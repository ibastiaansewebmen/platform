<?php

namespace Oro\Bundle\FormBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class AutocompleteCompilerPass implements CompilerPassInterface
{
    /**
     * @param ContainerBuilder $container
     */
    public function process(ContainerBuilder $container)
    {
        $searchRegistryDefinition = $container->getDefinition('oro_form.autocomplete.search_registry');
        $securityDefinition = $container->getDefinition('oro_form.autocomplete.security');

        foreach ($container->findTaggedServiceIds('oro_form.autocomplete.search_handler') as $id => $attributes) {
            foreach ($attributes as $eachTag) {
                $name = !empty($eachTag['alias']) ? $eachTag['alias'] : $id;
                $searchRegistryDefinition->addMethodCall('addSearchHandler', array($name, new Reference($id)));
                if (!empty($eachTag['acl_resource'])) {
                    $securityDefinition->addMethodCall(
                        'setAutocompleteAclResource',
                        array($name, $eachTag['acl_resource'])
                    );
                }
            }
        }
    }
}
