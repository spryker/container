<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Service\Container\Pass;

use ReflectionClass;
use ReflectionException;
use Spryker\Service\Container\Attributes\Stack;
use Spryker\Service\Container\ProxyFactory;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class StackResolverPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $definitionIds = $container->getServiceIds();

        foreach ($definitionIds as $id) {
            // For some reason proxies will be tried to be added to Orm classes when the constructor where it is used has a Stack attribute defined.
            // We have to skip Orm files.
            if (!$container->hasDefinition($id) || $this->isPropelService($id)) {
                continue;
            }

            $definition = $container->getDefinition($id);

            $class = $definition->getClass();

            try {
                if ($class && !class_exists($class)) {
                    continue;
                }
                /** @phpstan-var class-string $class */
                $reflectionClass = new ReflectionClass($class);
            } catch (ReflectionException) {
                continue;
            }

            // We have to check the class names attributes and the __constructor attributes
            $classAttributes = $reflectionClass->getAttributes(Stack::class);

            if ($classAttributes) {
                foreach ($classAttributes as $classAttribute) {
                    /** @var \Spryker\Service\Container\Attributes\Stack $stackAttribute */
                    $stackAttribute = $classAttribute->newInstance();
                    $definition->addTag((string)$stackAttribute->service);
                }
            }

            $constructor = $reflectionClass->getConstructor();

            if (!$constructor) {
                continue;
            }

            $constructorAttributes = $constructor->getAttributes(Stack::class);

            if ($constructorAttributes) {
                foreach ($constructorAttributes as $constructorAttribute) {
                    /** @var \Spryker\Service\Container\Attributes\Stack $stackAttribute */
                    $stackAttribute = $constructorAttribute->newInstance();

                    if ($stackAttribute->dependencyProvider && $stackAttribute->dependencyProviderMethod) {
                        $this->addDependencyProviderProxy($container, $definition, $stackAttribute);

                        continue;
                    }

                    $definition->setArgument((string)$stackAttribute->provideToArgument, new TaggedIteratorArgument((string)$stackAttribute->service));
                }
            }
        }
    }

    protected function addDependencyProviderProxy(ContainerBuilder $container, Definition $definition, Stack $stackAttribute): void
    {
        $pluginStackServiceId = sprintf('%s.%s', $definition->getClass(), $stackAttribute->provideToArgument);

        $factoryDefinition = new Definition('array');
        $factoryDefinition->setFactory([new Reference(ProxyFactory::class), 'createPluginProviderProxy']);
        $factoryDefinition->setArguments([$stackAttribute->dependencyProvider, $stackAttribute->dependencyProviderMethod]);

        $container->setDefinition($pluginStackServiceId, $factoryDefinition);

        // Allow setting this with and without $ prefix.
        $definition->setArgument('$' . ltrim((string)$stackAttribute->provideToArgument, '$'), new Reference($pluginStackServiceId));
    }

    protected function isPropelService(string $dependencyId): bool
    {
        return str_starts_with($dependencyId, 'Orm\\');
    }
}
