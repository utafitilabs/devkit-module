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
use Uhifadhi\Bundle\AreaBundle\Overview\AttentionProviderInterface;
use Uhifadhi\Bundle\AreaBundle\Overview\MapLayerProviderInterface;
use Uhifadhi\Bundle\AreaBundle\Overview\NowTileProviderInterface;
use Uhifadhi\Bundle\AreaBundle\Overview\OverviewContributorInterface;
use Uhifadhi\Bundle\AreaBundle\Overview\OverviewCopyProviderInterface;
use Uhifadhi\Bundle\AreaBundle\Overview\PulseProviderInterface;
use Uhifadhi\Bundle\RegistryBundle\RegistryBundle;
use Uhifadhi\Bundle\ShellBundle\Widget\Registry\WidgetSurfaceInterface;
use Uhifadhi\Contracts\Kpi\DepartmentKpiProviderInterface;
use Uhifadhi\Devkit\Console\Package\ResolvedPackage;
use Uhifadhi\Devkit\Console\Wiring\ContributionPoint;
use Uhifadhi\Devkit\Console\Wiring\ContributionPointInspector;
use Uhifadhi\Devkit\Tests\Integration\Console\Fixtures\FixtureModuleProvider;
use Uhifadhi\Devkit\Tests\Integration\Fixtures\RecordingContentProvider;
use Uhifadhi\Devkit\Tests\Unit\Console\Fixtures\FakePackageIntrospector;
use Uhifadhi\Devkit\UhifadhiDevkitBundle;

final class ContributionPointInspectorTest extends TestCase
{
    public function testItEnumeratesEveryKnownPointIncludingTheEmptyOnes(): void
    {
        $inspector = $this->inspector();

        $tags = array_map(static fn (ContributionPoint $point): string => $point->tag, $inspector->points());
        self::assertSame(array_keys(ContributionPointInspector::CONTRIBUTION_POINTS), $tags, 'Every defined contribution point is listed, empty ones included.');

        $kpi = $this->point($inspector, 'uhifadhi.department_kpi');
        self::assertSame(0, $kpi->count(), 'A point nothing has wired into reads zero, honestly.');
    }

    /**
     * THE LIST IS THE PLATFORM'S, NOT THE CONSOLE'S. Every contribution point a
     * module can register on is published as a `TAG` constant on the interface a
     * module implements to reach it — that is where a module reads the string
     * from, so it is where the console has to read it from too.
     *
     * A tag in this list that the platform does not publish draws a row nobody
     * can ever contribute to; a tag the platform publishes and this list omits
     * hides a whole contribution point from the surface built to show them. Both
     * are silent, which is why they are asserted rather than reviewed.
     */
    public function testTheKnownPointsAreTheTagsThePlatformPublishes(): void
    {
        $published = array_map(
            static fn (string $interface): string => \constant($interface.'::TAG'),
            [
                WidgetSurfaceInterface::class,
                DepartmentKpiProviderInterface::class,
                OverviewContributorInterface::class,
                NowTileProviderInterface::class,
                AttentionProviderInterface::class,
                PulseProviderInterface::class,
                OverviewCopyProviderInterface::class,
                MapLayerProviderInterface::class,
            ],
        );

        // The module tag and devkit's own two carry no contribution interface —
        // a module registers itself on the first, and the other two are the
        // contracts this package collects.
        $published[] = RegistryBundle::MODULE_TAG;
        $published[] = UhifadhiDevkitBundle::CONTENT_PROVIDER_TAG;
        $published[] = UhifadhiDevkitBundle::COMMAND_PROVIDER_TAG;

        sort($published);
        $known = array_keys(ContributionPointInspector::CONTRIBUTION_POINTS);
        sort($known);

        self::assertSame($published, $known);
    }

    public function testItCollectsContributorsAndResolvesTheirModule(): void
    {
        $module = $this->point($this->inspector(), 'uhifadhi.module');

        self::assertSame(2, $module->count());
        self::assertSame('FixtureModuleProvider', $module->contributors[0]->shortClass());
        self::assertSame('patrol', $module->contributors[0]->package?->shortName);
        self::assertSame('incident', $module->contributors[1]->package?->shortName);
    }

    public function testItSummarisesTheWiringForTheHeadline(): void
    {
        $inspector = $this->inspector();

        self::assertSame(\count(ContributionPointInspector::CONTRIBUTION_POINTS), $inspector->pointCount());
        self::assertSame(3, $inspector->providerCount(), 'two on the module tag, one seed-content provider.');
        self::assertSame(2, $inspector->contributorCount(), 'patrol and incident.');
        self::assertSame(['tag' => 'uhifadhi.module', 'count' => 2], $inspector->widest());
    }

    private function inspector(): ContributionPointInspector
    {
        $packages = new FakePackageIntrospector(ownersByClass: [
            FixtureModuleProvider::class => ResolvedPackage::of('uhifadhi/patrol-module', '0.5.2'),
            RecordingContentProvider::class => ResolvedPackage::of('uhifadhi/incident-module', '0.2.2'),
        ]);

        // The module tag carries two FixtureModuleProvider registrations (patrol
        // and incident, same class, different modules); one seed-content provider.
        // A pass hands the inspector class names, so both module registrations
        // read as the same class — the count is what matters.
        $collected = [
            'uhifadhi.module' => [FixtureModuleProvider::class, RecordingContentProvider::class],
            'uhifadhi.devkit.content_provider' => [RecordingContentProvider::class],
        ];

        return new ContributionPointInspector($collected, $packages);
    }

    private function point(ContributionPointInspector $inspector, string $tag): ContributionPoint
    {
        foreach ($inspector->points() as $point) {
            if ($point->tag === $tag) {
                return $point;
            }
        }

        self::fail(\sprintf('No contribution point "%s".', $tag));
    }
}
