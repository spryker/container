<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\Service\Container\Fixture;

/**
 * A resolved class whose constructor type-hints an id the pass registers as an ALIAS, which is what
 * makes {@see \Spryker\Service\Container\Pass\SprykerDefaultsPass::markDependenciesAsLazy()} look
 * that id up.
 */
class AliasDependentFixtureFacade implements AliasDependentFixtureFacadeInterface
{
    public function __construct(protected ProjectFixtureFacadeInterface $projectFixtureFacade)
    {
    }
}
