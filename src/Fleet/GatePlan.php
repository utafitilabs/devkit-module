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
 * THE WHOLE RUN, IN ORDER, before any of it happens.
 *
 * `--dry-run` prints this; a real run performs exactly it. One list, so the two
 * can never describe different things.
 */
final readonly class GatePlan
{
    /** @param list<GateStep> $steps */
    public function __construct(public array $steps)
    {
    }

    public function count(): int
    {
        return \count($this->steps);
    }

    /** @return list<string> */
    public function describe(): array
    {
        return array_map(static fn (GateStep $step): string => $step->describe(), $this->steps);
    }
}
