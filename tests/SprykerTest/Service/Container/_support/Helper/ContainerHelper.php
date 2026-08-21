<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace SprykerTest\Service\Container\Helper;

use Codeception\Module;
use Codeception\TestInterface;
use ReflectionProperty;
use Spryker\Service\Container\Container;
use Spryker\Service\Container\ContainerDelegator;
use Spryker\Service\Container\ContainerInterface;
use Spryker\Shared\Kernel\Container\ContainerProxy;
use SprykerTest\Shared\Testify\Helper\ModuleHelperConfigTrait;

class ContainerHelper extends Module
{
    use ModuleHelperConfigTrait;

    /**
     * @var string
     */
    protected const CONFIG_KEY_DEBUG = 'debug';

    /**
     * @var \Spryker\Service\Container\ContainerInterface|null
     */
    protected $container;

    public function getContainer(): ContainerInterface
    {
        if ($this->container === null) {
            $this->container = new ContainerProxy([
                'logger' => null,
                'debug' => $this->config[static::CONFIG_KEY_DEBUG],
                'charset' => 'UTF-8',
            ]);
        }

        return $this->container;
    }

    public function _after(TestInterface $test): void
    {
        if ($this->container !== null) {
            $this->resetStaticProperties();
            $this->resetContainerDelegator();
            $this->container = null;
        }
    }

    protected function setDefaultConfig(): void
    {
        $this->config = [
            static::CONFIG_KEY_DEBUG => false,
        ];
    }

    protected function resetStaticProperties(): void
    {
        $staticProperties = [
            'aliases',
            'globalServices',
            'globalServiceIdentifier',
            'globalFrozenServices',
        ];

        foreach ($staticProperties as $staticProperty) {
            $reflectedProperty = new ReflectionProperty(Container::class, $staticProperty);
            $reflectedProperty->setValue([]);
        }
    }

    protected function resetContainerDelegator(): void
    {
        if (!interface_exists('\Symfony\Component\DependencyInjection\ContainerInterface', false)) {
            return;
        }

        $reflectedProperty = new ReflectionProperty(ContainerDelegator::class, 'instance');
        $reflectedProperty->setValue(null);
    }
}
