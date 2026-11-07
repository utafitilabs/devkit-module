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
 * Where a package is switched on across the fleet — classified from what the
 * console can read WITHOUT a database.
 *
 * `TheCore` — the core package, which is on in nothing because it is not a
 * module. `HostWide` — infrastructure (a package with no module provider) and
 * base modules (a provider that answers base(): seeded active in every area).
 * `PerArea` — an installable capability module, switched on per area by an admin.
 *
 * The exact per-area state is not this classification: it is the registry's
 * ledger, read through {@see AreaStatesInterface} where there is one.
 */
enum ModuleReach
{
    case TheCore;
    case HostWide;
    case PerArea;
}
