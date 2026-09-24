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

namespace Uhifadhi\Devkit\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * `fleet:gate` through the real container: the command is registered, the
 * configured fleet reaches it, and `--dry-run` prints the plan without creating
 * a project, dropping a database or serving anything.
 *
 * The gate itself is never run from a suite — it installs the whole fleet over
 * the network, in minutes. What is proven here is that it is wired and that its
 * plan is the configured one; the plan's shape is {@see \Uhifadhi\Devkit\Tests\Unit\Fleet\GatePlannerTest}.
 */
final class FleetGateDryRunTest extends KernelTestCase
{
    public function testTheDryRunPrintsTheReleasedPlanAndRunsNoneOfIt(): void
    {
        $tester = $this->gate();
        $exit = $tester->execute(['--dry-run' => true]);

        $display = $tester->getDisplay();

        self::assertSame(0, $exit);
        self::assertStringContainsString('released mode', $display);
        self::assertStringContainsString('composer create-project uhifadhi/skeleton', $display);
        self::assertStringContainsString('php bin/console registry:sync', $display);
        self::assertStringContainsString('composer require uhifadhi/roster-module', $display);
        self::assertStringContainsString('nothing was created, dropped or served', $display);
    }

    public function testTheDryRunNamesTheDatabasesItWouldDrop(): void
    {
        $tester = $this->gate();
        $tester->execute(['--dry-run' => true]);

        self::assertStringContainsString('DATABASE_URL', $tester->getDisplay());
        self::assertStringContainsString('dropped and recreated', $tester->getDisplay());
    }

    /**
     * A private module is opt-in, and it brings a database of its own — nothing
     * an installer ever requires, gated the same way.
     */
    public function testAPrivateModuleIsAddedOnlyWhenItIsAskedFor(): void
    {
        $tester = $this->gate();
        $tester->execute(['--dry-run' => true]);
        self::assertStringNotContainsString('telemetry', $tester->getDisplay());

        $tester = $this->gate();
        $tester->execute(['--dry-run' => true, '--private-module' => ['telemetry']]);
        self::assertStringContainsString('uhifadhi/telemetry-module', $tester->getDisplay());
        self::assertStringContainsString('TELEMETRY_DATABASE_URL', $tester->getDisplay());
    }

    public function testAModuleTheFleetDoesNotKnowIsRefusedBeforeAnythingHappens(): void
    {
        $tester = $this->gate();
        $exit = $tester->execute(['--dry-run' => true, '--module' => ['weather']]);

        self::assertSame(2, $exit, 'an invalid invocation, not a red gate');
        self::assertStringContainsString('knows no module "weather"', $tester->getDisplay());
    }

    public function testAModeThatIsNeitherIsRefused(): void
    {
        $tester = $this->gate();
        $exit = $tester->execute(['--dry-run' => true, '--mode' => 'sideways']);

        self::assertSame(2, $exit);
        self::assertStringContainsString('"released" or "head"', $tester->getDisplay());
    }

    private function gate(): CommandTester
    {
        self::ensureKernelShutdown();
        $application = new Application(self::bootKernel());
        $application->setAutoExit(false);

        return new CommandTester($application->find('fleet:gate'));
    }
}
