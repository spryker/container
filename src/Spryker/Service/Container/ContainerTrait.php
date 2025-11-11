<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types = 1);

namespace Spryker\Service\Container;

use Spryker\Zed\Kernel\Business\AbstractFacade;
use UnitEnum;

trait ContainerTrait
{
    /**
     * @template C of object
     *
     * @param class-string<C>|string $serviceId
     *
     * @return bool
     */
    public function hasService(string $serviceId): bool
    {
        return ContainerDelegator::getInstance()->has($serviceId);
    }

    /**
     * @template C of object
     *
     * @param class-string<C>|string $serviceId
     *
     * @return C|null
     */
    public function getService(string $serviceId, ?string $factoryMethodName = null): ?object
    {
        $container = ContainerDelegator::getInstance();

        if ($container->has($serviceId)) {
            return $container->get($serviceId);
        }

        if ($this instanceof AbstractFacade && $factoryMethodName !== null) {
            return $this->getFactory()->$factoryMethodName();
        }

        return null;
    }

    /**
     * This method should be used where it is expected to possibly get null or where a simple get would throw an exception
     * on services which may or may not be there.
     *
     * @template C of object
     *
     * @param class-string<C>|string $serviceId
     *
     * @return ($serviceId is class-string<C> C|object|null
     */
    public function findService(string $serviceId): ?object
    {
        $container = ContainerDelegator::getInstance();

        if ($container->has($serviceId)) {
            return $container->get($serviceId);
        }

        return null;
    }

    public function hasParameter(string $parameterName): bool
    {
        return ContainerDelegator::getInstance()->hasParameter($parameterName);
    }

    public function getParameter(string $parameterName): array|bool|string|int|float|UnitEnum|null
    {
        return ContainerDelegator::getInstance()->getParameter($parameterName);
    }

    public function findParameter(string $parameterName): array|bool|string|int|float|UnitEnum|null
    {
        $container = ContainerDelegator::getInstance();

        if ($container->hasParameter($parameterName)) {
            return $container->getParameter($parameterName);
        }

        return null;
    }
}
