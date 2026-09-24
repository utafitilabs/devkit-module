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
 * WHERE THE PACKAGES COME FROM, which is the only difference between the two
 * runs of the gate.
 *
 * Released asks: does the fleet as published install and run as one product —
 * every package resolved the way the starter's own README resolves it. Head
 * asks the same question of the sibling checkouts at their last commit, so
 * "would an install work if I tagged everything right now" is answered before
 * the tag rather than after it.
 */
enum GateMode: string
{
    case Released = 'released';
    case Head = 'head';
}
