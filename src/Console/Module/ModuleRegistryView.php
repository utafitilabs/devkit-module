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

/**
 * The registry as a surface reads it: the rows and the fleet-wide facts the
 * Modules page heads itself with. Read once, so a card that says "10 installed"
 * and the rows beneath it can never describe two different fleets.
 */
final readonly class ModuleRegistryView
{
    /**
     * @param list<ModuleRow> $rows
     */
    public function __construct(
        public array $rows,
        public string $currentCore,
    ) {
    }

    public function installedCount(): int
    {
        return \count($this->rows);
    }

    public function onCoreCount(): int
    {
        return \count(array_filter(
            $this->rows,
            static fn (ModuleRow $r): bool => CoreState::OnCore === $r->coreState,
        ));
    }

    /**
     * How many rows COULD be on the core — everything that pins the contracts at
     * all. The denominator of "9 / 10 on current core": the contract itself and
     * unpinned infrastructure are not counted, because "on core" is not a
     * question about them.
     */
    public function pinsCoreCount(): int
    {
        return \count(array_filter(
            $this->rows,
            static fn (ModuleRow $r): bool => CoreState::NotApplicable !== $r->coreState,
        ));
    }

    public function totalGrants(): int
    {
        return array_sum(array_map(static fn (ModuleRow $r): int => $r->grants, $this->rows));
    }

    public function totalRoutes(): int
    {
        return array_sum(array_map(static fn (ModuleRow $r): int => $r->routes, $this->rows));
    }
}
