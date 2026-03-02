<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace SprykerTest\Service\Container\Helper;

use Codeception\Module;

trait ContainerHelperTrait
{
    protected function getContainerHelper(): ContainerHelper
    {
        /** @var \SprykerTest\Service\Container\Helper\ContainerHelper $containerHelper */
        $containerHelper = $this->getModule('\\' . ContainerHelper::class);

        return $containerHelper;
    }

    abstract protected function getModule(string $name): Module;
}
