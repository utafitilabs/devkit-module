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
 * THE STEP THAT BROKE, and everything needed to act on it: what was being done,
 * the command it was, and what came back. The gate stops here — every step after
 * a red one would be measuring a project that is already wrong.
 */
final class GateFailure extends \RuntimeException
{
    public function __construct(
        public readonly GateStep $step,
        public readonly string $output,
        string $why,
    ) {
        parent::__construct($why);
    }
}
