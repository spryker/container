<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\Service\Container\Fixture;

/**
 * Stands in for a PROJECT facade override. Named so that `::class . 'Interface'` resolves to
 * {@see \SprykerTest\Service\Container\Fixture\ProjectFixtureFacadeInterface}, which is the naming
 * convention the pass relies on to find a project interface.
 */
class ProjectFixtureFacade implements ProjectFixtureFacadeInterface
{
}
