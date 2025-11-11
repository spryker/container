<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace SprykerTest\Service\Container;

use Codeception\Test\Unit;
use ReflectionClass;
use Spryker\Service\Container\ContainerDelegator;
use Spryker\Service\Container\ContainerTrait;
use Spryker\Shared\Kernel\Container\ContainerProxy;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group Service
 * @group Container
 * @group ContainerTraitServiceTest
 * Add your own group annotations below this line
 */
class ContainerTraitServiceTest extends Unit
{
    use ContainerTrait;

    protected function _before()
    {
        parent::_before();

        $reflection = new ReflectionClass(ContainerDelegator::class);
        $instanceProperty = $reflection->getProperty('instance');

        $instanceProperty->setAccessible(true);
        $instanceProperty->setValue(null, null);
    }

    public function testGivenTheContainerDoesNotHasAServiceWhenHasServiceIsCalledThenFalseIsReturned(): void
    {
        $this->assertFalse($this->hasService('not existing service'));
    }

    public function testGivenTheContainerHasAServiceWhenHasServiceIsCalledThenTrueIsReturned(): void
    {
        ContainerDelegator::getInstance()->set(static::class, $this);

        $this->assertTrue($this->hasService(static::class));
    }

    public function testGivenTheContainerDoesNotHasAServiceWhenFindServiceIsCalledThenNullIsReturned(): void
    {
        $this->assertNull($this->findService('not existing service'));
    }

    public function testGivenTheContainerHasAServiceWhenFindServiceIsCalledThenTheServiceIsReturned(): void
    {
        ContainerDelegator::getInstance()->set(static::class, $this);

        $this->assertInstanceOf(static::class, $this->findService(static::class));
    }

    public function testGivenTheContainerDoesNotHasAServiceWhenGetServiceIsCalledAndTheServiceIsNotAClassLikeStringThenNullIsReturned(): void
    {
        ContainerDelegator::getInstance()->attachContainer('application_container', new ContainerProxy());
        ContainerDelegator::getInstance()->attachContainer('project_container', new ContainerProxy());

        $this->assertNull($this->getService('not existing service'));
    }

    public function testGivenTheContainerDoesNotHasAServiceWhenGetServiceIsCalledAndTheServiceIsAClassLikeStringThenNullIsReturned(): void
    {
        ContainerDelegator::getInstance()->attachContainer('application_container', new ContainerProxy());
        ContainerDelegator::getInstance()->attachContainer('project_container', new ContainerProxy());

        $this->assertNull($this->getService(static::class));
    }

    public function testGivenTheContainerHasAServiceWhenGetServiceIsCalledThenTheServiceIsReturned(): void
    {
        ContainerDelegator::getInstance()->set(static::class, $this);

        $this->assertInstanceOf(static::class, $this->getService(static::class));
    }
}
