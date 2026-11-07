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

use Composer\Semver\Semver;
use Symfony\Component\Routing\RouterInterface;
use Uhifadhi\Bundle\RegistryBundle\RegistryBundle;
use Uhifadhi\Contracts\Access\ConcernSourceInterface;
use Uhifadhi\Contracts\ModuleProviderInterface;
use Uhifadhi\Devkit\Console\Package\PackageIntrospector;
use Uhifadhi\Devkit\Console\Package\ResolvedPackage;

/**
 * THE INSTALLED FLEET AS ONE REGISTER — every uhifadhi package with its version,
 * whether it pins the current core, how much it declares, and where it reaches.
 *
 * The rows are the Composer fleet (so an infrastructure package that carries no
 * module provider is still listed), enriched from the module tag where a
 * provider is registered: the grants its concerns declare, and its stamped routes
 * counted off the router. The core question is answered by comparing each
 * package's own `uhifadhi/uhifadhi` constraint against the core
 * version actually installed — {@see Semver}, not a string
 * match, so `^0.4` reads as behind `0.5.1` and `^0.5` reads as on it.
 *
 * WHERE A MODULE IS ON. Its reach is classified from the tags alone
 * ({@see ModuleReach}); the exact per-area state ("on in 1 / 4") is the
 * registry's ledger, read through {@see AreaStatesInterface} where the
 * installation keeps one, and absent where it does not.
 */
final class ModuleRegistry
{
    private const string CORE_PACKAGE = 'uhifadhi/uhifadhi';

    /**
     * @param iterable<ModuleProviderInterface> $providers every module tagged with uhifadhi.module
     * @param iterable<ConcernSourceInterface>  $concerns  every declaration tagged with uhifadhi.access.concerns
     */
    public function __construct(
        private readonly iterable $providers,
        private readonly PackageIntrospector $packages,
        private readonly RouterInterface $router,
        private readonly iterable $concerns = [],
        private readonly ?AreaStatesInterface $areaStates = null,
    ) {
    }

    /**
     * The registry rows a reader narrowed to: one core state (`on`, `behind`,
     * `na`) and a name that contains what they typed.
     *
     * @return list<ModuleRow>
     */
    public function listed(ModuleRegistryView $view, ?string $core, ?string $query): array
    {
        $query = trim((string) $query);

        return array_values(array_filter($view->rows, static fn (ModuleRow $row): bool => (null === $core || $core === self::coreKey($row->coreState))
            && ('' === $query || false !== mb_stripos($row->package->shortName.' '.$row->package->name, $query))));
    }

    /**
     * How many registry rows each core state has.
     *
     * @return array<string, int>
     */
    public function coreCounts(ModuleRegistryView $view): array
    {
        $counts = [];
        foreach ($view->rows as $row) {
            $key = self::coreKey($row->coreState);
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        return $counts;
    }

    /** What the core chip calls a state. */
    public static function coreKey(CoreState $state): string
    {
        return match ($state) {
            CoreState::OnCore => 'on',
            CoreState::BehindCore => 'behind',
            CoreState::NotApplicable => 'na',
        };
    }

    /**
     * THE PER-AREA MODULE STATE, or null where the installation has no
     * per-area ledger to read. One row per module the module tag collects,
     * read off the ledger, and one per package that is no module of an area
     * — on in every area by definition; the core itself is no row.
     */
    public function areaMatrix(): ?AreaMatrix
    {
        if (null === $this->areaStates) {
            return null;
        }

        $areas = $this->areaStates->areas();
        $switchedOn = $this->areaStates->switchedOn();

        $rows = [];
        $owned = [];
        foreach ($this->providers as $provider) {
            $package = $this->packages->ownerOf($provider);
            if (null !== $package) {
                $owned[$package->name] = true;
            }
            $on = [];
            foreach (array_keys($areas) as $uuid) {
                $on[$uuid] = \in_array($provider->slug(), $switchedOn[$uuid] ?? [], true);
            }
            $rows[] = new AreaMatrixRow($provider->slug(), $provider->name(), $package->version ?? '', !$provider->base(), $on);
        }

        foreach ($this->packages->fleet() as $package) {
            if (self::CORE_PACKAGE === $package->name || isset($owned[$package->name])) {
                continue;
            }
            $rows[] = new AreaMatrixRow(null, $package->shortName, $package->version, false, array_fill_keys(array_keys($areas), true));
        }

        usort($rows, static fn (AreaMatrixRow $a, AreaMatrixRow $b): int => [$b->perArea, $a->label] <=> [$a->perArea, $b->label]);

        return new AreaMatrix($areas, $rows);
    }

    /**
     * HOW MANY GRANTS A MODULE PUTS ON THE POSITIONS PAGE — its declared
     * concerns crossed with the verbs each one supports. A concern names the
     * module that enforces it, which is what makes the count answerable from
     * a declaration nobody had to register twice.
     */
    private function grantsOf(string $slug): int
    {
        $pairs = 0;
        foreach ($this->concerns as $source) {
            foreach ($source->concerns() as $concern) {
                if ($slug === $concern->moduleSlug()) {
                    $pairs += \count($concern->verbs());
                }
            }
        }

        return $pairs;
    }

    public function view(): ModuleRegistryView
    {
        $contracts = $this->packages->package(self::CORE_PACKAGE);
        $currentCore = null !== $contracts ? $contracts->version : 'unknown';
        $providersByPackage = $this->providersByPackage();

        $rows = [];
        foreach ($this->packages->fleet() as $package) {
            $provider = $providersByPackage[$package->name] ?? null;
            $rows[] = $this->row($package, $provider, $currentCore);
        }

        return new ModuleRegistryView($rows, $currentCore);
    }

    private function row(ResolvedPackage $package, ?ModuleProviderInterface $provider, string $currentCore): ModuleRow
    {
        $isCore = self::CORE_PACKAGE === $package->name;
        $constraint = $isCore
            ? null
            : ($this->packages->requirements($package->name)[self::CORE_PACKAGE] ?? null);

        return new ModuleRow(
            package: $package,
            coreConstraint: $constraint,
            coreState: $this->coreState($isCore, $constraint, $currentCore),
            grants: null === $provider ? 0 : $this->grantsOf($provider->slug()),
            routes: null === $provider ? 0 : $this->routeCount($provider->slug()),
            reach: $this->reach($isCore, $provider),
            slug: $provider?->slug(),
        );
    }

    private function coreState(bool $isCore, ?string $constraint, string $currentCore): CoreState
    {
        if ($isCore || null === $constraint) {
            return CoreState::NotApplicable;
        }

        return Semver::satisfies($currentCore, $constraint) ? CoreState::OnCore : CoreState::BehindCore;
    }

    private function reach(bool $isCore, ?ModuleProviderInterface $provider): ModuleReach
    {
        if ($isCore) {
            return ModuleReach::TheCore;
        }

        // Infrastructure (no provider) and base modules are on everywhere; an
        // installable capability module is switched on per area.
        if (null === $provider || $provider->base()) {
            return ModuleReach::HostWide;
        }

        return ModuleReach::PerArea;
    }

    private function routeCount(string $slug): int
    {
        $count = 0;
        foreach ($this->router->getRouteCollection() as $route) {
            if ($slug === $route->getDefault(RegistryBundle::MODULE_ROUTE_DEFAULT)) {
                ++$count;
            }
        }

        return $count;
    }

    /**
     * @return array<string, ModuleProviderInterface> package name => its provider
     */
    private function providersByPackage(): array
    {
        $map = [];
        foreach ($this->providers as $provider) {
            $package = $this->packages->ownerOf($provider);
            if (null !== $package && !isset($map[$package->name])) {
                $map[$package->name] = $provider;
            }
        }

        return $map;
    }
}
