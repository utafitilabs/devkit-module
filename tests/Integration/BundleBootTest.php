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
use Uhifadhi\Devkit\Tests\Integration\Fixtures\RecordingCommandProvider;
use Uhifadhi\Devkit\Tests\Integration\Fixtures\RecordingContentProvider;

/**
 * The whole collector, wired through the real container: the bundle boots,
 * devkit collects the tagged providers, fixtures:seed seeds the content
 * providers in dependency order, and a command provider's descriptor is a
 * console command the application can find and run.
 */
final class BundleBootTest extends KernelTestCase
{
    protected function setUp(): void
    {
        RecordingContentProvider::reset();
        RecordingCommandProvider::reset();
    }

    public function testFixturesSeedSeedsTaggedProvidersInDependencyOrder(): void
    {
        $application = new Application(self::bootKernel());
        $application->setAutoExit(false);

        $tester = new CommandTester($application->find('fixtures:seed'));
        $exit = $tester->execute([]);

        self::assertSame(0, $exit);
        self::assertSame(['area', 'patrol', 'incident'], RecordingContentProvider::$loaded);
    }

    public function testACommandProvidersDescriptorIsARunnableCommand(): void
    {
        $application = new Application(self::bootKernel());
        $application->setAutoExit(false);

        $command = $application->find('devkit:test:echo');
        $tester = new CommandTester($command);
        $exit = $tester->execute(['arguments' => ['one', 'two']]);

        // The exit code and the recorded tail both come from the descriptor's
        // own closure — proof the descriptor became this running command.
        self::assertSame(7, $exit);
        self::assertSame(['one', 'two'], RecordingCommandProvider::$ranWith);
    }
}
