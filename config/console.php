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

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Uhifadhi\Bundle\RegistryBundle\RegistryBundle;
use Uhifadhi\Contracts\Access\ConcernSourceInterface;
use Uhifadhi\Devkit\Console\Command\CommandInventory;
use Uhifadhi\Devkit\Console\Controller\ConsoleController;
use Uhifadhi\Devkit\Console\Doctor\Conformance;
use Uhifadhi\Devkit\Console\Module\ModuleRegistry;
use Uhifadhi\Devkit\Console\Package\ComposerPackageIntrospector;
use Uhifadhi\Devkit\Console\Package\PackageIntrospector;
use Uhifadhi\Devkit\Console\Wiring\ContributionPointInspector;
use Uhifadhi\Devkit\UhifadhiDevkitBundle;

/*
 * THE DEV CONSOLE'S UI WIRING (slice 2).
 *
 * Imported by UhifadhiDevkitBundle::loadExtension ONLY where twig-bundle is
 * present — a real devkit install, which requires twig, routing and the shell.
 * The collector (slice 1) runs without any of that, so this file is not loaded
 * there and its router/twig dependencies never reach a UI-less container.
 *
 * The surfaces read the same tags the collector runs on — the tagged
 * content/command providers, the module providers, the router — and turn them
 * into the four inspector surfaces. Everything is dev-only by the same firewall
 * the collector is: devkit is require-dev.
 *
 * Explicit wiring, no autowire/autoconfigure, ids prefixed with the bundle alias
 * — the reusable-bundle rule (see config/services.php).
 */
return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    // What Composer knows, behind an interface so tests can hand the registry a
    // fleet they control.
    $services->set('devkit.console.packages', ComposerPackageIntrospector::class);
    $services->alias(PackageIntrospector::class, 'devkit.console.packages');

    // Commands surface — the assembled list, grouped by contributing module.
    $services->set('devkit.console.command_inventory', CommandInventory::class)
        ->args([
            tagged_iterator(UhifadhiDevkitBundle::CONTENT_PROVIDER_TAG),
            tagged_iterator(UhifadhiDevkitBundle::COMMAND_PROVIDER_TAG),
            service('devkit.console.packages'),
        ]);

    // Modules surface — the installed fleet as one register.
    $services->set('devkit.console.module_registry', ModuleRegistry::class)
        ->args([
            tagged_iterator(RegistryBundle::MODULE_TAG),
            service('devkit.console.packages'),
            service('router'),
            tagged_iterator(ConcernSourceInterface::TAG),
        ]);

    // Doctor surface — the compatibility matrix + findings.
    $services->set('devkit.console.conformance', Conformance::class)
        ->args([
            service('devkit.console.module_registry'),
            service('devkit.console.packages'),
        ]);

    // Wiring surface — the tag inspector. Its first argument (the collected
    // tag => classes map) is filled by CollectContributionPointsPass at compile time.
    $services->set('devkit.console.contribution_points', ContributionPointInspector::class)
        ->args([
            [],
            service('devkit.console.packages'),
        ]);

    // The console's controller — the one public service, reached by the route
    // resource an application imports in a when@dev block.
    $services->set('devkit.console.controller', ConsoleController::class)
        ->args([
            service('twig'),
            service('devkit.console.command_inventory'),
            service('devkit.console.module_registry'),
            service('devkit.console.conformance'),
            service('devkit.console.contribution_points'),
            param('kernel.debug'),
        ])
        ->public();
};
