<?php

declare(strict_types=1);

/*
 * This file is part of the UhifadhiLabs Devkit Module.
 *
 * (c) Ezekiel Mjema <https://github.com/eemjema>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Uhifadhi\Devkit;

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Uhifadhi\Contracts\Devkit\CommandProviderInterface;
use Uhifadhi\Contracts\Devkit\ContentProviderInterface;
use Uhifadhi\Devkit\Console\DependencyInjection\Compiler\CollectContributionPointsPass;
use Uhifadhi\Devkit\DependencyInjection\Compiler\DecorateCommandLoaderPass;
use Uhifadhi\Devkit\DependencyInjection\DevkitConfiguration;

/**
 * Devkit — THE dev-only module, and a collector.
 *
 * It installs through require-dev, so it and everything it registers are absent
 * from a production build: require-dev IS the production firewall. Its job is to
 * gather the INERT provider classes other modules ship — declared through the
 * two contracts the core publishes, which live there precisely so an
 * always-installed module can name them even when devkit is not present — and
 * materialise them into things a developer can run:
 *
 *   - every {@see ContentProviderInterface} becomes a step of `fixtures:demo`,
 *     seeded in dependsOn() topological order;
 *   - every {@see CommandProviderInterface}'s descriptors become real console
 *     commands.
 *
 * Both only ever exist where devkit is installed, which is only ever dev and CI.
 *
 * Explicit wiring, no autowire/autoconfigure — the reusable-bundle rule (see
 * config/services.php). Modules tag their inert providers with the tag STRINGS
 * below, written as literals in the module's own service config: a module cannot
 * reference these constants, because devkit is absent from the production build
 * the module also ships into. The constants exist for devkit's own wiring and
 * for tests.
 */
final class UhifadhiDevkitBundle extends AbstractBundle
{
    /**
     * Tag for {@see ContentProviderInterface} services. `fixtures:demo` collects
     * everything carrying it. Modules add it as a literal string.
     */
    public const string CONTENT_PROVIDER_TAG = 'uhifadhi.devkit.content_provider';

    /**
     * Tag for {@see CommandProviderInterface} services. The command loader turns
     * each collected provider's descriptors into console commands. Modules add it
     * as a literal string.
     */
    public const string COMMAND_PROVIDER_TAG = 'uhifadhi.devkit.command_provider';

    protected string $extensionAlias = 'devkit';

    public function configure(DefinitionConfigurator $definition): void
    {
        DevkitConfiguration::define($definition->rootNode());
    }

    /**
     * @param array<string, mixed> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        // The collector's static wiring — always.
        $container->import('../config/services.php');

        // The fleet gate — always, and it reads nothing from this kernel: the
        // installation it judges does not exist when it starts. What the fleet
        // is made of is configuration, so a new official module is a line under
        // devkit.fleet and no release of this bundle.
        $builder->setParameter('devkit.fleet', \is_array($config['fleet'] ?? null) ? $config['fleet'] : []);
        $container->import('../config/fleet.php');

        // The dev-console UI — ONLY where twig-bundle is installed, i.e. a real
        // devkit install (devkit requires twig, routing and the shell). The
        // collector can be booted on framework-bundle alone, and its container
        // must not gain a dependency on a router or twig it does not have; the
        // console's own wiring lives behind this gate for exactly that reason.
        //
        // Gated on kernel.bundles rather than hasExtension('twig'): at
        // loadExtension time not every bundle's extension is registered yet, but
        // the registered-bundles map is a kernel parameter set before any of them
        // loads.
        /** @var array<string, class-string> $bundles */
        $bundles = $builder->hasParameter('kernel.bundles') ? $builder->getParameter('kernel.bundles') : [];
        if (isset($bundles['TwigBundle'])) {
            $container->import('../config/console.php');
        }

        // The zone import — ONLY where the core's areas are installed, gated the
        // same way and for the same reason: it names an area repository and the
        // core's import service, neither of which a framework-only kernel has.
        if (isset($bundles['AreaBundle'])) {
            $container->import('../config/zones.php');
        }
    }

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        /*
         * A COURTESY for hosts and modules that DO autoconfigure: an application
         * whose services default to autoconfigure:true gets its providers tagged
         * just by implementing the interface. Modules shipped as reusable bundles
         * are NOT autoconfigured (config/services.php explains why) and still tag
         * their providers by hand with the literal tag strings above — but a
         * host's own app-level provider need not.
         */
        $container->registerForAutoconfiguration(ContentProviderInterface::class)
            ->addTag(self::CONTENT_PROVIDER_TAG);
        $container->registerForAutoconfiguration(CommandProviderInterface::class)
            ->addTag(self::COMMAND_PROVIDER_TAG);

        /*
         * Wrap the framework's command loader so the modules' CommandDescriptors
         * become console commands. It runs at TYPE_BEFORE_REMOVING with a lower
         * priority than symfony/console's AddConsoleCommandPass (default 0), so
         * it runs just AFTER the loader it decorates has been created — the loader
         * does not exist during the ordinary decoration phase, which is why this
         * is a hand-written pass (see DecorateCommandLoaderPass).
         */
        $container->addCompilerPass(new DecorateCommandLoaderPass(), PassConfig::TYPE_BEFORE_REMOVING, -16);

        /*
         * Collect, for every known contribution point, the classes registered on
         * its tag — the data the Wiring surface's inspector reads. It runs at
         * TYPE_BEFORE_REMOVING so every tag (including the ones this bundle adds
         * by registerForAutoconfiguration above) has settled and none has been
         * optimised away. See CollectContributionPointsPass.
         */
        $container->addCompilerPass(new CollectContributionPointsPass(), PassConfig::TYPE_BEFORE_REMOVING);
    }
}
