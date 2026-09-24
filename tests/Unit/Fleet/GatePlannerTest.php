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

namespace Uhifadhi\Devkit\Tests\Unit\Fleet;

use PHPUnit\Framework\TestCase;
use Uhifadhi\Devkit\DependencyInjection\DevkitConfiguration;
use Uhifadhi\Devkit\Fleet\FleetSettings;
use Uhifadhi\Devkit\Fleet\GateMode;
use Uhifadhi\Devkit\Fleet\GatePlan;
use Uhifadhi\Devkit\Fleet\GatePlanner;
use Uhifadhi\Devkit\Fleet\GateRequest;
use Uhifadhi\Devkit\Fleet\GateStep;
use Uhifadhi\Devkit\Fleet\GateStepKind;
use Uhifadhi\Devkit\Tests\Fixtures\FixedCheckoutVersions;

/**
 * The plan is the gate's one decision, made before anything runs — which is what
 * lets it be asserted here without a network, a database or a project on disk.
 */
final class GatePlannerTest extends TestCase
{
    public function testTheModuleListComesFromConfigurationAndKeepsItsOrder(): void
    {
        $plan = $this->plan(GateMode::Released);

        $requires = $this->commandsMatching($plan, 'require');
        self::assertSame([
            'composer require uhifadhi/storage-module --no-interaction --no-progress',
            'composer require uhifadhi/patrol-module --no-interaction --no-progress',
            'composer require uhifadhi/incident-module --no-interaction --no-progress',
            'composer require uhifadhi/roster-module --no-interaction --no-progress',
        ], $requires, 'the configured official list, in the configured install order');
    }

    public function testAskingForOtherModulesGatesThoseInstead(): void
    {
        $settings = $this->settings();
        $plan = $this->planner()->plan($settings, $this->request(GateMode::Released, $settings->resolve(['patrol'])));

        self::assertSame(
            ['composer require uhifadhi/patrol-module --no-interaction --no-progress'],
            $this->commandsMatching($plan, 'require'),
        );
    }

    public function testAModuleNobodyDescribedIsRefusedByName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('knows no module "weather"');

        $this->settings()->resolve(['weather']);
    }

    /**
     * Released mode is the starter's own line: Packagist, no flags. Head mode
     * creates from the starter's checkout and then requires the core by the
     * branch that checkout has out.
     */
    public function testReleasedModeCreatesTheProjectWithNoRepositoryFlags(): void
    {
        $first = $this->plan(GateMode::Released)->steps[0];

        self::assertSame(GateStepKind::Shell, $first->kind);
        self::assertSame(['composer', 'create-project', 'uhifadhi/skeleton', '/tmp/gate-project', '--no-interaction', '--no-progress'], $first->command);
        self::assertFalse($first->inProject, 'the project does not exist yet, so the step runs beside it');
    }

    public function testHeadModeReadsTheSiblingCheckoutsAndRequiresTheirBranches(): void
    {
        $plan = $this->plan(GateMode::Head);
        $lines = $plan->describe();

        self::assertSame(GateStepKind::ValidateStarter, $plan->steps[0]->kind, 'head mode validates the starter before creating anything from it');
        self::assertSame('/work/skeleton', $plan->steps[0]->subject);
        self::assertSame(['composer', 'validate', '--strict', '--no-check-publish'], $plan->steps[0]->command);
        self::assertSame(GateStepKind::ReadmeListsModules, $plan->steps[1]->kind, 'head mode has the starter on disk, so it checks the list first');

        self::assertStringContainsString('--stability=dev', $lines[2]);
        self::assertStringContainsString('--repository={"type":"vcs","url":"\/work\/skeleton"}', $lines[2]);

        self::assertContains('composer config repositories.uhifadhi-uhifadhi vcs /work/uhifadhi', $this->commands($plan));
        self::assertContains('composer require uhifadhi/uhifadhi:0.4.x-dev --no-interaction --no-progress', $this->commands($plan));
        self::assertContains('composer config repositories.uhifadhi-patrol-module vcs /work/patrol-module', $this->commands($plan));
        self::assertContains('composer require uhifadhi/patrol-module:0.4.x-dev --no-interaction --no-progress', $this->commands($plan));
    }

    /**
     * The commands an install is followed by are configuration. The catalogue is
     * filled by an explicit command run after the migration, and the day the
     * fleet gains another such command it is a line of config, not a release.
     */
    public function testTheAfterMigrateCommandsComeFromConfigurationInOrder(): void
    {
        $plan = $this->plan(GateMode::Released);
        $core = \array_slice($this->commands($plan), 0, 7);

        self::assertSame([
            'composer create-project uhifadhi/skeleton /tmp/gate-project --no-interaction --no-progress',
            'php bin/console cache:clear --no-warmup',
            'php bin/console doctrine:migrations:migrate --no-interaction',
            'php bin/console registry:sync --no-interaction',
            'php bin/console cache:warmup --no-interaction',
            'php bin/console asset-map:compile',
            'php bin/console doctrine:schema:validate --skip-sync --no-interaction',
        ], $core);
    }

    public function testEveryInstallIsFollowedByBothSchemaQuestions(): void
    {
        $plan = $this->plan(GateMode::Released);

        $sync = array_values(array_filter($plan->steps, static fn (GateStep $s): bool => 'in sync' === $s->expect));
        // Once for the core, once per module.
        self::assertCount(5, $sync);
        self::assertTrue($sync[0]->allowFailure, 'the answer is in the output, not in the exit code');
    }

    public function testACapabilityModuleIsAssertedIntoTheCatalogueAndInfrastructureIsNot(): void
    {
        $plan = $this->plan(GateMode::Released);

        $catalogue = [];
        foreach ($plan->steps as $step) {
            if (str_contains($step->label, 'the catalogue lists')) {
                $catalogue[] = $step->expect;
            }
        }

        self::assertSame(['patrols', 'incidents', 'roster'], $catalogue, 'storage declares no tile, so there is nothing to find');
    }

    public function testAModuleWithItsOwnDatabaseGetsItMadeAndMigrated(): void
    {
        $settings = $this->settings();
        $modules = $settings->resolve(['telemetry']);
        $plan = $this->planner()->plan($settings, $this->request(GateMode::Released, $modules, [
            'DATABASE_URL' => 'postgresql://app:app@127.0.0.1:5434/gate',
            'TELEMETRY_DATABASE_URL' => 'postgresql://app:app@127.0.0.1:5434/gate_telemetry',
        ]));

        $fresh = array_values(array_filter($plan->steps, static fn (GateStep $s): bool => GateStepKind::FreshDatabase === $s->kind));
        self::assertCount(2, $fresh);
        self::assertSame('postgresql://app:app@127.0.0.1:5434/gate_telemetry', $fresh[1]->subject);

        self::assertContains('composer config repositories.telemetry vcs https://github.com/utafitilabs/telemetry-module', $this->commands($plan));
        self::assertContains('php bin/console telemetry:migrate --no-interaction', $this->commands($plan));
    }

    /**
     * The order inside one module is the order an installer works in, and the
     * browser half is the half no shell can perform.
     */
    public function testAModuleEndsWithTheSmokeSuiteAndTheAdministratorOpeningIt(): void
    {
        $settings = $this->settings();
        $plan = $this->planner()->plan($settings, $this->request(GateMode::Released, $settings->resolve(['patrol'])));

        $kinds = array_map(static fn (GateStep $s): GateStepKind => $s->kind, \array_slice($plan->steps, -5));
        self::assertSame([GateStepKind::Shell, GateStepKind::Serve, GateStepKind::SignIn, GateStepKind::OpenModule, GateStepKind::OpenPersonRecord], $kinds, 'every module install ends on the administrator\'s record, where contributed cards are drawn');
        self::assertSame(['composer', 'test'], $plan->steps[$plan->count() - 5]->command);
        self::assertSame('patrol', $plan->steps[$plan->count() - 1]->subject);
    }

    public function testTheAdministratorAndTheAreaComeBeforeAnyModule(): void
    {
        $plan = $this->plan(GateMode::Released);

        $upTo = [];
        foreach ($plan->steps as $step) {
            if (str_contains($step->label, 'uhifadhi/storage-module')) {
                break;
            }
            $upTo[] = $step->kind;
        }

        self::assertContains(GateStepKind::WriteEnvironment, $upTo);
        self::assertContains(GateStepKind::Serve, $upTo);
        self::assertContains(GateStepKind::SignIn, $upTo);
        self::assertContains(GateStepKind::CreateArea, $upTo);
    }

    // ── the plan under test ─────────────────────────────────────────────────

    private function plan(GateMode $mode): GatePlan
    {
        $settings = $this->settings();

        return $this->planner()->plan($settings, $this->request($mode, $settings->resolve($settings->officialModules)));
    }

    private function planner(): GatePlanner
    {
        return new GatePlanner(new FixedCheckoutVersions('0.4.x-dev'));
    }

    private function settings(): FleetSettings
    {
        // The shipped defaults, read the way the container reads them.
        return FleetSettings::fromArray([
            'official_modules' => DevkitConfiguration::OFFICIAL_MODULES,
            'private_modules' => DevkitConfiguration::PRIVATE_MODULES,
            'after_migrate' => DevkitConfiguration::AFTER_MIGRATE,
            'area_name' => DevkitConfiguration::AREA_NAME,
            'modules' => DevkitConfiguration::MODULES,
        ]);
    }

    /**
     * @param list<\Uhifadhi\Devkit\Fleet\ModuleUnderGate> $modules
     * @param array<string, string>                        $databases
     */
    private function request(GateMode $mode, array $modules, array $databases = ['DATABASE_URL' => 'postgresql://app:app@127.0.0.1:5434/gate']): GateRequest
    {
        return new GateRequest($mode, '/tmp/gate-project', '/work', $modules, $databases);
    }

    /** @return list<string> */
    private function commands(GatePlan $plan): array
    {
        $commands = [];
        foreach ($plan->steps as $step) {
            if ([] !== $step->command) {
                $commands[] = implode(' ', $step->command);
            }
        }

        return $commands;
    }

    /** @return list<string> */
    private function commandsMatching(GatePlan $plan, string $needle): array
    {
        return array_values(array_filter(
            $this->commands($plan),
            static fn (string $command): bool => str_contains($command, ' '.$needle.' '),
        ));
    }
}
