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
    public function __construct(protected LazyLoadingValueHolderFactory $factory = new LazyLoadingValueHolderFactory())
    {
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
        $containerDelegator = ContainerDelegator::getInstance();

        /**
         * Check if we may have an overridden DependencyProvider on the project level.
         *
         * If not, create an instance of it.
         *
         * Since the methods are protected, we need to use reflection to be able to access the protected method. Return
         * the plugins provided on the project level (empty stack if not provided on the project level as defined in the core.)
         */
        if ($containerDelegator->has($dependencyProviderClassName)) {
            $dependencyProvider = $containerDelegator->get($dependencyProviderClassName);
        } else {
            $dependencyProvider = new $dependencyProviderClassName();
        }

        $reflectionMethod = new ReflectionMethod($dependencyProvider, $getterMethodName);
        $reflectionMethod->setAccessible(true);

        return $reflectionMethod->invoke($dependencyProvider);
    }
}
