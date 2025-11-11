<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Service\Container\Attributes;

use Attribute;
use Spryker\Service\Container\Exception\StackAttributeDefinitionException;

#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final class Stack
{
    public function __construct(
        /**
         * Used on a class __constructor method that needs a dependency passed in from a DependencyProvider.
         * Defines the DependencyProvider that must be used to resolve this.
         *
         * E.g. `Spryker\Zed\Customer\CustomerDependencyProvider`.
         *
         * The Container takes care of resolving the correct one.
         */
        public readonly ?string $dependencyProvider = null,
        /**
         * Used on a class __constructor method together with the `$dependencyProvider` argument that needs a dependency passed
         * in from a DependencyProvider e.g. `getPluginStack.
         *
         * With the `$dependencyProvider` argument, the Container tries to find the service `Spryker\Zed\Customer\CustomerDependencyProvider`
         * and calls the `$dependencyProviderMethod` on it.
         */
        public readonly ?string $dependencyProviderMethod = null,
        /**
         * Use in classes that want to provide themselves to another class.
         */
        public readonly ?string $provideToClass = null,
        /**
         * Use in classes that want to provide themselves to another class to tell to which of the arguments it wants to provide to.
         */
        public readonly ?string $provideToArgument = null,
        /**
         * Use in classes that want to provide themselves to another class to define the order of the arguments.
         */
        public readonly ?int $stackPosition = null,
        /**
         * Can be used to define the service name of the class that uses this attribute. Basically the same as
         * `\Symfony\Component\DependencyInjection\Attribute\AsAlias`.
         */
        public readonly ?string $service = null, // We may have multiple stacks defined in a class, and we need to safely define the correct stack definition into.
    ) {
        // Validate that when the `$dependencyProvider` argument is used, it MUST be used together with the `$dependencyProviderMethod` argument.
        if ($this->dependencyProvider !== null && $this->dependencyProviderMethod === null) {
            throw new StackAttributeDefinitionException(
                sprintf(
                    'You have defined the "%s" dependency provider to be used to resolve a stack of dependencies. You also need to define which method on the DependencyProvider must be used to resolve with the argument "$dependencyProviderMethod".',
                    $this->dependencyProvider,
                ),
            );
        }

        // Validate that when the `$dependencyProvider` argument is used, it MUST be used together with the `$provideToArgument`
        // argument to let the container know to which argument it must pass the resolved stack.
        if ($this->dependencyProvider !== null && $this->provideToArgument === null) {
            throw new StackAttributeDefinitionException(
                sprintf(
                    'You have defined the "%s::%s" dependency provider method to resolve a stack of dependencies. You also need to define which argument of the class __constructor the resolved dependency must be passed to with the argument "$provideToArgument".',
                    $this->dependencyProvider,
                    $this->dependencyProviderMethod,
                ),
            );
        }
    }
}
