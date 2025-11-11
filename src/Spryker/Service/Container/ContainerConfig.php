<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Service\Container;

use Spryker\Service\Kernel\AbstractBundleConfig;
use Spryker\Shared\Kernel\KernelConstants;

class ContainerConfig extends AbstractBundleConfig
{
    /**
     * @api
     *
     * @return array<string>
     */
    public function getCoreNamespaces(): array
    {
        return $this->get(KernelConstants::CORE_NAMESPACES);
    }

    /**
     * @api
     *
     * @return array<string>
     */
    public function getProjectNamespaces(): array
    {
        return $this->get(KernelConstants::PROJECT_NAMESPACES);
    }
}
