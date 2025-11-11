<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Service\Container\Pass;

use ReflectionClass;
use ReflectionException;
use Spryker\Service\Container\ProxyFactory;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class BridgePass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $definitionIds = $container->getServiceIds();

        foreach ($definitionIds as $id) {
            if (!$container->hasDefinition($id)) {
                continue;
            }

            $definition = $container->getDefinition($id);

            $class = $definition->getClass();

            // Only run for Bridges and Adapters, if not a Bridge dependency, continue with the next definition.
            if (!$class || $definition->isAbstract() || (!str_ends_with($class, 'Bridge') && !str_ends_with($class, 'Adapter'))) {
                continue;
            }

            try {
                if (!class_exists($class)) {
                    continue;
                }
                $reflectionClass = new ReflectionClass($class);
            } catch (ReflectionException) {
                continue;
            }

            $constructor = $reflectionClass->getConstructor();

            if (!$constructor) {
                continue;
            }

            // We do not have a typed constructor, and we have to use the DocBlock to determine the used class.
            $docComment = $constructor->getDocComment();

            if ($docComment === false) {
                continue;
            }

            $paramAnnotations = $this->parseParamAnnotations($docComment);

            foreach ($constructor->getParameters() as $parameter) {
                if ($parameter->getType() !== null) {
                    continue;
                }

                $paramName = $parameter->getName();

                if (!isset($paramAnnotations[$paramName])) {
                    continue;
                }

                $dependencyId = $paramAnnotations[$paramName];

                /**
                 * When compiling a module container, we do not have access to outside dependencies, and we have to create
                 * a Proxy for this which will be resolved on the project later.
                 */
                if (!$container->has($dependencyId)) {
                    $proxyDefinition = new Definition();
                    $proxyDefinition->setClass($dependencyId);
                    $proxyDefinition->setFactory([
                        new Reference(ProxyFactory::class),
                        'createProxy',
                    ]);
                    $proxyDefinition->setArguments([$dependencyId]);
                    $container->setDefinition($dependencyId, $proxyDefinition);
                }

                $definition->setArgument('$' . $paramName, new Reference($dependencyId));
            }
        }
    }

    /**
     * @param string $docComment
     *
     * @return array<string, string>
     */
    private function parseParamAnnotations(string $docComment): array
    {
        $annotations = [];
        preg_match_all('/@param\s+([\\\\\w]+)\s+\$(\w+)/', $docComment, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $type = $match[1];
            $name = $match[2];
            $annotations[$name] = ltrim($type, '\\');
        }

        return $annotations;
    }
}
