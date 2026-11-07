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

namespace Uhifadhi\Devkit\Console\Command;

/**
 * ONE ROW OF THE COMMANDS REGISTER: a command and the contributing module it
 * came from, which the row states on its sub-line.
 */
final readonly class ListedCommand
{
    public function __construct(
        public AssembledCommand $command,
        public CommandGroup $group,
    ) {
    }
}
