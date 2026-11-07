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
 * ONE PAGE OF A TABLE, as the house foot states it.
 *
 * @template T
 */
final readonly class TablePage
{
    /**
     * @param list<T> $rows
     */
    public function __construct(
        public array $rows,
        public int $total,
        public string $size,
        public int $page,
        public int $pages,
    ) {
    }

    public function hasPrevious(): bool
    {
        return $this->page > 1;
    }

    public function hasNext(): bool
    {
        return $this->page < $this->pages;
    }
}
