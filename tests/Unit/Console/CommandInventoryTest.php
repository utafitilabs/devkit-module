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
use Uhifadhi\Devkit\Console\Command\CommandInventory;
use Uhifadhi\Devkit\Console\Command\CommandKind;
use Uhifadhi\Devkit\Console\Package\ResolvedPackage;
use Uhifadhi\Devkit\Tests\Integration\Fixtures\RecordingCommandProvider;
use Uhifadhi\Devkit\Tests\Integration\Fixtures\RecordingContentProvider;
use Uhifadhi\Devkit\Tests\Unit\Console\Fixtures\FakePackageIntrospector;

final class CommandInventoryTest extends TestCase
{
    public function testItLeadsWithDevkitsOwnHeroCommand(): void
    {
        $catalogue = new CommandInventory([], [], new FakePackageIntrospector())->catalogue();

        self::assertSame('fixtures:seed', $catalogue->hero()->name);
        self::assertSame(CommandKind::SeedContent, $catalogue->hero()->kind);
        self::assertSame('devkit', $catalogue->groups[0]->label, 'devkit leads the assembled list.');
    }

    public function testItGroupsEachContributionUnderTheModuleThatShippedIt(): void
    {
        $patrolContent = new RecordingContentProvider('patrol');
        $incidentContent = new RecordingContentProvider('incident');
        $incidentCommand = new RecordingCommandProvider();

        $packages = new FakePackageIntrospector();
        $packages->place($patrolContent, ResolvedPackage::of('uhifadhi/patrol-module', '0.5.2'));
        $packages->place($incidentContent, ResolvedPackage::of('uhifadhi/incident-module', '0.2.2'));
        $packages->place($incidentCommand, ResolvedPackage::of('uhifadhi/incident-module', '0.2.2'));

        $catalogue = new CommandInventory([$patrolContent, $incidentContent], [$incidentCommand], $packages)->catalogue();

        $labels = array_map(static fn ($g) => $g->label, $catalogue->groups);
        self::assertSame(['devkit', 'patrol', 'incident'], $labels, 'One group per contributing module, in contribution order.');

        $incident = $catalogue->groups[2];
        self::assertSame(2, $incident->count(), 'A module contributing content AND a command lands both in one group.');
        self::assertSame('uhifadhi/incident-module', $incident->package?->name);
    }

    public function testItCountsTheAssembledListTheWayTheSurfacesHeadIt(): void
    {
        $patrolContent = new RecordingContentProvider('patrol');
        $incidentContent = new RecordingContentProvider('incident');
        $command = new RecordingCommandProvider();

        $packages = new FakePackageIntrospector();
        $packages->place($patrolContent, ResolvedPackage::of('uhifadhi/patrol-module', '0.5.2'));
        $packages->place($incidentContent, ResolvedPackage::of('uhifadhi/incident-module', '0.2.2'));
        $packages->place($command, ResolvedPackage::of('uhifadhi/incident-module', '0.2.2'));

        $catalogue = new CommandInventory([$patrolContent, $incidentContent], [$command], $packages)->catalogue();

        self::assertSame(4, $catalogue->total(), 'hero + two seed loaders + one command.');
        self::assertSame(3, $catalogue->seedContentCount(), 'hero + two content providers.');
        self::assertSame(0, $catalogue->deprecatedCount(), 'No contract carries a deprecation signal — a flagged gap, always 0 in v1.');
        self::assertSame(3, $catalogue->contributorCount(), 'devkit, patrol and incident each contribute at least one.');
    }

    public function testContributingGroupsExcludeDevkitsOwn(): void
    {
        $content = new RecordingContentProvider('patrol');
        $packages = new FakePackageIntrospector();
        $packages->place($content, ResolvedPackage::of('uhifadhi/patrol-module', '0.5.2'));

        $catalogue = new CommandInventory([$content], [], $packages)->catalogue();

        $labels = array_map(static fn ($g) => $g->label, $catalogue->contributingGroups());
        self::assertSame(['patrol'], $labels, 'The home surface lists the modules beneath the hero, not devkit again.');
    }
}
