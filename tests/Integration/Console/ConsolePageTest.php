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

        self::assertGreaterThan(0, $crawler->filter('.dk-grp')->count(), 'Commands are grouped by contributing module.');
        $text = $crawler->filter('div.pgbody')->text();
        self::assertStringContainsString('fixtures:seed', $text);
        self::assertStringContainsString('devkit:test:echo', $text, 'A tagged command provider becomes a listed command.');
        self::assertStringContainsString('deprecat', $text, 'The deprecation gap is flagged on the surface.');
    }

    public function testTheModulesSurfaceIsTheRegistryWithADeferredPerAreaNote(): void
    {
        $crawler = $this->get('/_devkit/modules');

        self::assertCount(1, $crawler->filter('.factband'), 'The fleet facts head the page.');
        self::assertGreaterThan(0, $crawler->filter('table.tbl .dk-ver')->count(), 'Every module row shows a version.');
        self::assertStringContainsString('deferred', $crawler->filter('div.pgbody')->text(), 'The per-area matrix is flagged deferred, not faked.');
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

    private function get(string $path): Crawler
    {
        $response = $this->handle($path);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), \sprintf('GET %s did not serve.', $path));

        return new Crawler((string) $response->getContent());
    }

    private function handle(string $path, bool $debug = true): Response
    {
        $kernel = new ConsoleTestKernel('test', $debug);
        $kernel->boot();

        return $kernel->handle(Request::create($path), catch: true);
    }
}
