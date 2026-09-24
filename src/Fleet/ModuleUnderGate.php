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

namespace Uhifadhi\Devkit\Fleet;

/**
 * ONE MODULE AS THE GATE KNOWS IT — everything the gate has to be told about a
 * module, and nothing the module itself decides.
 *
 * All of it is configuration (`devkit.fleet.modules`), so adding a module to the
 * fleet is a line in an installation's config rather than an edit here: the gate
 * discovers what to install instead of carrying the list in code.
 */
final readonly class ModuleUnderGate
{
    /**
     * @param string      $name           the bare module word, `patrol` — the composer package is derived from it
     * @param string      $page           where the module first answers once it is on; `%s` is the area's uuid
     * @param string|null $catalogueSlug  the slug the catalogue must list after the install, or null for an
     *                                    infrastructure module that declares no tile and answers everywhere at once
     * @param string|null $databaseEnv    a module keeping its tables in a database of its own names the variable
     *                                    holding that database's url; the gate creates it fresh
     * @param string|null $migrateCommand what migrates that separate database
     * @param string|null $repository     a module outside Packagist names the repository `composer config` writes
     *                                    before the require — released mode only; head mode reads the checkout
     * @param bool        $private        a module of the managed-hosting tier: gated the same way, listed nowhere
     */
    public function __construct(
        public string $name,
        public string $page,
        public ?string $catalogueSlug = null,
        public ?string $databaseEnv = null,
        public ?string $migrateCommand = null,
        public ?string $repository = null,
        public bool $private = false,
    ) {
    }

    public function package(): string
    {
        return 'uhifadhi/'.$this->name.'-module';
    }

    /** The directory head mode reads this module from, inside the workspace. */
    public function checkout(string $workspace): string
    {
        return rtrim($workspace, '/').'/'.$this->name.'-module';
    }

    public function pageFor(string $areaUuid): string
    {
        return str_contains($this->page, '%s') ? \sprintf($this->page, $areaUuid) : $this->page;
    }
}
