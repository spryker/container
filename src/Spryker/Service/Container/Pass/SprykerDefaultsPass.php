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
use Spryker\Zed\ModuleFinder\Business\ModuleFinderFacade;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class SprykerDefaultsPass implements CompilerPassInterface
{
    /**
     * @var array<string>
     */
    private array $coreNamespaces = [];

    public function __construct(protected ContainerConfig $config = new ContainerConfig())
    {
    }

    /**
     * This Pass makes all "default" classes like Facade, Client, and Service visible to the container builder to prevent
     * creating too many proxies.
     */
    public function process(ContainerBuilder $container): void
    {
        $moduleFinderFacade = new ModuleFinderFacade();

        foreach ($moduleFinderFacade->getModules() as $moduleTransfer) {
            $moduleName = $moduleTransfer->getName();
            $organisationName = $moduleTransfer->getOrganization()?->getName();

            // Make PHPStan happy, as this case can't happen anyway.
            if (!$organisationName) {
                continue;
            }

            $this->registerDefaultService($container, sprintf('%s\Zed\%s\Business\%sFacadeInterface', $organisationName, $moduleName, $moduleName));
            $this->registerDefaultService($container, sprintf('%s\Zed\%s\%sConfig', $organisationName, $moduleName, $moduleName));

            $this->registerDefaultService($container, sprintf('%s\Glue\%s\%sConfig', $organisationName, $moduleName, $moduleName));

            $this->registerDefaultService($container, sprintf('%s\Client\%s\%sClientInterface', $organisationName, $moduleName, $moduleName));
            $this->registerDefaultService($container, sprintf('%s\Client\%s\%sConfig', $organisationName, $moduleName, $moduleName));

            $this->registerDefaultService($container, sprintf('%s\Service\%s\%sServiceInterface', $organisationName, $moduleName, $moduleName));
        }
    }

    protected function registerDefaultService(ContainerBuilder $container, string $interfaceOrClassName): void
    {
        if (interface_exists($interfaceOrClassName)) {
            $this->registerDefaultServiceByInterface($container, $interfaceOrClassName);

            return;
        }

        if (class_exists($interfaceOrClassName)) {
            $this->registerDefaultServiceByClass($container, $interfaceOrClassName);
        }
    }

    protected function registerDefaultServiceByInterface(ContainerBuilder $container, string $interfaceName): void
    {
        $resolvedClass = $this->findResolvableClassForInterface($interfaceName);

        if ($resolvedClass) {
            $definition = new Definition($resolvedClass);
            $definition->setPublic(true);

            $this->markDependenciesAsLazy($container, $resolvedClass);

            $optionalInterfaceName = $resolvedClass . 'Interface';

            if (interface_exists($interfaceName)) {
                $interfaceName = $optionalInterfaceName;
            }

            $container->setDefinition($interfaceName, $definition);

            return;
        }

        /**
         * This SHOULD never happen to be executed and needs refactoring of the \Spryker\Service\Container\Pass\SprykerDefaultsPass::findConcreteClassForInterface()
         * method to be much more explicit on finding
         */
        $proxyDefinition = new Definition();
        $proxyDefinition->setClass($interfaceName);
        $proxyDefinition->setFactory([
            new Reference(ProxyFactory::class),
            'createProxy',
        ]);
        $proxyDefinition->setArguments([$interfaceName]);
        $proxyDefinition->setPublic(true);

        $container->setDefinition($interfaceName, $proxyDefinition);
    }

    protected function registerDefaultServiceByClass(ContainerBuilder $container, string $className): void
    {
        $definition = new Definition($className);
        $definition->setPublic(true);

        $this->markDependenciesAsLazy($container, $className);

        $container->setDefinition($className, $definition);
    }

    /**
     * @param string $interfaceName
     *
     * @return string|null
     */
    protected function findResolvableClassForInterface(string $interfaceName): ?string
    {
        $interfaceNameFragments = explode('\\', $interfaceName);

        if (!$this->isCoreNamespace($interfaceNameFragments[0])) {
            return null;
        }

        $className = str_replace('Interface', '', $interfaceName);
        $classNameCandidates = $this->getClassNameCandidates($interfaceNameFragments[0], $className);

        foreach ($classNameCandidates as $classNameCandidate) {
            if (!class_exists($classNameCandidate)) {
                continue;
            }

            return $classNameCandidate;
        }

        return null;
    }

    protected function isCoreNamespace(string $namespace): bool
    {
        return isset($this->getCoreNamespaces()[$namespace]);
    }

    protected function getCoreNamespaces(): array
    {
        if (!count($this->coreNamespaces)) {
            $this->coreNamespaces = array_flip($this->config->getCoreNamespaces());
        }

        return $this->coreNamespaces;
    }

    protected function getClassNameCandidates(string $organisation, string $className): array
    {
        $classNameCandidates = [];

        foreach ($this->config->getProjectNamespaces() as $namespace) {
            $classNameCandidates[] = str_replace($organisation . '\\', $namespace . '\\', $className);
        }

        $classNameCandidates[] = $className;

        return $classNameCandidates;
    }

    /**
     * @param \Symfony\Component\DependencyInjection\ContainerBuilder $container
     * @param string $concreteClass
     *
     * @return void
     */
    protected function markDependenciesAsLazy(ContainerBuilder $container, string $concreteClass): void
    {
        if (!class_exists($concreteClass)) {
            return;
        }

        try {
            $reflectionClass = new ReflectionClass($concreteClass);
            $constructor = $reflectionClass->getConstructor();

            if (!$constructor) {
                return;
            }

            foreach ($constructor->getParameters() as $parameter) {
                $type = $parameter->getType();

                if ($type && !$type->isBuiltin() && $type instanceof ReflectionNamedType) {
                    $dependencyClass = $type->getName();

                    if ($container->has($dependencyClass)) {
                        $dependencyDefinition = $container->getDefinition($dependencyClass);

                        if ($dependencyDefinition->getFactory()) {
                            continue;
                        }

                        $dependencyDefinition->setLazy(true);
                    }
                }
            }
        } catch (ReflectionException) {
            // Could happen for classes that are not autoloadable, but should not happen here.
            // We can ignore this.
        }
    }
}
