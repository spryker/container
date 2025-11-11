<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Service\Container\Pass;

use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use Spryker\Service\Container\ContainerConfig;
use Spryker\Service\Container\ProxyFactory;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class ProxyPass implements CompilerPassInterface
{
    public function __construct(
        protected ContainerConfig $config = new ContainerConfig()
    ) {
    }

    public function process(ContainerBuilder $container): void
    {
        // Get a snapshot of definition IDs to iterate over, as we'll be adding new ones.
        $definitionIds = $container->getServiceIds();

        foreach ($definitionIds as $id) {
            // The definition might have been removed or replaced, so we check.
            if (!$container->hasDefinition($id)) {
                continue;
            }

            $definition = $container->getDefinition($id);

            // We only care about autowired services that have a class we can reflect on.
            if (!$definition->isAutowired() || !$definition->getClass() || $definition->isAbstract()) {
                continue;
            }

            // Use reflection to find constructor dependencies.
            try {
                // We need to be able to handle classes that might not exist yet.
                if (!class_exists($definition->getClass()) && !interface_exists($definition->getClass())) {
                    continue;
                }

                $reflectionClass = new ReflectionClass($definition->getClass());
            } catch (ReflectionException) {
                // Class doesn't exist, can't inspect it. AutowirePass will handle this error later if it's still an issue.
                continue;
            }

            $constructor = $reflectionClass->getConstructor();

            if (!$constructor) {
                continue;
            }

            foreach ($constructor->getParameters() as $parameter) {
                $type = $parameter->getType();

                if (!$type instanceof ReflectionNamedType) {
                    continue;
                }

                if ($type->getName() === 'array') {
                    continue;
                }

                if ($type->isBuiltin()) {
                    continue;
                }

                $dependencyId = $type->getName(); // e.g., 'Spryker\Zed\User\Business\UserFacadeInterface'

                // If the dependency is NOT in the container AND the class/interface does not exist (cannot be autoloaded),
                // then it's an external dependency we need to proxy.
                // Skip internal PHP classes
                if (!$container->has($dependencyId) && $this->requiresProxy($dependencyId)) {
                    // Create a definition for our proxy.
                    $proxyDefinition = new Definition();
                    $proxyDefinition->setClass($definition->getClass());
                    $proxyDefinition->setFactory([
                        new Reference(ProxyFactory::class), // Our internal ProxyFactory service
                        'createProxy',
                    ]);

                    $proxyDefinition->setArguments([$dependencyId]); // Pass the original ID to the factory

                    // Add the new proxy definition to the container. AutowirePass will now find this service.
                    $container->setDefinition($dependencyId, $proxyDefinition);
                }
            }
        }
    }

    protected function requiresProxy(string $dependencyId): bool
    {
        if (!class_exists($dependencyId) && !interface_exists($dependencyId)) {
            return true;
        }

        if (!$this->isCoreService($dependencyId) && !$this->isPropelService($dependencyId)) {
            return false;
        }

        // Check if a class is not an internal class like Throwable, etc.
        return (new ReflectionClass($dependencyId))->isUserDefined();
    }

    protected function isCoreService(string $dependencyId): bool
    {
        foreach ($this->config->getCoreNamespaces() as $coreNamespace) {
            if (str_starts_with($dependencyId, $coreNamespace)) {
                return true;
            }
        }

        return false;
    }

    protected function isPropelService(string $dependencyId): bool
    {
        return str_starts_with($dependencyId, 'Orm\\');
    }
}
