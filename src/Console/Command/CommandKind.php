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
 * The two things a module contributes to the assembled list, and the one
 * distinction the console draws between them.
 *
 * SEED CONTENT is a {@see \Uhifadhi\Contracts\Devkit\ContentProviderInterface}
 * — a slice of sample data seeded, in dependency order, by `fixtures:seed`. It is
 * not a standalone command; it is a step of the one command that stands a park
 * up. A COMMAND is a {@see \Uhifadhi\Contracts\Devkit\CommandProviderInterface}
 * descriptor — a named dev/maintenance command devkit registers on its own.
 */
enum CommandKind: string
{
    case SeedContent = 'seed-content';
    case Command = 'command';
}
