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

use Uhifadhi\Bundle\AreaBundle\Repository\AreaOfInterestRepository;
use Uhifadhi\Bundle\AreaBundle\Service\ZoneImportService;
use Uhifadhi\Devkit\Command\ZoneImportCommand;

/*
 * The dev-only path into the core's zone import.
 *
 * Its own file, imported by UhifadhiDevkitBundle::loadExtension ONLY where the
 * core's AreaBundle is in the kernel — the same gate the dev console's UI sits
 * behind, for the same reason: the collector can be booted on framework-bundle
 * alone, and a container built without areas must not hold a service referencing
 * an area repository that is not there.
 *
 * Explicit wiring, no autowire/autoconfigure, id prefixed with the bundle alias —
 * the reusable-bundle rule config/services.php cites
 * (https://symfony.com/doc/current/bundles/best_practices.html), and the
 * hand-applied `console.command` tag it explains
 * (https://symfony.com/doc/current/console.html, settled against
 * vendor/symfony/console/DependencyInjection/AddConsoleCommandPass.php: the name
 * and description come from this command's own #[AsCommand] attribute, so the
 * tag needs no `command:` value to stay lazy).
 */
return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('devkit.command.zone_import', ZoneImportCommand::class)
        ->args([
            service(AreaOfInterestRepository::class),
            service(ZoneImportService::class),
        ])
        ->tag('console.command');
};
