<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Service\Container;

class DedicatedService implements DedicatedServiceInterface
{
    protected $service;

    public function __construct(\Closure $service)
    {
        $this->nuts = $service;
    }

    public function getService(): \Closure
    {
        return $this->nuts;
    }
}
