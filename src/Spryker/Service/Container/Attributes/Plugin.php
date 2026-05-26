<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Service\Container\Attributes;

use Attribute;

/**
 * Resolves a single plugin instance from the module's DependencyProvider and injects it into the constructor parameter.
 *
 * The DependencyProvider is auto-discovered from the class namespace following the convention
 * `{Organization}\{Layer}\{Module}\{Module}DependencyProvider`. The method named by `dependencyProviderMethod`
 * is expected to return a single plugin object — not an array. For plugin stacks use {@link Plugins} instead.
 *
 * Usage:
 * ```php
 * public function __construct(
 *     #[Plugin(dependencyProviderMethod: 'getCompanyUserProviderPlugin')]
 *     protected CompanyUserProviderPluginInterface $companyUserProviderPlugin,
 * ) {
 * }
 * ```
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final class Plugin
{
    public function __construct(
        public readonly string $dependencyProviderMethod,
    ) {
    }
}
