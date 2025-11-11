<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Container\Business;

use Generated\Shared\Transfer\ContainerBuilderRequestTransfer;
use Generated\Shared\Transfer\ContainerBuilderResponseTransfer;
use Spryker\Zed\Kernel\Business\AbstractFacade;

/**
 * @method \Spryker\Zed\Container\Business\ContainerBusinessFactory getFactory()
 */
class ContainerFacade extends AbstractFacade implements ContainerFacadeInterface
{
    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param \Generated\Shared\Transfer\ContainerBuilderRequestTransfer $containerBuilderRequestTransfer
     *
     * @return \Generated\Shared\Transfer\ContainerBuilderResponseTransfer
     */
    public function buildContainer(
        ContainerBuilderRequestTransfer $containerBuilderRequestTransfer
    ): ContainerBuilderResponseTransfer {
        return $this->getFactory()
            ->createContainerBuilder()
            ->buildContainer($containerBuilderRequestTransfer);
    }
}
