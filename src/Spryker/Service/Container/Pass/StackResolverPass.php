<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Service\Container\Pass;

use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionNamedType;
use Spryker\Service\Container\Attributes\Plugin;
use Spryker\Service\Container\Attributes\Plugins;
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

            // Skip factory-built definitions (e.g. ProxyPass interface proxies). Their `class` is the
            // consumer's class — reflecting it and applying constructor-attribute logic would set named
            // arguments on the factory call (e.g. ProxyFactory::createProxy()) and break compilation.
            if ($definition->getFactory() !== null) {
                continue;
            }

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

            $this->resolvePluginsAttributes($container, $definition, $constructor);
            $this->resolvePluginAttributes($container, $definition, $constructor);
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

    protected function resolvePluginsAttributes(
        ContainerBuilder $container,
        Definition $definition,
        ReflectionMethod $constructor,
    ): void {
        foreach ($constructor->getParameters() as $parameter) {
            $pluginsAttributes = $parameter->getAttributes(Plugins::class);

            if (!$pluginsAttributes) {
                continue;
            }

            /** @var \Spryker\Service\Container\Attributes\Plugins $pluginsAttribute */
            $pluginsAttribute = $pluginsAttributes[0]->newInstance();
            $dependencyProvider = $this->resolveDependencyProvider((string)$definition->getClass());
            $argumentName = sprintf('$%s', $parameter->getName());

            $pluginStackServiceId = sprintf('%s.%s', $definition->getClass(), $argumentName);

            $factoryDefinition = new Definition('array');
            $factoryDefinition->setFactory([new Reference(ProxyFactory::class), 'createPluginProviderProxy']);
            $factoryDefinition->setArguments([$dependencyProvider, $pluginsAttribute->dependencyProviderMethod]);

            $container->setDefinition($pluginStackServiceId, $factoryDefinition);

            $definition->setArgument($argumentName, new Reference($pluginStackServiceId));
        }
    }

    protected function resolvePluginAttributes(
        ContainerBuilder $container,
        Definition $definition,
        ReflectionMethod $constructor,
    ): void {
        foreach ($constructor->getParameters() as $parameter) {
            $pluginAttributes = $parameter->getAttributes(Plugin::class);

            if (!$pluginAttributes) {
                continue;
            }

            /** @var \Spryker\Service\Container\Attributes\Plugin $pluginAttribute */
            $pluginAttribute = $pluginAttributes[0]->newInstance();
            $dependencyProvider = $this->resolveDependencyProvider((string)$definition->getClass());
            $argumentName = sprintf('$%s', $parameter->getName());

            $pluginServiceId = sprintf('%s.%s', $definition->getClass(), $argumentName);

            $parameterType = $parameter->getType();
            $definitionClass = ($parameterType instanceof ReflectionNamedType) ? $parameterType->getName() : 'object';

            $factoryDefinition = new Definition($definitionClass);
            $factoryDefinition->setFactory([new Reference(ProxyFactory::class), 'createSinglePluginProviderProxy']);
            $factoryDefinition->setArguments([$dependencyProvider, $pluginAttribute->dependencyProviderMethod]);

            $container->setDefinition($pluginServiceId, $factoryDefinition);

            $definition->setArgument($argumentName, new Reference($pluginServiceId));
        }
    }

    /**
     * Resolves the DependencyProvider fully qualified class name from the service class namespace.
     *
     * Convention: `{Organization}\{Layer}\{Module}\{Module}DependencyProvider`
     */
    protected function resolveDependencyProvider(string $className): string
    {
        $namespaceParts = explode('\\', $className);
        $moduleNamespace = sprintf('%s\%s\%s', $namespaceParts[0], $namespaceParts[1], $namespaceParts[2]);

        return sprintf('%s\%sDependencyProvider', $moduleNamespace, $namespaceParts[2]);
    }

    protected function isPropelService(string $dependencyId): bool
    {
        return str_starts_with($dependencyId, 'Orm\\');
    }
}
