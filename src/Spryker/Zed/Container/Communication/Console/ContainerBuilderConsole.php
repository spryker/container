<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Container\Communication\Console;

use Generated\Shared\Transfer\ContainerBuilderRequestTransfer;
use Spryker\Zed\Kernel\Communication\Console\Console;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @method \Spryker\Zed\Container\Business\ContainerFacadeInterface getFacade()
 */
class ContainerBuilderConsole extends Console
{
    /**
     * @var string
     */
    protected const ARGUMENT_NAMESPACE = 'namespace';

    /**
     * @var string
     */
    protected const OPTION_MODULE_NAME = 'module-name';

    /**
     * @var string
     */
    protected const OPTION_CWD = 'cwd';

    /**
     * @var string
     */
    protected const OPTION_CONFIG_FILE = 'config-file';

    /**
     * @var string
     */
    protected const OPTION_NO_CACHE = 'no-cache';

    /**
     * @return void
     */
    protected function configure(): void
    {
        $this->setName('container:build')
            ->setDescription('Build and compile the dependency injection container')
            ->addArgument(
                static::ARGUMENT_NAMESPACE,
                InputArgument::OPTIONAL,
                'The namespace for the container (e.g., project (default) or Spryker)',
                'project',
            )
            ->addOption(
                static::OPTION_MODULE_NAME,
                'm',
                InputOption::VALUE_OPTIONAL,
                'The module name (e.g., Customer)',
            )
            ->addOption(
                static::OPTION_CWD,
                'd',
                InputOption::VALUE_OPTIONAL,
                'The current working directory',
                getcwd(),
            )
            ->addOption(
                static::OPTION_CONFIG_FILE,
                'c',
                InputOption::VALUE_OPTIONAL,
                'Fully qualified path to the configuration file for the services.',
                'services.php',
            )
            ->addOption(
                static::OPTION_NO_CACHE,
                null,
                InputOption::VALUE_NONE,
                'Defines if multiple files should be compiled or a single file. When set, only one file is generated. This helps debugging.',
            );

        parent::configure();
    }

    /**
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $organization = $input->getArgument(static::ARGUMENT_NAMESPACE);

        if ($organization === 'project') {
            $this->output->writeln('<info>Container built successfully</info>');

            return static::CODE_SUCCESS;
        }

        $containerBuilderRequestTransfer = $this->createContainerBuilderRequestTransfer($input);

        $containerBuilderResponseTransfer = $this->getFacade()
            ->buildContainer($containerBuilderRequestTransfer);

        if (!$containerBuilderResponseTransfer->getIsSuccessful()) {
            foreach ($containerBuilderResponseTransfer->getErrors() as $error) {
                $this->output->writeln(sprintf('<error>%s</error>', $error));
            }

            return static::CODE_ERROR;
        }

        $this->output->writeln('<info>Container built successfully</info>');

        return static::CODE_SUCCESS;
    }

    /**
     * @param \Symfony\Component\Console\Input\InputInterface $input
     *
     * @return \Generated\Shared\Transfer\ContainerBuilderRequestTransfer
     */
    protected function createContainerBuilderRequestTransfer(InputInterface $input): ContainerBuilderRequestTransfer
    {
        $containerBuilderRequestTransfer = new ContainerBuilderRequestTransfer();
        $containerBuilderRequestTransfer->setNamespace($input->getArgument(static::ARGUMENT_NAMESPACE));

        $moduleName = $input->getOption(static::OPTION_MODULE_NAME);

        if ($moduleName) {
            $containerBuilderRequestTransfer->setModuleName($moduleName);
        }

        $cwd = $input->getOption(static::OPTION_CWD);

        if ($cwd) {
            $containerBuilderRequestTransfer->setCwd($cwd);
        }

        $configFile = $input->getOption(static::OPTION_CONFIG_FILE);

        if ($configFile) {
            $containerBuilderRequestTransfer->setConfigFile($configFile);
        }

        $noCache = $input->getOption(static::OPTION_NO_CACHE);

        if ($noCache) {
            $containerBuilderRequestTransfer->setCache(false);
        }

        return $containerBuilderRequestTransfer;
    }
}
