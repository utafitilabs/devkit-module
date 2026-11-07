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

namespace Uhifadhi\Devkit\Tests\Integration\Console;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * THE FOUR SURFACES AND THEIR HOME, served the way a dev install serves them:
 * over HTTP, through the console's controller, in the shell frame, on a kernel
 * that plays the always-installed modules with tagged fixture providers.
 *
 * Each surface must render in the frame (sidebar, main column, the one `.page`
 * the frame owns), carry the four console tabs, and show the data its
 * introspection service assembled from the fixtures — not a placeholder.
 */
final class ConsolePageTest extends TestCase
{
    private const array PATHS = [
        '/_devkit',
        '/_devkit/commands',
        '/_devkit/modules',
        '/_devkit/doctor',
        '/_devkit/wiring',
    ];

    /**
     * Every surface renders in the shell frame with the console's own tabs.
     */
    public function testEverySurfaceRendersInTheFrameWithTheConsoleTabs(): void
    {
        foreach (self::PATHS as $path) {
            $crawler = $this->get($path);

            self::assertCount(1, $crawler->filter('aside.side'), $path.': the console wears the shell frame.');
            self::assertCount(1, $crawler->filter('main.main div.page'), $path.': the frame owns .page.');
            self::assertCount(4, $crawler->filter('div.atabs a'), $path.': Commands · Modules · Doctor · Wiring.');
            self::assertStringContainsString('dev-only', $crawler->filter('div.pgact')->text(), $path.': the dev-only chip.');
        }
    }

    public function testTheHomeLeadsWithBothCommandsAndTheModuleInspector(): void
    {
        $crawler = $this->get('/_devkit');

        self::assertStringContainsString('fixtures:seed', $crawler->filter('.dk-hero')->text(), 'The hero is the one command that stands a park up.');
        self::assertGreaterThan(0, $crawler->filter('table.tbl .dk-name')->count(), 'The module registry leads here too.');
        self::assertCount(1, $crawler->filter('table.dk-mx'), 'The live compatibility grid is on the home.');
        self::assertGreaterThanOrEqual(4, $crawler->filter('.grid.g4 .kpi')->count(), 'Four at-a-glance KPIs.');
    }

    public function testTheCommandsSurfaceListsTheAssembledFleet(): void
    {
        $crawler = $this->get('/_devkit/commands');

        $text = $crawler->filter('div.pgbody')->text();
        self::assertStringContainsString('fixtures:seed', $text);
        self::assertStringContainsString('devkit:test:echo', $text, 'A tagged command provider becomes a listed command.');
        self::assertStringContainsString('deprecat', $text, 'The deprecation gap is flagged on the surface.');
    }

    /**
     * ONE REGISTER, NO GROUP HEADERS: the contributing module is a chip in the
     * house bar and the `.sub` line under each command's name.
     */
    public function testTheCommandsAreOneHouseTableUnderTheHouseBar(): void
    {
        $crawler = $this->get('/_devkit/commands');

        self::assertCount(0, $crawler->filter('.dk-grp, .dk-cmd'), 'no group header and no card row is left');
        $card = $crawler->filter('.c')->reduce(static fn (Crawler $c): bool => $c->filter('table.tbl')->count() > 0);
        self::assertCount(1, $card);
        self::assertCount(2, $card->filter('form.lfilt[method=get] > details.i-dd > summary.mchip.i-ddt'));
        self::assertSame(['module', 'kind'], $card->filter('form.lfilt .i-ddhead')->each(static fn (Crawler $h): string => trim($h->text())));
        self::assertCount(1, $card->filter('form.lfilt .lsearch input.fld[name=q]'));

        $rows = $card->filter('table.tbl tbody tr');
        self::assertGreaterThanOrEqual(2, $rows->count());
        $rows->each(static function (Crawler $row): void {
            self::assertCount(1, $row->filter('td')->first()->filter('.sub'), 'the module is the row\'s sub-line');
            self::assertCount(1, $row->filter('td.door .dk-run[disabled]'));
        });
        self::assertSame(
            'every module · '.$rows->count(),
            trim($card->filter('form.lfilt details.i-dd')->first()->filter('summary .i-ddval')->text()),
        );
    }

    public function testTheCommandsRegisterEndsInTheShellFoot(): void
    {
        $crawler = $this->get('/_devkit/commands?kind=command');

        $foot = $crawler->filter('.c > .rdf-foot');
        self::assertCount(1, $foot);
        $count = $crawler->filter('table.tbl tbody tr')->count();
        self::assertStringStartsWith($count.' command'.(1 === $count ? '' : 's').' · 20 per page', trim($foot->filter('span')->first()->text()));
        self::assertSame('page 1 of 1', trim($foot->filter('.rdf-page')->text()));
        foreach ($foot->filter('details.rdf-size a.i-ddopt')->each(static fn (Crawler $a): string => (string) $a->attr('href')) as $href) {
            self::assertStringStartsWith('/_devkit/commands?', $href);
            self::assertStringContainsString('kind=command', $href, 'a new size keeps the filter');
        }

        self::assertStringContainsString('all on one page', $this->get('/_devkit/commands?per=all')->filter('.rdf-foot')->text());
    }

    public function testTheKindChipAndTheSearchNarrowTheCommands(): void
    {
        $commands = $this->get('/_devkit/commands?kind=command')->filter('table.tbl tbody')->text();

        self::assertStringContainsString('devkit:test:echo', $commands);
        self::assertStringNotContainsString('fixtures:seed', $commands, 'fixtures:seed is seed content, not a command');

        $searched = $this->get('/_devkit/commands?q=echo')->filter('table.tbl tbody tr');
        self::assertCount(1, $searched);
        self::assertStringContainsString('devkit:test:echo', $searched->text());
    }

    public function testTheModulesSurfaceCarriesNoStripAndSaysWhyThereIsNoPerAreaMatrix(): void
    {
        $crawler = $this->get('/_devkit/modules');

        self::assertCount(0, $crawler->filter('.factband'), 'A list page carries no strip.');
        self::assertGreaterThan(0, $crawler->filter('table.tbl .dk-ver')->count(), 'Every module row shows a version.');
        self::assertCount(1, $crawler->filter('table.tbl'), 'Without a ledger there is no per-area table.');
        self::assertStringContainsString('no per-area ledger', $crawler->filter('div.pgbody')->text());
    }

    public function testTheRegistrySitsUnderTheHouseBarAndOverTheHouseFoot(): void
    {
        $crawler = $this->get('/_devkit/modules');

        $card = $crawler->filter('.c')->reduce(static fn (Crawler $c): bool => $c->filter('table.tbl .dk-ver')->count() > 0)->first();
        self::assertCount(1, $card->filter('form.lfilt[method=get] details.i-dd summary.mchip'));
        self::assertCount(1, $card->filter('form.lfilt .lsearch input.fld[name=q]'));
        self::assertStringStartsWith('2 packages', trim($card->filter('.rdf-foot')->text()));
        self::assertSame('page 1 of 1', trim($card->filter('.rdf-foot .rdf-page')->text()));
        self::assertSame(['permissions', 'routes'], $card->filter('th.num')->each(static fn (Crawler $th): string => trim($th->text())));
    }

    public function testTheCoreChipNarrowsTheRegistry(): void
    {
        $crawler = $this->get('/_devkit/modules?core=na');

        self::assertSame(['uhifadhi'], $crawler->filter('table.tbl .dk-name')->each(static fn (Crawler $n): string => trim($n->text())));
        self::assertStringContainsString('is the core', $crawler->filter('table.tbl')->text());
    }

    public function testThePerAreaMatrixIsAHouseTableReadFromTheLedger(): void
    {
        $crawler = $this->get('/_devkit/modules', ConsoleTestKernel::WITH_AREAS);

        $matrix = $crawler->filter('table.tbl.dk-areas');
        self::assertCount(1, $matrix);
        self::assertSame(['module', 'North Block', 'South Block'], $matrix->filter('thead th')->each(static fn (Crawler $th): string => trim($th->text())));
        self::assertCount(0, $matrix->filter('tr.sxgrp'), 'No band rows: the grouping is the order and the chip.');

        $rows = $matrix->filter('tbody tr');
        self::assertStringContainsString('Patrols', $rows->eq(0)->text(), 'Modules switched on per area come first.');
        self::assertSame(['pass', 'na'], $rows->eq(0)->filter('.dk-cell')->each(static fn (Crawler $c): string => str_replace('dk-cell ', '', (string) $c->attr('class'))));
        self::assertStringContainsString('per area', $rows->eq(0)->text());
        self::assertStringContainsString('every area', $rows->eq(1)->text());

        $card = $crawler->filter('.c')->reduce(static fn (Crawler $c): bool => $c->filter('table.dk-areas')->count() > 0)->first();
        self::assertCount(1, $card->filter('form.lfilt'));
        self::assertCount(1, $card->filter('.rdf-foot'));
        self::assertStringContainsString('on in 1 of 2 areas', $crawler->filter('div.pgbody')->text());
    }

    public function testTheReachChipNarrowsTheMatrix(): void
    {
        $crawler = $this->get('/_devkit/modules?reach=every', ConsoleTestKernel::WITH_AREAS);

        $text = $crawler->filter('table.dk-areas tbody')->text();
        self::assertStringNotContainsString('Patrols', $text);
        self::assertStringContainsString('Areas', $text);
    }

    public function testTheDoctorSurfaceComputesTheMatrixAndFlagsTheDeferredChecks(): void
    {
        $crawler = $this->get('/_devkit/doctor');

        self::assertCount(1, $crawler->filter('table.dk-mx'), 'The compatibility matrix.');
        self::assertSame(6, $crawler->filter('table.dk-mx tr:first-child th')->count(), 'module + five checks.');
        $text = $crawler->filter('div.pgbody')->text();
        self::assertStringContainsString('extends shell', $text, 'Deferred checks are named, not hidden.');
        self::assertStringContainsString('geom guards', $text);
    }

    public function testTheWiringSurfaceInspectsEveryContributionPoint(): void
    {
        $crawler = $this->get('/_devkit/wiring');

        self::assertGreaterThan(0, $crawler->filter('.dk-point')->count());
        $text = $crawler->filter('div.pgbody')->text();
        self::assertStringContainsString('uhifadhi.module', $text, 'A module registering itself is inspected.');
        self::assertStringContainsString('uhifadhi.devkit.content_provider', $text, "devkit's own tags are inspected too.");
    }

    /**
     * THE DEV-ONLY FIREWALL, at the controller: outside a debug kernel the
     * console is a 404, not a console. (Composer's require-dev is the other
     * firewall, and it keeps this class off a production build entirely.).
     */
    public function testTheConsoleIs404WhenTheKernelIsNotInDebug(): void
    {
        $response = $this->handle('/_devkit', debug: false);

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    private function get(string $path, string $environment = 'test'): Crawler
    {
        $response = $this->handle($path, true, $environment);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), \sprintf('GET %s did not serve.', $path));

        return new Crawler((string) $response->getContent());
    }

    private function handle(string $path, bool $debug = true, string $environment = 'test'): Response
    {
        $kernel = new ConsoleTestKernel($environment, $debug);
        $kernel->boot();

        return $kernel->handle(Request::create($path), catch: true);
    }
}
