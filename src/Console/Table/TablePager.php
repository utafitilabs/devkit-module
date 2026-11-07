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

namespace Uhifadhi\Devkit\Console\Table;

/**
 * CUTS A TABLE INTO THE HOUSE FOOT'S PAGES — 20 a page unless the reader
 * chose 50, 100 or all; anything not offered falls back rather than failing.
 */
final readonly class TablePager
{
    /** @var list<string> */
    public const array SIZES = ['20', '50', '100', 'all'];

    /**
     * @template T
     *
     * @param list<T> $rows
     *
     * @return TablePage<T>
     */
    public function page(array $rows, ?string $size, ?string $page): TablePage
    {
        $size = \in_array($size, self::SIZES, true) ? $size : self::SIZES[0];
        $total = \count($rows);
        if ('all' === $size) {
            return new TablePage($rows, $total, $size, 1, 1);
        }

        $perPage = (int) $size;
        $pages = max(1, (int) ceil($total / $perPage));
        $current = min($pages, max(1, ctype_digit((string) $page) ? (int) $page : 1));

        return new TablePage(\array_slice($rows, ($current - 1) * $perPage, $perPage), $total, $size, $current, $pages);
    }
}
