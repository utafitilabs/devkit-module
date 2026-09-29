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

namespace Uhifadhi\Devkit\Console\Wiring;

use Uhifadhi\Devkit\Console\Package\PackageIntrospector;

/**
 * THE TAG INSPECTOR — for each contribution point the platform defines, who is
 * registered and how many collected.
 *
 * The points are known: a module's registration of itself, the map layers, the
 * widget surfaces, the department KPIs, the five ways an area's overview is
 * composed, and devkit's own two. Which services carry each tag is not known
 * until the container is compiled, so it is collected there
 * ({@see \Uhifadhi\Devkit\DependencyInjection\Compiler\CollectContributionPointsPass})
 * and handed in as a class-name map — this service turns that map into the rows
 * a surface renders, resolving each contributor back to the module that shipped
 * it.
 *
 * It enumerates every KNOWN point, including the ones nothing has wired into
 * yet: a point at zero is the honest state of a fresh installation, and hiding
 * it would make the console lie about what the platform offers.
 */
final class ContributionPointInspector
{
    /**
     * The platform's contribution points, in the order the Wiring surface lays
     * them out, each with the sentence the console explains it by. The keys are
     * the tag strings the compiler pass collects.
     *
     * EVERY ONE OF THEM IS PUBLISHED AS A `TAG` CONSTANT on the interface a
     * module implements to reach it, which is where a module reads the string
     * from — so a copy here that drifts draws a row nobody can contribute to, or
     * hides a point that exists. The two are held together by a test rather than
     * by review.
     */
    public const array CONTRIBUTION_POINTS = [
        'uhifadhi.module' => 'Every installed module registers itself here so the host can enumerate the fleet.',
        'uhifadhi.map.layer' => 'Map layers a module draws on the shared plate, each with its own legend.',
        'uhifadhi.department_kpi' => 'Per-department KPI figures a module contributes to the lens.',
        'uhifadhi.widget_surface' => 'Surfaces a widget may be placed on.',
        'uhifadhi.overview.widget_provider' => 'Widgets and their render context, laid out on an area\'s overview.',
        'uhifadhi.overview.now_tile' => '"Right now" tiles in the overview strip.',
        'uhifadhi.overview.attention' => 'Items a module puts in the overview\'s attention list.',
        'uhifadhi.overview.pulse' => 'Events a module contributes to the activity feed.',
        'uhifadhi.overview.copy' => 'Copy fragments a module writes into a named slot.',
        'uhifadhi.devkit.content_provider' => 'Seed-content slices devkit seeds through fixtures:seed, in dependency order.',
        'uhifadhi.devkit.command_provider' => 'Dev/maintenance commands devkit registers as real console commands in a dev install.',
    ];

    /**
     * @param array<string, list<string>> $collected tag => the classes registered on it
     */
    public function __construct(
        private readonly array $collected,
        private readonly PackageIntrospector $packages,
    ) {
    }

    /**
     * @return list<ContributionPoint>
     */
    public function points(): array
    {
        $points = [];
        foreach (self::CONTRIBUTION_POINTS as $tag => $description) {
            $points[] = new ContributionPoint($tag, $description, $this->contributorsFor($tag));
        }

        return $points;
    }

    public function pointCount(): int
    {
        return \count(self::CONTRIBUTION_POINTS);
    }

    public function providerCount(): int
    {
        return array_sum(array_map(static fn (ContributionPoint $point): int => $point->count(), $this->points()));
    }

    /**
     * How many distinct modules register on at least one point.
     */
    public function contributorCount(): int
    {
        $packages = [];
        foreach ($this->points() as $point) {
            foreach ($point->contributors as $contributor) {
                if (null !== $contributor->package) {
                    $packages[$contributor->package->name] = true;
                }
            }
        }

        return \count($packages);
    }

    /**
     * The busiest point and how many it carries — the "widest" headline.
     *
     * @return array{tag: string, count: int}
     */
    public function widest(): array
    {
        $widest = ['tag' => '', 'count' => 0];
        foreach ($this->points() as $point) {
            if ($point->count() > $widest['count']) {
                $widest = ['tag' => $point->tag, 'count' => $point->count()];
            }
        }

        return $widest;
    }

    /**
     * @return list<Contributor>
     */
    private function contributorsFor(string $tag): array
    {
        $contributors = [];
        foreach ($this->collected[$tag] ?? [] as $class) {
            $contributors[] = new Contributor($class, $this->packages->ownerOfClass($class));
        }

        return $contributors;
    }
}
