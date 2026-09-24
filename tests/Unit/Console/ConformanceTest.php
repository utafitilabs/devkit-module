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
use Uhifadhi\Devkit\Console\Doctor\CheckState;
use Uhifadhi\Devkit\Console\Doctor\Conformance;
use Uhifadhi\Devkit\Console\Doctor\ConformanceCheck;
use Uhifadhi\Devkit\Console\Doctor\MatrixRow;
use Uhifadhi\Devkit\Console\Module\ModuleRegistry;
use Uhifadhi\Devkit\Console\Package\ResolvedPackage;
use Uhifadhi\Devkit\Tests\Integration\Console\Fixtures\FixtureModuleProvider;
use Uhifadhi\Devkit\Tests\Unit\Console\Fixtures\FakePackageIntrospector;
use Uhifadhi\Devkit\Tests\Unit\Console\Fixtures\FakeRouter;

final class ConformanceTest extends TestCase
{
    public function testTheMatrixReadsWhatItCanAndDefersWhatItCannot(): void
    {
        $report = $this->conformance()->report();

        // Rows are modules; the core itself takes no row.
        $shortNames = array_map(static fn (MatrixRow $r): string => $r->package->shortName, $report->matrix);
        self::assertSame(['area', 'patrol', 'storage'], $shortNames);

        $patrol = $this->matrixRow($report->matrix, 'patrol');
        self::assertSame(CheckState::Warn, $patrol->cell(ConformanceCheck::PinsCore), 'patrol pins the old core.');
        self::assertSame(CheckState::Fail, $patrol->cell(ConformanceCheck::NoDevMain), 'patrol carries a dev-main marker.');
        self::assertSame(CheckState::Pass, $patrol->cell(ConformanceCheck::RoutesStamped));
        self::assertSame(CheckState::Deferred, $patrol->cell(ConformanceCheck::ExtendsShell), 'not faked green — deferred.');
        self::assertSame(CheckState::Deferred, $patrol->cell(ConformanceCheck::GeomGuards));

        $area = $this->matrixRow($report->matrix, 'area');
        self::assertSame(CheckState::Pass, $area->cell(ConformanceCheck::PinsCore));
        self::assertSame(CheckState::Pass, $area->cell(ConformanceCheck::NoDevMain));
    }

    public function testItCountsWarnFailAndDeferredHonestly(): void
    {
        $report = $this->conformance()->report();

        self::assertSame(1, $report->warnCount());
        self::assertSame(1, $report->failCount());
        self::assertSame(3, $report->computedCheckCount());
        self::assertSame(2, $report->deferredCheckCount(), 'extends-shell and geom-guards are deferred.');
        self::assertSame(1, $report->passingCheckCount(), 'only routes-stamped is clean fleet-wide.');
    }

    public function testItTurnsEachProblemIntoAnActionableFinding(): void
    {
        $report = $this->conformance()->report();

        self::assertCount(2, $report->attention);
        self::assertSame(CheckState::Fail, $report->attention[0]->state, 'the worst finding leads.');

        $titles = implode(' | ', array_map(static fn ($f) => $f->title, $report->attention));
        self::assertStringContainsString('dev-main', $titles);
        self::assertStringContainsString('old core', $titles);

        foreach ($report->attention as $finding) {
            self::assertNotNull($finding->where, 'a finding names the file to open.');
            self::assertStringContainsString('composer.json', (string) $finding->where);
        }

        self::assertNotSame([], $report->passing, 'the clean checks are summarised too.');
        self::assertSame(ConformanceCheck::deferred(), $report->deferred);
    }

    private function conformance(): Conformance
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
                'uhifadhi/patrol-module' => ['uhifadhi/uhifadhi' => '^0.4', 'uhifadhi/storage-module' => '^0.3 || dev-main'],
                'uhifadhi/storage-module' => [],
            ],
        );
        $packages->place($area, $areaPackage);
        $packages->place($patrol, $patrolPackage);

        $registry = new ModuleRegistry([$area, $patrol], $packages, new FakeRouter(['area' => 9, 'patrol' => 8]));

        return new Conformance($registry, $packages);
    }

    /**
     * @param list<MatrixRow> $matrix
     */
    private function matrixRow(array $matrix, string $shortName): MatrixRow
    {
        foreach ($matrix as $row) {
            if ($row->package->shortName === $shortName) {
                return $row;
            }
        }

        self::fail(\sprintf('No matrix row for "%s".', $shortName));
    }
}
