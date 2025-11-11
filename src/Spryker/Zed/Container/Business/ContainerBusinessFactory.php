<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Container\Business;

use Spryker\Service\Container\Builder\ContainerBuilder;
use Spryker\Zed\Kernel\Business\AbstractBusinessFactory;

class ContainerBusinessFactory extends AbstractBusinessFactory
{
    public function createContainerBuilder(): ContainerBuilder
    {
        return new ContainerBuilder();
    }
}
