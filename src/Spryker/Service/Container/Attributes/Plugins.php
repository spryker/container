<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Service\Container\Attributes;

use Attribute;

/**
 * Resolves a plugin stack from the module's DependencyProvider and injects it into the constructor parameter.
 *
 * The DependencyProvider is auto-discovered from the class namespace following the convention
 * `{Organization}\{Layer}\{Module}\{Module}DependencyProvider`.
 *
 * Usage:
 * ```php
 * public function __construct(
 *     #[Plugins(dependencyProviderMethod: 'getItemExpanderPlugins')]
 *     protected array $itemExpanderPlugins = [],
 * ) {
 * }
 * ```
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final class Plugins
{
    public function __construct(
        public readonly string $dependencyProviderMethod,
    ) {
    }
}
