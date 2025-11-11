<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Service\Container\Builder;

use Generated\Shared\Transfer\ContainerBuilderRequestTransfer;
use Generated\Shared\Transfer\ContainerBuilderResponseTransfer;
use Spryker\Service\Container\Pass\BridgePass;
use Spryker\Service\Container\Pass\ProxyPass;
use Spryker\Service\Container\Pass\SprykerDefaultsPass;
use Spryker\Service\Container\Pass\StackResolverPass;
use Symfony\Component\Config\Builder\ConfigBuilderGenerator;
use Symfony\Component\Config\ConfigCache;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder as SymfonyContainerBuilder;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\Filesystem\Filesystem;

class ContainerBuilder
{
    public function buildContainer(ContainerBuilderRequestTransfer $containerBuilderRequestTransfer): ContainerBuilderResponseTransfer
    {
        $containerBuilderResponseTransfer = new ContainerBuilderResponseTransfer();
        $containerBuilderResponseTransfer->setIsSuccessful(true);

        // Namespace can be:
        // - `ProjectNamespace\\Service\\Container`
        // - `CoreNamespace\\ModuleName\\Service\\Container`
        $containerNamespace = sprintf(
            '%s\\%sService\\Container',
            $containerBuilderRequestTransfer->getNamespace(),
            $containerBuilderRequestTransfer->getModuleName() ? $containerBuilderRequestTransfer->getModuleName() . '\\' : '',
        );

        // Path to the Container can be:
        // - `cache/DependencyInjection/ProjectNamespaceServiceContainer`
        // - `vendor/core-namespace/module-name/var/cache/ModuleNameServiceContainer`
        $containerFile = sprintf(
            '%s/cache/DependencyInjection/%sServiceContainer.php',
            rtrim($containerBuilderRequestTransfer->getCwdOrFail(), '/'),
            $containerBuilderRequestTransfer->getModuleName() ?: $containerBuilderRequestTransfer->getNamespace(),
        );

        // Container class can be:
        // - `ProjectNamespaceServiceContainer`
        // - `ModuleNameServiceContainer`
        $containerClass = sprintf('%sServiceContainer', $containerBuilderRequestTransfer->getModuleName() ?: $containerBuilderRequestTransfer->getNamespace());

        $cache = new ConfigCache($containerFile, str_ends_with(APPLICATION_ENV, '.dev'));

        if ($cache->isFresh()) {
            return $containerBuilderResponseTransfer;
        }

        $containerBuilder = new SymfonyContainerBuilder();
        $containerBuilder
            // We need to pass the namespace aka organisation into the Pass to be able to skip using specific container in specific cases.
            // Check the description and implementation in the Pass itself
            ->addCompilerPass(new ProxyPass())
            ->addCompilerPass(new SprykerDefaultsPass())
            ->addCompilerPass(new BridgePass())
            ->addCompilerPass(new StackResolverPass());

        // Load the service configuration
        $loader = new PhpFileLoader(
            $containerBuilder,
            new FileLocator(
                $containerBuilderRequestTransfer->getCwd() . '/config',
            ),
            null,
            new ConfigBuilderGenerator($containerBuilderRequestTransfer->getCwdOrFail()),
        );

        // Defaults to services.php but can be anything else.
        $loader->load($containerBuilderRequestTransfer->getConfigFile());

        // Compile the container
        $containerBuilder->compile();

        // Dump the compiled container to a PHP file
        $dumper = new PhpDumper($containerBuilder);

        $content = $dumper->dump([
            'namespace' => $containerNamespace,
            'class' => $containerClass,
            'file' => $cache->getPath(),
            'as_files' => $containerBuilderRequestTransfer->getCache() ?? true,
        ]);

        $filesystem = new Filesystem();

        if ($containerBuilderRequestTransfer->getCache() === false && is_string($content)) {
            $filesystem->dumpFile($containerFile, $content);

            return $containerBuilderResponseTransfer;
        }

        /** @phpstan-var array $content */
        $rootCode = array_pop($content);
        $directory = dirname($cache->getPath()) . '/';

        foreach ($content as $file => $code) {
            $filesystem->dumpFile($directory . $file, $code);
            chmod($directory . $file, 0666 & ~umask());
        }

        $cache->write($rootCode, $containerBuilder->getResources());

        return $containerBuilderResponseTransfer;
    }
}
