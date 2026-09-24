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

use Uhifadhi\Devkit\Console\Package\ResolvedPackage;

/**
 * One row of the module registry — a package as the fleet register presents it.
 *
 * `coreConstraint` is the version constraint the package declares on
 * `uhifadhi/uhifadhi`, or null when it declares none (the core
 * itself, and any infrastructure that does not pin it). `grants` and
 * `routes` are counted only where a module provider was found for the package —
 * infrastructure contributes neither through the module tag.
 */
final readonly class ModuleRow
{
    public function __construct(
        public ResolvedPackage $package,
        public ?string $coreConstraint,
        public CoreState $coreState,
        public int $grants,
        public int $routes,
        public ModuleReach $reach,
    ) {
    }
}
