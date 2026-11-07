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

namespace Uhifadhi\Devkit\Tests\Integration\Console\Fixtures;

use Uhifadhi\Devkit\Console\Module\AreaStatesInterface;

/** Two areas: patrols on in the first only, the base module on in both. */
final readonly class FixtureAreaStates implements AreaStatesInterface
{
    public function areas(): array
    {
        return ['area-1' => 'North Block', 'area-2' => 'South Block'];
    }

    public function switchedOn(): array
    {
        return ['area-1' => ['areas', 'patrol'], 'area-2' => ['areas']];
    }
}
