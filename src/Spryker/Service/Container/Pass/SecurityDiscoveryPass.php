<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Service\Container\Pass;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionNamedType;
use Spryker\Service\ModuleFinder\ModuleFinderService;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Discovers and registers Security classes from Glue module directories.
 *
 * Scans each module for a top-level `Security/` directory under the Glue layer:
 * `{Org}\Glue\{Module}\Security\`
 *
 * Classes under `Api/{Backend|Storefront}/Security/` are NOT scanned here because
 * those are already auto-discovered by ApiClassAutoDiscoveryPass in the ApiPlatform bundle.
 *
 * Registers all instantiable classes found and creates interface-to-implementation
 * aliases for interfaces within the same module namespace.
 */
class SecurityDiscoveryPass implements CompilerPassInterface
{
    protected const string LAYER_GLUE = 'Glue';

    protected const string DIRECTORY_SECURITY = 'Security';

    protected const string FILE_EXTENSION_PHP = 'php';

    protected const string TAG_SECURITY_VOTER = 'security.voter';

    public function process(ContainerBuilder $container): void
    {
        $moduleFinderService = new ModuleFinderService();

        foreach ($moduleFinderService->getModules() as $moduleTransfer) {
            $moduleName = $moduleTransfer->getName();
            $organisationName = $moduleTransfer->getOrganization()?->getName();

            if (!$organisationName) {
                continue;
            }

            $glueBasePath = sprintf(
                '%ssrc/%s/%s/%s',
                $moduleTransfer->getPath(),
                $organisationName,
                static::LAYER_GLUE,
                $moduleName,
            );

            $securityDirectory = $glueBasePath . '/' . static::DIRECTORY_SECURITY;

            if (!is_dir($securityDirectory)) {
                continue;
            }

            $moduleNamespace = sprintf(
                '%s\\%s\\%s',
                $organisationName,
                static::LAYER_GLUE,
                $moduleName,
            );

            $this->registerClassesFromDirectory(
                $container,
                $securityDirectory,
                $moduleNamespace,
                $glueBasePath,
            );
        }
    }

    protected function registerClassesFromDirectory(
        ContainerBuilder $container,
        string $directory,
        string $moduleNamespace,
        string $glueBasePath,
    ): void {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory),
        );

        foreach ($iterator as $file) {
            if ($file->isDir() || $file->getExtension() !== static::FILE_EXTENSION_PHP) {
                continue;
            }

            $className = $this->resolveClassName($file->getPathname(), $moduleNamespace, $glueBasePath);

            if ($className === null) {
                continue;
            }

            if ($container->has($className)) {
                continue;
            }

            if (!$this->canBeAutowired($className, $container)) {
                continue;
            }

            $definition = new Definition($className);
            $definition->setAutowired(true);
            $definition->setAutoconfigured(true);
            $definition->setPublic(true);

            $this->applySecurityTags($definition, $className);

            $container->setDefinition($className, $definition);

            $this->registerInterfaceAliases($container, $className, $moduleNamespace);
        }
    }

    // Voter subclasses need an explicit security.voter tag because autoconfiguration
    // rules are applied before this compiler pass runs.

    protected function applySecurityTags(Definition $definition, string $className): void
    {
        if (is_subclass_of($className, Voter::class)) {
            $definition->addTag(static::TAG_SECURITY_VOTER);
        }
    }

    // Creates aliases from interfaces to their implementations when the interface
    // belongs to the same module namespace and has exactly one registered implementation.

    /**
     * @param class-string $className
     */
    protected function registerInterfaceAliases(
        ContainerBuilder $container,
        string $className,
        string $moduleNamespace,
    ): void {
        $reflectionClass = new ReflectionClass($className);

        foreach ($reflectionClass->getInterfaces() as $interface) {
            $interfaceName = $interface->getName();

            if (!str_starts_with($interfaceName, $moduleNamespace)) {
                continue;
            }

            if ($container->has($interfaceName)) {
                continue;
            }

            $container->setAlias($interfaceName, $className)->setPublic(true);
        }
    }

    /**
     * @return class-string|null
     */
    protected function resolveClassName(
        string $filePath,
        string $moduleNamespace,
        string $glueBasePath,
    ): ?string {
        // Convert file path to namespace by computing relative path from the Glue base
        $relativePath = str_replace($glueBasePath, '', $filePath);
        $relativePath = str_replace('/', '\\', $relativePath);
        $relativePath = str_replace('.' . static::FILE_EXTENSION_PHP, '', $relativePath);

        $className = $moduleNamespace . $relativePath;

        if (!class_exists($className) && !interface_exists($className)) {
            return null;
        }

        return $className;
    }

    // Checks if a class can be safely autowired: must be instantiable and all
    // constructor dependencies must either exist in the container or have default values.
    // Skips classes with iterable/array params that need explicit tagged_iterator config,
    // and classes whose dependencies aren't resolvable.
    /**
     * @param class-string $className
     */
    protected function canBeAutowired(string $className, ContainerBuilder $container): bool
    {
        $reflectionClass = new ReflectionClass($className);

        if (!$reflectionClass->isInstantiable()) {
            return false;
        }

        $constructor = $reflectionClass->getConstructor();

        if ($constructor === null) {
            return true;
        }

        foreach ($constructor->getParameters() as $parameter) {
            if ($parameter->isDefaultValueAvailable()) {
                continue;
            }

            $type = $parameter->getType();

            if (!$type instanceof ReflectionNamedType) {
                return false;
            }

            if ($type->isBuiltin()) {
                return false;
            }

            $dependencyClass = $type->getName();

            if (!$container->has($dependencyClass)) {
                return false;
            }
        }

        return true;
    }
}
