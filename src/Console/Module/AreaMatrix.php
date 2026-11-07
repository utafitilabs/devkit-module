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
 * THE PER-AREA MODULE STATE — one row per module, one column per area; the
 * modules switched on per area first, then those on in every area.
 */
final readonly class AreaMatrix
{
    public const string REACH_PER_AREA = 'per';
    public const string REACH_EVERY = 'every';

    /**
     * @param array<string, string> $areas area uuid => name
     * @param list<AreaMatrixRow>   $rows
     */
    public function __construct(
        public array $areas,
        public array $rows,
    ) {
    }

    /**
     * @return list<AreaMatrixRow>
     */
    public function listed(?string $reach, ?string $query): array
    {
        $query = trim((string) $query);

        return array_values(array_filter($this->rows, static fn (AreaMatrixRow $row): bool => (null === $reach || (self::REACH_PER_AREA === $reach) === $row->perArea)
            && ('' === $query || false !== mb_stripos($row->label.' '.$row->slug, $query))));
    }

    public function perAreaCount(): int
    {
        return \count(array_filter($this->rows, static fn (AreaMatrixRow $row): bool => $row->perArea));
    }

    /** How many areas a module is on in, or null where the matrix has no such module. */
    public function onCount(?string $slug): ?int
    {
        foreach ($this->rows as $row) {
            if (null !== $slug && $slug === $row->slug) {
                return $row->onCount();
            }
        }

        return null;
    }
}
