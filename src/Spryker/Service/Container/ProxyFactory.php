<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Service\Container;

use ProxyManager\Factory\LazyLoadingValueHolderFactory;
use ReflectionMethod;

class ProxyFactory
{
    public function __construct(
        protected LazyLoadingValueHolderFactory $factory = new LazyLoadingValueHolderFactory(),
        protected ContainerConfig $config = new ContainerConfig(),
    ) {
    }

    public function createProxy(string $className): object
    {
        $initializer = function (&$wrappedObject, $proxy, $method, $params, &$initializer) use ($className) {
            $containerDelegator = ContainerDelegator::getInstance();

            $wrappedObject = $containerDelegator->get($className);

            $initializer = null; // Prevent this from running again.

            return true; // Return true to indicate success.
        };

        return $this->factory->createProxy($className, $initializer);
    }

    /**
     * This method is exclusively used for resolving plugin stacks. Currently, it works by configuring the Stack attribute
     * on the class that needs a specific dependency.
     *
     * @see \Spryker\Service\Container\Attributes\Stack
     * @see \Spryker\Service\Container\Pass\StackResolverPass::addDependencyProviderProxy()
     */
    public function createPluginProviderProxy(string $dependencyProviderClassName, string $getterMethodName): array
    {
        $dependencyProvider = $this->resolveDependencyProvider($dependencyProviderClassName);

        $reflectionMethod = new ReflectionMethod($dependencyProvider, $getterMethodName);

        return $reflectionMethod->invoke($dependencyProvider);
    }

    /**
     * Resolves a single plugin instance from a DependencyProvider method. Counterpart of
     * {@see createPluginProviderProxy()} for the legacy single-plugin pattern used by
     * modules that register one plugin per slot in their DependencyProvider.
     *
     * @see \Spryker\Service\Container\Attributes\Plugin
     * @see \Spryker\Service\Container\Pass\StackResolverPass::resolvePluginAttributes()
     */
    public function createSinglePluginProviderProxy(string $dependencyProviderClassName, string $getterMethodName): object
    {
        $dependencyProvider = $this->resolveDependencyProvider($dependencyProviderClassName);

        $reflectionMethod = new ReflectionMethod($dependencyProvider, $getterMethodName);

        return $reflectionMethod->invoke($dependencyProvider);
    }

    /**
     * Resolves the DependencyProvider by checking:
     * 1. The container for explicit project-level registrations (backward compatible)
     * 2. Project namespaces for class overrides (e.g., Pyz) via class_exists
     * 3. Falls back to the core class
     */
    protected function resolveDependencyProvider(string $dependencyProviderClassName): object
    {
        // Respects explicit project-level registrations in ApplicationServices.php
        $containerDelegator = ContainerDelegator::getInstance();

        if ($containerDelegator->has($dependencyProviderClassName)) {
            return $containerDelegator->get($dependencyProviderClassName);
        }

        // Namespace resolution: check project namespaces for overrides
        $parts = explode('\\', $dependencyProviderClassName);
        $organisation = $parts[0];

        foreach ($this->config->getProjectNamespaces() as $projectNamespace) {
            $projectClassName = str_replace($organisation . '\\', $projectNamespace . '\\', $dependencyProviderClassName);

            if ($projectClassName !== $dependencyProviderClassName && class_exists($projectClassName)) {
                return new $projectClassName();
            }
        }

        return new $dependencyProviderClassName();
    }
}
