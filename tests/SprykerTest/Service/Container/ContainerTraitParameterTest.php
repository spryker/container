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
 * @group ContainerTraitParameterTest
 * Add your own group annotations below this line
 */
class ContainerTraitParameterTest extends Unit
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

    public function testGivenTheContainerDoesNotHasAParameterWhenHasParameterIsCalledThenFalseIsReturned(): void
    {
        $this->assertFalse($this->hasParameter('not existing parameter'));
    }

    public function testGivenTheContainerHasAParameterWhenHasParameterIsCalledThenTrueIsReturned(): void
    {
        ContainerDelegator::getInstance()->setParameter(static::class, '$this');

        $this->assertTrue($this->hasParameter(static::class));
    }

    public function testGivenTheContainerDoesNotHasAParameterWhenFindParameterIsCalledThenNullIsReturned(): void
    {
        $this->assertNull($this->findParameter('not existing parameter'));
    }

    public function testGivenTheContainerHasAParameterWhenFindParameterIsCalledThenTheParameterIsReturned(): void
    {
        ContainerDelegator::getInstance()->setParameter(static::class, '$this');

        $this->assertSame('$this', $this->findParameter(static::class));
    }

    public function testGivenTheContainerDoesNotHasAParameterWhenGetParameterIsCalledThenNullIsReturned(): void
    {
        ContainerDelegator::getInstance()->attachContainer('application_container', new ContainerProxy());
        ContainerDelegator::getInstance()->attachContainer('project_container', new ContainerProxy());

        $this->assertNull($this->getParameter('not existing parameter'));
    }

    public function testGivenTheContainerHasAParameterWhenGetParameterIsCalledThenTheParameterIsReturned(): void
    {
        ContainerDelegator::getInstance()->setParameter(static::class, '$this');

        $this->assertSame('$this', $this->getParameter(static::class));
    }
}
