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
 * WHERE EACH MODULE IS SWITCHED ON — the registry's per-area ledger, read
 * whole. Only an installation with the registry and a database has one; the
 * console reads it when it is there and says so when it is not.
 */
interface AreaStatesInterface
{
    /**
     * Every area the installation holds, in its own order.
     *
     * @return array<string, string> area uuid => the area's name
     */
    public function areas(): array;

    /**
     * The module slugs switched on in each area.
     *
     * @return array<string, list<string>> area uuid => slugs
     */
    public function switchedOn(): array;
}
