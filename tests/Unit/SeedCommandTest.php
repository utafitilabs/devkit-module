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

namespace Uhifadhi\Devkit\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Uhifadhi\Devkit\Command\SeedCommand;
use Uhifadhi\Devkit\Content\ContentOrderer;
use Uhifadhi\Devkit\Tests\Fixtures\FakeContentProvider;

/**
 * fixtures:seed collects the content providers, orders them on dependsOn(), and
 * calls load() in that order — printing each slice and reporting a malformed
 * graph as a failed command rather than a stack trace.
 */
final class SeedCommandTest extends TestCase
{
    /**
     * @param iterable<\Uhifadhi\Contracts\Devkit\ContentProviderInterface> $providers
     */
    private function tester(iterable $providers): CommandTester
    {
        return new CommandTester(new SeedCommand($providers, new ContentOrderer()));
    }

    public function testItRunsLoadInDependencyOrder(): void
    {
        $loaded = [];

        $tester = $this->tester([
            new FakeContentProvider('incident', ['area', 'patrol'], static function () use (&$loaded): void {
                $loaded[] = 'incident';
            }),
            new FakeContentProvider('patrol', ['area'], static function () use (&$loaded): void {
                $loaded[] = 'patrol';
            }),
            new FakeContentProvider('area', [], static function () use (&$loaded): void {
                $loaded[] = 'area';
            }),
        ]);

        $exit = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertSame(['area', 'patrol', 'incident'], $loaded);
    }

    public function testItPrintsEachSliceLabelAndDescription(): void
    {
        $tester = $this->tester([
            new FakeContentProvider('area', [], null, 'Areas', 'The seed protected areas.'),
        ]);

        $tester->execute([]);
        $output = $tester->getDisplay();

        self::assertStringContainsString('Areas', $output);
        self::assertStringContainsString('The seed protected areas.', $output);
    }

    public function testAnInstallationWithNoProvidersSeedsNothingCleanly(): void
    {
        $tester = $this->tester([]);

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertStringContainsString('No seed-content providers are installed', $tester->getDisplay());
    }

    public function testACycleFailsTheCommandWithAClearError(): void
    {
        $tester = $this->tester([
            new FakeContentProvider('a', ['b']),
            new FakeContentProvider('b', ['a']),
        ]);

        $exit = $tester->execute([]);

        self::assertSame(Command::FAILURE, $exit);
        self::assertStringContainsString('dependency cycle', $tester->getDisplay());
    }
}
