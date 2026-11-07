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

namespace Uhifadhi\Devkit\Tests\Unit\Console;

use PHPUnit\Framework\TestCase;
use Uhifadhi\Devkit\Console\Command\AssembledCommand;
use Uhifadhi\Devkit\Console\Command\CommandCatalogue;
use Uhifadhi\Devkit\Console\Command\CommandGroup;
use Uhifadhi\Devkit\Console\Command\CommandKind;
use Uhifadhi\Devkit\Console\Command\ListedCommand;
use Uhifadhi\Devkit\Console\Package\ResolvedPackage;

/**
 * THE COMMANDS REGISTER, read off the catalogue: every command one row that
 * names the module it came from, narrowed by module, kind and a search, and
 * the counts the bar's chips carry.
 */
final class CommandCatalogueTest extends TestCase
{
    public function testEveryCommandIsOneRowNamingItsModuleInContributionOrder(): void
    {
        $rows = self::catalogue()->listed(null, null, null);

        self::assertSame(
            ['fixtures:seed', 'trail:seed', 'trail:reindex', 'gate:sync'],
            array_map(static fn (ListedCommand $row): string => $row->command->name, $rows),
        );
        self::assertSame(['devkit', 'trail', 'trail', 'gate'], array_map(static fn (ListedCommand $row): string => $row->group->label, $rows));
    }

    public function testTheModuleTheKindAndTheSearchEachNarrow(): void
    {
        $catalogue = self::catalogue();

        self::assertSame(['trail:seed', 'trail:reindex'], self::names($catalogue->listed('trail', null, null)));
        self::assertSame(['trail:reindex', 'gate:sync'], self::names($catalogue->listed(null, 'command', null)));
        self::assertSame(['trail:reindex'], self::names($catalogue->listed('trail', 'command', null)));
        self::assertSame(['gate:sync'], self::names($catalogue->listed(null, null, 'BARRIER')), 'the search reads the description, in any case');
        self::assertSame(['trail:reindex'], self::names($catalogue->listed(null, null, 'reindex')), 'and the name');
        self::assertSame([], $catalogue->listed('nobody', null, null));
        self::assertSame([], $catalogue->listed(null, 'not-a-kind', null));
    }

    public function testTheChipsCountTheWholeCatalogue(): void
    {
        $catalogue = self::catalogue();

        self::assertSame(['devkit' => 1, 'trail' => 2, 'gate' => 1], $catalogue->moduleCounts());
        self::assertSame(['seed-content' => 2, 'command' => 2], $catalogue->kindCounts());
    }

    private static function catalogue(): CommandCatalogue
    {
        return new CommandCatalogue([
            new CommandGroup('devkit', null, [new AssembledCommand('fixtures:seed', CommandKind::SeedContent, 'Load every module’s seed content.')]),
            new CommandGroup('trail', new ResolvedPackage('example/trail-module', '0.4.0', 'trail'), [
                new AssembledCommand('trail:seed', CommandKind::SeedContent, 'Seed the sample trails.'),
                new AssembledCommand('trail:reindex', CommandKind::Command, 'Rebuild the trail index.'),
            ]),
            new CommandGroup('gate', new ResolvedPackage('example/gate-module', '0.2.0', 'gate'), [
                new AssembledCommand('gate:sync', CommandKind::Command, 'Sync the barrier log.'),
            ]),
            new CommandGroup('quiet', new ResolvedPackage('example/quiet-module', '0.1.0', 'quiet'), []),
        ]);
    }

    /**
     * @param list<ListedCommand> $rows
     *
     * @return list<string>
     */
    private static function names(array $rows): array
    {
        return array_map(static fn (ListedCommand $row): string => $row->command->name, $rows);
    }
}
