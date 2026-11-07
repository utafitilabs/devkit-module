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

use Uhifadhi\Bundle\RegistryBundle\Repository\AreaModuleRepository;
use Uhifadhi\Devkit\Console\Module\RegistryAreaStates;

/*
 * THE PER-AREA LEDGER, where the installation keeps one: imported only beside
 * the console and only where the registry and Doctrine are both installed,
 * so the Modules surface can draw where each module is switched on.
 */
return static function (ContainerConfigurator $container): void {
    $container->services()->set('devkit.console.area_states', RegistryAreaStates::class)
        ->args([service(AreaModuleRepository::class)]);
};
