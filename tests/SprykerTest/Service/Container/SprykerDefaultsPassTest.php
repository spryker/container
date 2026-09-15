<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\Service\Container;

use Codeception\Test\Unit;
use Spryker\Service\Container\Pass\SprykerDefaultsPass;
use SprykerTest\Service\Container\Fixture\AliasDependentFixtureFacade;
use SprykerTest\Service\Container\Fixture\AliasDependentFixtureFacadeInterface;
use SprykerTest\Service\Container\Fixture\FixtureFacade;
use SprykerTest\Service\Container\Fixture\FixtureFacadeInterface;
use SprykerTest\Service\Container\Fixture\InterfacelessFixtureFacade;
use SprykerTest\Service\Container\Fixture\ProjectFixtureFacade;
use SprykerTest\Service\Container\Fixture\ProjectFixtureFacadeInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group Service
 * @group Container
 * @group SprykerDefaultsPassTest
 * Add your own group annotations below this line
 */
class SprykerDefaultsPassTest extends Unit
{
    /**
     * The pass is asked to register a CORE `<Org>\Zed\<Module>\Business\<Module>FacadeInterface` and
     * resolves the concrete class through the project namespaces first. It used to register the
     * definition under `$resolvedClass . 'Interface'` — the PROJECT id — whenever the core interface
     * existed, which is always, so a module the project overrode was registered under the project id
     * ONLY and the core id that was actually requested never existed.
     *
     * Nothing autowired by the core interface could be built after that, and the failure surfaced
     * badly: an unregistered interface gets stubbed synthetic and the request dies at runtime inside
     * the service locator naming neither the service nor the argument that could not be resolved.
     */
    public function testRegisterDefaultServiceByInterfaceRegistersTheRequestedCoreIdWhenTheProjectOverridesTheClass(): void
    {
        // Arrange
        $containerBuilder = new ContainerBuilder();
        $compilerPass = $this->createCompilerPass(ProjectFixtureFacade::class);

        // Act
        $compilerPass->registerForTest($containerBuilder, FixtureFacadeInterface::class);

        // Assert
        $this->assertTrue(
            $containerBuilder->hasDefinition(FixtureFacadeInterface::class),
            'The core interface id the pass was asked to register must resolve.',
        );
        $this->assertSame(
            ProjectFixtureFacade::class,
            $containerBuilder->getDefinition(FixtureFacadeInterface::class)->getClass(),
            'The core id must resolve to the project override, not the core class.',
        );
    }

    /**
     * The project class IS the service the application means to use, so both ids have to resolve to
     * one shared instance — an alias, never a second definition.
     */
    public function testRegisterDefaultServiceByInterfaceAliasesTheProjectInterfaceOntoTheCoreId(): void
    {
        // Arrange
        $containerBuilder = new ContainerBuilder();
        $compilerPass = $this->createCompilerPass(ProjectFixtureFacade::class);

        // Act
        $compilerPass->registerForTest($containerBuilder, FixtureFacadeInterface::class);

        // Assert
        $this->assertTrue(
            $containerBuilder->hasAlias(ProjectFixtureFacadeInterface::class),
            'The project interface id must resolve too, as an alias rather than its own definition.',
        );
        $this->assertSame(
            FixtureFacadeInterface::class,
            (string)$containerBuilder->getAlias(ProjectFixtureFacadeInterface::class),
            'The project interface must point at the core id so both share one instance.',
        );
    }

    public function testRegisterDefaultServiceByInterfaceBuildsOneSharedInstanceForBothIds(): void
    {
        // Arrange
        $containerBuilder = new ContainerBuilder();
        $compilerPass = $this->createCompilerPass(ProjectFixtureFacade::class);
        $compilerPass->registerForTest($containerBuilder, FixtureFacadeInterface::class);

        // Act
        $containerBuilder->compile();

        // Assert
        $this->assertSame(
            $containerBuilder->get(FixtureFacadeInterface::class),
            $containerBuilder->get(ProjectFixtureFacadeInterface::class),
            'Aliasing rather than re-registering is what makes both ids one service.',
        );
    }

    /**
     * Without the `$optionalInterfaceName !== $interfaceName` guard this would alias the core id onto
     * itself, which Symfony resolves as a circular reference at compile time.
     */
    public function testRegisterDefaultServiceByInterfaceAddsNoSelfAliasWhenTheProjectDoesNotOverrideTheClass(): void
    {
        // Arrange
        $containerBuilder = new ContainerBuilder();
        $compilerPass = $this->createCompilerPass(FixtureFacade::class);

        // Act
        $compilerPass->registerForTest($containerBuilder, FixtureFacadeInterface::class);
        $containerBuilder->compile();

        // Assert
        $this->assertFalse(
            $containerBuilder->hasAlias(FixtureFacadeInterface::class),
            'The core id must keep its definition, never become an alias to itself.',
        );
        $this->assertInstanceOf(FixtureFacade::class, $containerBuilder->get(FixtureFacadeInterface::class));
    }

    /**
     * A resolved class is not guaranteed to have a `...Interface` sibling. The old code appended the
     * suffix unconditionally and registered the definition under an id that need not exist at all.
     */
    public function testRegisterDefaultServiceByInterfaceAddsNoAliasWhenTheResolvedClassHasNoOwnInterface(): void
    {
        // Arrange
        $containerBuilder = new ContainerBuilder();
        $compilerPass = $this->createCompilerPass(InterfacelessFixtureFacade::class);

        // Act
        $compilerPass->registerForTest($containerBuilder, FixtureFacadeInterface::class);

        // Assert
        $this->assertTrue($containerBuilder->hasDefinition(FixtureFacadeInterface::class));
        $this->assertSame(
            [],
            $containerBuilder->getAliases(),
            'No alias may be added when the resolved class has no interface of its own.',
        );
        $this->assertSame(
            InterfacelessFixtureFacade::class,
            $containerBuilder->getDefinition(FixtureFacadeInterface::class)->getClass(),
        );
    }

    /**
     * Both ids are looked up by autowiring, which only sees public services.
     */
    public function testRegisterDefaultServiceByInterfaceMakesBothIdsPublic(): void
    {
        // Arrange
        $containerBuilder = new ContainerBuilder();
        $compilerPass = $this->createCompilerPass(ProjectFixtureFacade::class);

        // Act
        $compilerPass->registerForTest($containerBuilder, FixtureFacadeInterface::class);

        // Assert
        $this->assertTrue($containerBuilder->getDefinition(FixtureFacadeInterface::class)->isPublic());
        $this->assertTrue($containerBuilder->getAlias(ProjectFixtureFacadeInterface::class)->isPublic());
    }

    /**
     * `markDependenciesAsLazy()` reflects the resolved class's constructor and looks each dependency
     * up in the container. `ContainerBuilder::has()` is ALSO true for an alias while
     * `getDefinition()` throws `ServiceNotFoundException` for one — and this method catches only
     * `ReflectionException`, so the throw would abort container compilation outright.
     *
     * Aliases did not exist in this container before the project-override fix above created them,
     * which is what made the pre-existing `has()` guard reachable.
     */
    public function testRegisterDefaultServiceByInterfaceDoesNotThrowWhenAConstructorDependsOnAnAliasedId(): void
    {
        // Arrange
        $containerBuilder = new ContainerBuilder();
        $this->createCompilerPass(ProjectFixtureFacade::class)
            ->registerForTest($containerBuilder, FixtureFacadeInterface::class);

        // Act
        $this->createCompilerPass(AliasDependentFixtureFacade::class)
            ->registerForTest($containerBuilder, AliasDependentFixtureFacadeInterface::class);

        // Assert
        $this->assertTrue($containerBuilder->hasDefinition(AliasDependentFixtureFacadeInterface::class));
        $this->assertTrue(
            $containerBuilder->hasAlias(ProjectFixtureFacadeInterface::class),
            'The aliased dependency must survive being reflected over.',
        );
    }

    /**
     * Class resolution walks the real project namespaces, which the fixtures are deliberately not in;
     * stubbing it isolates the registration behaviour under test.
     */
    protected function createCompilerPass(string $resolvedClass): SprykerDefaultsPass
    {
        return new class ($resolvedClass) extends SprykerDefaultsPass {
            public function __construct(protected string $resolvedClass)
            {
            }

            public function registerForTest(ContainerBuilder $containerBuilder, string $interfaceName): void
            {
                $this->registerDefaultServiceByInterface($containerBuilder, $interfaceName);
            }

            protected function findResolvableClassForInterface(string $interfaceName): ?string
            {
                return $this->resolvedClass;
            }
        };
    }
}
