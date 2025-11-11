<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Container\Business;

use Generated\Shared\Transfer\ContainerBuilderRequestTransfer;
use Generated\Shared\Transfer\ContainerBuilderResponseTransfer;

interface ContainerFacadeInterface
{
    /**
     * Specification:
     * - Builds and compiles the dependency injection container.
     * - Uses the namespace, moduleName, and cwd from the request transfer.
     * - Returns a response transfer with success status and potential errors.
     *
     * @api
     */
    public function buildContainer(
        ContainerBuilderRequestTransfer $containerBuilderRequestTransfer
    ): ContainerBuilderResponseTransfer;
}
