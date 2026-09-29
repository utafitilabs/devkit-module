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

namespace Uhifadhi\Devkit\Tests\Integration\Console;

use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\UX\Icons\UXIconsBundle;
use Symfony\UX\StimulusBundle\StimulusBundle;
use Uhifadhi\Bundle\RegistryBundle\RegistryBundle;
use Uhifadhi\Bundle\ShellBundle\ShellBundle;
use Uhifadhi\Devkit\Tests\Integration\Console\Fixtures\FixtureModuleProvider;
use Uhifadhi\Devkit\Tests\Integration\Fixtures\RecordingCommandProvider;
use Uhifadhi\Devkit\Tests\Integration\Fixtures\RecordingContentProvider;
use Uhifadhi\Devkit\UhifadhiDevkitBundle;

/**
 * THE INSTALLATION THE DEV CONSOLE LIVES IN — the shell frame the console renders
 * in (framework + twig + ux-icons + stimulus + shell), devkit itself, and NO
 * database: like the shell, the console is booted without one, and the per-area
 * data that would need one is the flagged deferral, not a boot requirement.
 *
 * The kernel plays the ALWAYS-INSTALLED MODULES: it tags a few fixture providers
 * — seed-content, a command, and two module providers on the `uhifadhi.module`
 * tag — by hand, exactly as a reusable module bundle tags its own. devkit's
 * introspection then reads them just as it would a real dev install. The
 * registry is intentionally NOT registered: the console reads the module tag as
 * a string and never needs the registry's runtime (or its Doctrine) to inspect it.
 */
final class ConsoleTestKernel extends Kernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new TwigBundle();
        yield new UXIconsBundle();
        yield new StimulusBundle();
        yield new ShellBundle();
        yield new UhifadhiDevkitBundle();
    }

    public function getProjectDir(): string
    {
        return __DIR__.'/Fixtures/app';
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'router' => ['utf8' => true],
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'asset_mapper' => [
                'paths' => [$this->getProjectDir().'/assets' => ''],
            ],
        ]);

        $container->services()->set('logger', NullLogger::class);

        $container->extension('twig', ['strict_variables' => true]);

        // No network from a render: the console's own SVGs are inline, and any
        // icon a fixture might name resolves to nothing rather than an HTTP call.
        $container->extension('ux_icons', [
            'iconify' => ['enabled' => false],
            'ignore_not_found' => true,
        ]);

        $services = $container->services();

        // The always-installed modules' inert seed-content providers, tagged by
        // hand (a reusable bundle's services are not autoconfigured).
        foreach ([
            'devkit.test.content.area' => ['area', []],
            'devkit.test.content.patrol' => ['patrol', ['area']],
            'devkit.test.content.incident' => ['incident', ['area', 'patrol']],
        ] as $id => [$key, $dependsOn]) {
            $services->set($id, RecordingContentProvider::class)
                ->args([$key, $dependsOn])
                ->tag(UhifadhiDevkitBundle::CONTENT_PROVIDER_TAG);
        }

        $services->set('devkit.test.command_provider', RecordingCommandProvider::class)
            ->tag(UhifadhiDevkitBundle::COMMAND_PROVIDER_TAG);

        // Two module providers on the module tag: a base one (host-wide) and an
        // installable one that declares a permission (per-area).
        $services->set('devkit.test.module.areas', FixtureModuleProvider::class)
            ->args(['areas', 'Areas', true, 0])
            ->tag(RegistryBundle::MODULE_TAG);

        $services->set('devkit.test.module.patrol', FixtureModuleProvider::class)
            ->args(['patrol', 'Patrols', false, 2])
            ->tag(RegistryBundle::MODULE_TAG);
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('@UhifadhiDevkitBundle/config/routes/console.php');
    }

    public function getCacheDir(): string
    {
        // Debug on and off compile to different containers (the kernel.debug the
        // console gates on is baked in), so they must not share a cache.
        return sys_get_temp_dir().'/devkit-module-tests/console-cache/'.$this->environment.'/'.($this->debug ? 'debug' : 'prod');
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/devkit-module-tests/console-log';
    }
}
