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
 * ONE MODULE ACROSS THE AREAS: on or off in each, by the ledger — or on in
 * all of them by definition, for a package that is no module of an area.
 */
final readonly class AreaMatrixRow
{
    /**
     * @param array<string, bool> $on area uuid => switched on
     */
    public function __construct(
        public ?string $slug,
        public string $label,
        public string $version,
        public bool $perArea,
        public array $on,
    ) {
    }

    public function onCount(): int
    {
        return \count(array_filter($this->on));
    }
}
