<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\Service\Container\Fixture;

/**
 * A resolved class with NO matching `...Interface` sibling, so the pass must register the requested
 * id and add no alias.
 */
class InterfacelessFixtureFacade implements FixtureFacadeInterface
{
}
