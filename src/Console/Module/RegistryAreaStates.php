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

namespace Uhifadhi\Devkit\Console\Module;

use Uhifadhi\Bundle\RegistryBundle\Repository\AreaModuleRepository;

/**
 * The ledger as the registry keeps it: every area it holds, and every
 * active assignment of a module to one.
 */
final readonly class RegistryAreaStates implements AreaStatesInterface
{
    public function __construct(
        private AreaModuleRepository $areaModules,
    ) {
    }

    public function areas(): array
    {
        $areas = [];
        foreach ($this->areaModules->everyArea() as $area) {
            $uuid = $area->getUuidString();
            if (null !== $uuid) {
                $areas[$uuid] = $area->getName() ?? $uuid;
            }
        }

        return $areas;
    }

    public function switchedOn(): array
    {
        return array_map(
            static fn (array $slugs): array => array_map(strval(...), array_keys($slugs)),
            $this->areaModules->runningSince(),
        );
    }
}
