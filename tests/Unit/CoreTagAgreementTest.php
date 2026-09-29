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

use Composer\InstalledVersions;
use PHPUnit\Framework\TestCase;
use Uhifadhi\Devkit\UhifadhiDevkitBundle;

/**
 * THE STRINGS BOTH SIDES HAVE TO SAY, held against each other.
 *
 * The arrangement has no shared symbol in it, deliberately. A provider ships
 * inside a bundle that is always installed, while devkit arrives through
 * require-dev and is absent from production; a provider that named
 * {@see UhifadhiDevkitBundle::CONTENT_PROVIDER_TAG} would be loading a class
 * that is not there. So the always-installed side writes the tag as a LITERAL,
 * devkit's constant is a second copy of the same literal, and nothing in either
 * language stops the two from drifting apart.
 *
 * Nothing except this. A drift would not break a build or throw: devkit's
 * iterator would simply come back empty and `fixtures:seed` would report a
 * successful seed of nothing. That is the failure this pins — read out of the
 * core's own shipped service file, so the assertion is against what the
 * providers are really tagged with rather than against a string this suite also
 * wrote.
 */
final class CoreTagAgreementTest extends TestCase
{
    /** Where the core tags its own two providers. */
    private const string CORE_SERVICES = '/src/Uhifadhi/Bundle/TeamBundle/config/services.php';

    public function testTheContentTagIsTheLiteralTheCoresProviderIsTaggedWith(): void
    {
        self::assertStringContainsString(
            "->tag('".UhifadhiDevkitBundle::CONTENT_PROVIDER_TAG."')",
            self::coreServices(),
            'The core tags its content provider with a literal string. fixtures:seed collects on a constant. They are the same string or the seed is empty.',
        );
    }

    /**
     * The pin is only worth having if it is complete: a devkit tag in the core
     * that nothing here collects is a contribution that silently does not
     * arrive. So every devkit tag the core writes has to be one devkit knows.
     */
    public function testTheCoreWritesNoDevkitTagDevkitDoesNotCollect(): void
    {
        preg_match_all("/'(uhifadhi\.devkit\.[a-z_]+)'/", self::coreServices(), $matches);

        self::assertNotEmpty($matches[1], 'The core is expected to tag providers for devkit to collect.');
        self::assertSame(
            [],
            array_values(array_diff(
                array_unique($matches[1]),
                [UhifadhiDevkitBundle::COMMAND_PROVIDER_TAG, UhifadhiDevkitBundle::CONTENT_PROVIDER_TAG],
            )),
            'devkit collects two tags. Any other one in the core is a contribution nothing comes for.',
        );
    }

    private static function coreServices(): string
    {
        $path = InstalledVersions::getInstallPath('uhifadhi/uhifadhi');
        self::assertNotNull($path, 'The core is a requirement of devkit and is installed.');

        $file = $path.self::CORE_SERVICES;
        self::assertFileExists($file);

        return (string) file_get_contents($file);
    }
}
