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

use Uhifadhi\Devkit\Command\FleetGateCommand;
use Uhifadhi\Devkit\Fleet\FleetSettings;
use Uhifadhi\Devkit\Fleet\GatePlanner;
use Uhifadhi\Devkit\Fleet\GateRunner;
use Uhifadhi\Devkit\Fleet\GitCheckoutVersions;

/*
 * THE FLEET GATE.
 *
 * Wired unconditionally, and it depends on nothing in the kernel: the gate does
 * not inspect the installation it is run from, it creates a new one and installs
 * the fleet into that. So it works from any checkout that has devkit, which is
 * what a gate has to do — the project it judges does not exist when it starts.
 *
 * Explicit wiring, no autowire/autoconfigure, ids prefixed with the bundle alias —
 * the reusable-bundle rule config/services.php cites
 * (https://symfony.com/doc/current/bundles/best_practices.html), and the
 * hand-applied `console.command` tag it explains
 * (https://symfony.com/doc/current/console.html, settled against
 * vendor/symfony/console/DependencyInjection/AddConsoleCommandPass.php: the name
 * and description come from the command's own #[AsCommand] attribute, so the tag
 * needs no `command:` value to stay lazy).
 */
return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    // `devkit.fleet` as the container holds it, read once into one object.
    $services->set('devkit.fleet.settings', FleetSettings::class)
        ->factory([FleetSettings::class, 'fromArray'])
        ->args([param('devkit.fleet')]);

    // The branch a sibling checkout has out, read from git. Head mode only ever
    // asks it about the checkouts that run names.
    $services->set('devkit.fleet.checkout_versions', GitCheckoutVersions::class);

    // The whole run, built before any of it happens — which is what makes
    // --dry-run the run itself rather than a description of it.
    $services->set('devkit.fleet.planner', GatePlanner::class)
        ->args([service('devkit.fleet.checkout_versions')]);

    $services->set('devkit.fleet.runner', GateRunner::class);

    $services->set('devkit.command.fleet_gate', FleetGateCommand::class)
        ->args([
            service('devkit.fleet.settings'),
            service('devkit.fleet.planner'),
            service('devkit.fleet.runner'),
        ])
        ->tag('console.command');
};
