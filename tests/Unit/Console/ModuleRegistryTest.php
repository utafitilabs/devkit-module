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
use Uhifadhi\Devkit\Console\Module\CoreState;
use Uhifadhi\Devkit\Console\Module\ModuleReach;
use Uhifadhi\Devkit\Console\Module\ModuleRegistry;
use Uhifadhi\Devkit\Console\Module\ModuleRow;
use Uhifadhi\Devkit\Console\Package\ResolvedPackage;
use Uhifadhi\Devkit\Tests\Integration\Console\Fixtures\FixtureConcernSource;
use Uhifadhi\Devkit\Tests\Integration\Console\Fixtures\FixtureModuleProvider;
use Uhifadhi\Devkit\Tests\Unit\Console\Fixtures\FakePackageIntrospector;
use Uhifadhi\Devkit\Tests\Unit\Console\Fixtures\FakeRouter;

final class ModuleRegistryTest extends TestCase
{
    public function testItBuildsARowForEveryPackageAndReadsTheCore(): void
    {
        $view = $this->registry()->view();

        self::assertSame('v0.5.1', $view->currentCore);
        self::assertSame(4, $view->installedCount());

        self::assertSame(CoreState::OnCore, $this->row($view->rows, 'area')->coreState, '^0.5 admits 0.5.1.');
        self::assertSame(CoreState::BehindCore, $this->row($view->rows, 'patrol')->coreState, '^0.4 does not admit 0.5.1.');
        self::assertSame(CoreState::NotApplicable, $this->row($view->rows, 'storage')->coreState, 'storage pins no core constraint.');
        self::assertSame(CoreState::NotApplicable, $this->row($view->rows, 'uhifadhi')->coreState, 'the core is not measured against itself.');
    }

    public function testItClassifiesReachWithoutADatabase(): void
    {
        $view = $this->registry()->view();

        self::assertSame(ModuleReach::HostWide, $this->row($view->rows, 'area')->reach, 'a base module is on everywhere.');
        self::assertSame(ModuleReach::PerArea, $this->row($view->rows, 'patrol')->reach, 'an installable module is per-area.');
        self::assertSame(ModuleReach::HostWide, $this->row($view->rows, 'storage')->reach, 'infrastructure with no provider is host-wide.');
        self::assertSame(ModuleReach::TheCore, $this->row($view->rows, 'uhifadhi')->reach);
    }

    public function testItCountsGrantsAndStampedRoutesFromTheSeamsAndRouter(): void
    {
        $view = $this->registry()->view();

        self::assertSame(2, $this->row($view->rows, 'area')->grants);
        self::assertSame(9, $this->row($view->rows, 'area')->routes);
        self::assertSame(1, $this->row($view->rows, 'patrol')->grants);
        self::assertSame(8, $this->row($view->rows, 'patrol')->routes);
        self::assertSame(0, $this->row($view->rows, 'storage')->grants, 'infrastructure declares none through the registry.');

        self::assertSame(1, $view->onCoreCount());
        self::assertSame(2, $view->pinsCoreCount(), 'area and patrol pin the core; shell and the core itself do not.');
        self::assertSame(3, $view->totalGrants());
        self::assertSame(17, $view->totalRoutes());
    }

    private function registry(): ModuleRegistry
    {
        $area = new FixtureModuleProvider('area', 'Area', true);
        $patrol = new FixtureModuleProvider('patrol', 'Patrol');

        $areaPackage = ResolvedPackage::of('uhifadhi/area-module', '0.11.1');
        $patrolPackage = ResolvedPackage::of('uhifadhi/patrol-module', '0.2.2');

        $packages = new FakePackageIntrospector(
            fleet: [
                ResolvedPackage::of('uhifadhi/uhifadhi', 'v0.5.1'),
                $areaPackage,
                $patrolPackage,
                ResolvedPackage::of('uhifadhi/storage-module', '0.8.0'),
            ],
            requirements: [
                'uhifadhi/area-module' => ['uhifadhi/uhifadhi' => '^0.5'],
                'uhifadhi/patrol-module' => ['uhifadhi/uhifadhi' => '^0.4'],
                'uhifadhi/storage-module' => [],
            ],
        );
        $packages->place($area, $areaPackage);
        $packages->place($patrol, $patrolPackage);

        return new ModuleRegistry(
            [$area, $patrol],
            $packages,
            new FakeRouter(['area' => 9, 'patrol' => 8]),
            [new FixtureConcernSource('area', 2), new FixtureConcernSource('patrol', 1)],
        );
    }

    /**
     * @param list<ModuleRow> $rows
     */
    private function row(array $rows, string $shortName): ModuleRow
    {
        foreach ($rows as $row) {
            if ($row->package->shortName === $shortName) {
                return $row;
            }
        }

        self::fail(\sprintf('No registry row for "%s".', $shortName));
    }
}
