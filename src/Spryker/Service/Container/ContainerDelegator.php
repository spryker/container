<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Service\Container;

use Closure;
use Psr\Container\ContainerInterface as PsrContainerInterface;
use Spryker\Service\Container\ContainerInterface as SprykerContainerInterface;
use Spryker\Shared\Kernel\Container\ContainerProxy;
use Symfony\Component\DependencyInjection\ContainerInterface as SymfonyContainerInterface;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use UnitEnum;

class ContainerDelegator implements SymfonyContainerInterface
{
    protected static ?self $instance = null;

    /**
     * @var array<string, \Psr\Container\ContainerInterface|\Symfony\Component\DependencyInjection\ContainerInterface|\Spryker\Service\Container\ContainerInterface|\Spryker\Shared\Kernel\Container\ContainerProxy>
     */
    protected array $containers = [];

    /**
     * @var array<string, mixed>
     */
    protected array $parameters = [];

    /**
     * @var array<string, object|mixed>
     */
    protected array $services = [];

    /**
     * @var array<string, object|mixed>
     */
    protected array $resolvedServices = [];

    /**
     * @var array<string>
     */
    protected array $checkedContainer = [];

    /**
     * Gets the single instance of the delegator.
     */
    public static function getInstance(): self
    {
        if (static::$instance === null) {
            static::$instance = new self();
        }

        return static::$instance;
    }

    /**
     * The constructor is private to prevent direct instantiation.
     */
    private function __construct(protected ContainerConfig $config = new ContainerConfig())
    {
    }

    /**
     * Prevent cloning of the instance.
     */
    private function __clone()
    {
    }

    public function attachContainer(
        string $identifier,
        PsrContainerInterface|SymfonyContainerInterface|SprykerContainerInterface|ContainerProxy $container
    ): void {
        $this->containers[$identifier] = $container;
    }

    /**
     * @template C of object
     * @template B of self::*_REFERENCE
     *
     * @see Reference
     *
     * @param class-string<C>|string $id
     * @param B|int $invalidBehavior
     *
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException When the service is not defined
     *
     * @return ($id is class-string<C> ? (B is (0 | 1) ? (C | object) : (C | object | null)) : (B is (0 | 1) ? object : (object | null)))|null
     */
    public function get(string $id, int $invalidBehavior = self::EXCEPTION_ON_INVALID_REFERENCE): ?object
    {
        // Ensure the id is always without the left side `\`. We may have cases like from the ControllerResolver returning those with the `\` prefix.
        $id = ltrim($id, '\\');

        // Reset the checked containers variable to have it clean for each individual request service
        $this->checkedContainer = [];

        // Used in the exception message at the end.
        $originalId = $id;

        if (isset($this->resolvedServices[$id])) {
            return $this->resolvedServices[$id];
        }

        if (isset($this->services[$id])) {
            return $this->resolvedServices[$id] = $this->services[$id];
        }

        // In case we do not have any other containers attached, we have to return null here to not break.
        if (count($this->containers) === 0) {
            return null;
        }

        // The expectation here is that string-like services are "application services" attached through the application plugins.
        // For these services we will first try the "project_container".
        if (str_contains($id, '\\') === false) {
            if (isset($this->containers['project_container'])) {
                $projectContainer = $this->containers['project_container'];

                if ($projectContainer->has($id)) {
                    return $this->resolvedServices[$id] = $projectContainer->get($id);
                }

                $this->persistCheckedContainersForExceptionHandling($id, $projectContainer::class);
            }

            if (isset($this->containers['application_container'])) {
                /** @var \Spryker\Shared\Kernel\Container\ContainerProxy $applicationContainer */
                $applicationContainer = $this->containers['application_container'];

                if ($applicationContainer->has($id)) {
                    return $this->resolvedServices[$id] = $applicationContainer->get($id);
                }

                $this->persistCheckedContainersForExceptionHandling($id, ContainerProxy::class);
            }

            // Return null as all other containers have only class like services.
            return null;
        }

        [$namespace, $application, $module, $containerIdentifier] = $this->getPartsFromId($id);

        /**
         * If the request is for a core service, first check for a project override to ensure Bucket -> Project -> Core resolving.
         */
        if ($this->isCoreNamespace($namespace) || $this->isProjectNamespace($namespace)) {
            $object = $this->findInProjectContainer($id);

            if ($object) {
                return $object;
            }
        }

        // The service was not found so far, let's autoload the module container and check if it has the request service.
        $moduleContainerClassName = sprintf('%s\%2$s\Service\Container\%2$sServiceContainer', $namespace, $module);

        if (class_exists($moduleContainerClassName)) {
            /** @var \Symfony\Component\DependencyInjection\Container $moduleContainer */
            $moduleContainer = new $moduleContainerClassName();

            if ($moduleContainer->has($id)) {
                return $this->resolvedServices[$id] = $moduleContainer->get($id);
            }

            $this->persistCheckedContainersForExceptionHandling($id, $moduleContainerClassName);

            /**
             * During compilation of a container Symfony removes the `Interface` suffix for services it was able to find only once
             * to keep the compiled container smaller. By that the core container will not have the expected ExampleClassInterface
             * but the ExampleClass.
             */
            if (str_ends_with($id, 'Interface')) {
                $id = str_replace('Interface', '', $id);

                if ($moduleContainer->has($id)) {
                    return $this->resolvedServices[$id] = $moduleContainer->get($id);
                }

                $this->persistCheckedContainersForExceptionHandling($id, $moduleContainerClassName);

                /**
                 * Core classes only have a dependency to the ModuleToModule*Interface and not the bridge. If we got a request
                 * to the service id `ModuleToModuleFacadeInterface` (the originalId that was requested), then we tried first the
                 * example from above with the removed suffix `ModuleToModuleFacade`. Which can't be found as such a service never exists.
                 *
                 * We add the `Bridge` suffix, so we will have `ModuleToModuleFacadeBridge`, which may exist in the core container.
                 */
                $id .= 'Bridge';

                if ($moduleContainer->has($id)) {
                    return $this->resolvedServices[$id] = $moduleContainer->get($id);
                }

                $this->persistCheckedContainersForExceptionHandling($id, $moduleContainerClassName);

                // In some cases, we are using Adapter, so we have to check them as well.
                $id .= 'Adapter';

                if ($moduleContainer->has($id)) {
                    return $this->resolvedServices[$id] = $moduleContainer->get($id);
                }

                $this->persistCheckedContainersForExceptionHandling($id, $moduleContainerClassName);
            }
        }

        /**
         * When the service wasn't found in any container, we must provide an expressive exception message that contains all
         * checked keys and where they have been tried to be taken from. By that a developer can check all containers manually
         * and look up for the requested service id and may be able to spot the issue easier.
         */
        $exceptionMessage = sprintf('Could not find the "%s" in any of the attached containers. This could happen when you try to auto-wire a core service from a module that has no container. You can add your own class and extend the one from the core service but you need to have the dependencies of the core service also configured or available in your container.', $originalId) . PHP_EOL . PHP_EOL;

        foreach ($this->checkedContainer as $servicedId => $containerKey) {
            $exceptionMessage .= sprintf('Checked "%s" in container "%s".', $servicedId, $containerKey) . PHP_EOL;
        }

        throw new ServiceNotFoundException($id, null, null, [], $exceptionMessage);
    }

    protected function findInProjectContainer(string $id): ?object
    {
        if (!isset($this->containers['project_container'])) {
            return null;
        }

        foreach ($this->config->getProjectNamespaces() as $projectNamespace) {
            $projectServiceId = $this->getProjectServiceId($id, $projectNamespace);

            /** @var \Symfony\Component\DependencyInjection\Container $projectContainer */
            $projectContainer = $this->containers['project_container'];
            $projectContainerClassName = $projectContainer::class;

            /**
             * We need to first check if we have a CodeBucket overriding for this particular service
             */
            $codeBucketServiceId = $this->getCodeBucketServiceId($projectServiceId);

            if ($projectContainer->has($codeBucketServiceId)) {
                return $this->resolvedServices[$id] = $projectContainer->get($codeBucketServiceId);
            }

            $this->persistCheckedContainersForExceptionHandling($codeBucketServiceId, $projectContainerClassName);

            if ($projectContainer->has($projectServiceId)) {
                return $this->resolvedServices[$id] = $projectContainer->get($projectServiceId);
            }

            $this->persistCheckedContainersForExceptionHandling($projectServiceId, $projectContainerClassName);

            // Spryker default classes (Facade, Client, Service) are registered in the project container by default.
            // Only when a project is fully moved to DI then the dependency can be found in the core container.
            // Due to this we have to ask for the original key as well in the project container.
            if ($projectContainer->has($id)) {
                return $this->resolvedServices[$id] = $projectContainer->get($id);
            }

            $this->persistCheckedContainersForExceptionHandling($id, $projectContainerClassName);
        }

        return null;
    }

    protected function getProjectServiceId(string $serviceId, string $projectOrganization): string
    {
        $parts = explode('\\', $serviceId);

        if (isset($parts[0])) {
            $parts[0] = $projectOrganization;

            return implode('\\', $parts);
        }

        return $serviceId;
    }

    protected function getCodeBucketServiceId(string $projectServiceId): string
    {
        $codeBucketName = APPLICATION_CODE_BUCKET;

        $parts = explode('\\', $projectServiceId);

        if (isset($parts[2])) {
            $parts[2] .= $codeBucketName;

            return implode('\\', $parts);
        }

        return $projectServiceId;
    }

    protected function persistCheckedContainersForExceptionHandling(string $serviceId, string $containerClassName): void
    {
        $this->checkedContainer[$serviceId] = $containerClassName;
    }

    public function set(string $id, ?object $service): void
    {
        // Runs the internal initializer; used by the dumped container to include always-needed files
        if (isset($this->privates['service_container']) && $this->privates['service_container'] instanceof Closure) {
            $initialize = $this->privates['service_container'];
            unset($this->privates['service_container']);
            $initialize($this);
        }

        if ($id === 'service_container') {
            throw new InvalidArgumentException('You cannot set service "service_container".');
        }

        if (isset($this->aliases[$id])) {
            unset($this->aliases[$id]);
        }

        if ($service === null) {
            unset($this->services[$id]);

            return;
        }

        $this->services[$id] = $service;
    }

    public function has(string $id): bool
    {
        if (isset($this->resolvedServices[$id])) {
            return true;
        }

        try {
            // Try to resolve the service, when it was resolved, return true. When it returns null, it means we tried a
            // non-class like service which may be there or not, and in this case it was not, so we return false.
            $res = $this->get($id);
            if (!$res) {
                return false;
            }

            return true;
        } catch (ServiceNotFoundException $e) {
            return false;
        }
    }

    /**
     * Check for whether a service has been initialized.
     */
    public function initialized(string $id): bool
    {
        return $this->resolvedServices[$id] ?? false;
    }

    public function getParameter(string $name): array|bool|string|int|float|\UnitEnum|null
    {
        if (isset($this->parameters[$name])) {
            return $this->parameters[$name];
        }

        $applicationContainer = $this->containers['application_container'];

        if (method_exists($applicationContainer, 'hasParameter') && $applicationContainer->hasParameter($name) && method_exists($applicationContainer, 'getParameter')) {
            return $applicationContainer->getParameter($name);
        }

        if ($applicationContainer->has($name)) {
            return $applicationContainer->get($name);
        }

        $projectContainer = $this->containers['project_container'];

        if (method_exists($projectContainer, 'hasParameter') && $projectContainer->hasParameter($name) && method_exists($projectContainer, 'getParameter')) {
            return $projectContainer->getParameter($name);
        }

        if ($projectContainer->has($name)) {
            return $projectContainer->get($name);
        }

        return null;
    }

    public function hasParameter(string $name): bool
    {
        if (isset($this->parameters[$name])) {
            return true;
        }

        if (count($this->containers) === 0) {
            return false;
        }

        $applicationContainer = $this->containers['application_container'];

        if (method_exists($applicationContainer, 'hasParameter')) {
            if ($applicationContainer->hasParameter($name)) {
                return true;
            }
        }

        if ($applicationContainer->has($name)) {
            return true;
        }

        $projectContainer = $this->containers['project_container'];

        if (method_exists($projectContainer, 'hasParameter')) {
            if ($projectContainer->hasParameter($name)) {
                return true;
            }
        }

        if ($projectContainer->has($name)) {
            return true;
        }

        return false;
    }

    public function setParameter(string $name, array|bool|string|int|float|UnitEnum|null $value): void
    {
        $this->parameters[$name] = $value;
    }

    /**
     * @return array{string, string, string, string}
     */
    protected function getPartsFromId(string $id): array
    {
        $parts = explode('\\', $id);

        return [$parts[0], $parts[1], $parts[2], $parts[1] . '.' . $parts[2]];
    }

    protected function isProjectNamespace(string $namespace): bool
    {
        $namespaces = array_flip($this->config->getProjectNamespaces());

        return isset($namespaces[$namespace]);
    }

    protected function isCoreNamespace(string $namespace): bool
    {
        $namespaces = array_flip($this->config->getCoreNamespaces());

        return isset($namespaces[$namespace]);
    }

    /**
     * Returns the IDs of services that have been removed during container compilation.
     *
     * This method is required by Symfony's ContainerDebugCommand to check if a service
     * was removed or inlined during compilation.
     *
     * Note: Only checks project_container (Symfony) as getRemovedIds() is Symfony-specific
     * for tracking removed services during container compilation. The application_container
     * (Spryker) does not implement this method.
     *
     * @return array<string, true>
     */
    public function getRemovedIds(): array
    {
        $removedIds = [];

        if (isset($this->containers['project_container'])) {
            $projectContainer = $this->containers['project_container'];

            if (method_exists($projectContainer, 'getRemovedIds')) {
                $removedIds = array_merge($removedIds, $projectContainer->getRemovedIds());
            }
        }

        return $removedIds;
    }

    /**
     * This method is required by Symfony's cache warmers and other components.
     *
     * Note: Only checks project_container (Symfony) as ParameterBagInterface is Symfony-specific.
     * The application_container (Spryker) does not have a parameter bag.
     * Returns an empty ParameterBag when project_container is not available to maintain
     * compatibility with Symfony's ContainerInterface contract (non-nullable return type).
     */
    public function getParameterBag(): ParameterBagInterface
    {
        if (isset($this->containers['project_container'])) {
            $projectContainer = $this->containers['project_container'];

            if (method_exists($projectContainer, 'getParameterBag')) {
                return $projectContainer->getParameterBag();
            }
        }

        return new ParameterBag();
    }
}
