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

namespace Uhifadhi\Devkit\Fleet;

/**
 * THE STARTER'S README, TURNED INTO A LIST OF STEPS.
 *
 * The whole plan is built before anything runs, so `--dry-run` prints the run
 * that would happen rather than a description of it, and so the ordering — which
 * is the part that goes wrong — is unit-testable without a network, a database
 * or a directory.
 *
 * WHAT DECIDES THE COMPOSER MECHANISMS BELOW — docs first, then the tool's own
 * source where the docs stop short:
 *
 *   create-project --repository / --add-repository
 *     "Provide a custom repository to search for the package, which will be
 *      used instead of packagist."
 *     "Add the custom repository in the composer.json. If a lock file is
 *      present, it will be deleted and an update will be run instead of an
 *      install."
 *
 *     @see https://getcomposer.org/doc/03-cli.md#create-project
 *     @see composer/src/Composer/Command/CreateProjectCommand.php —
 *          `if (null === $repositories) { …defaultRepos… } else { …only these… }`
 *          and the composer.json is written ONLY under `$addRepository`. So
 *          `--repository` finds the ROOT package and nothing more: every later
 *          `composer require` needs its own repository, which is what the
 *          `composer config repositories.*` steps write.
 *
 *   create-project --stability
 *     "Minimum stability of package. Defaults to `stable`."
 *     @see https://getcomposer.org/doc/03-cli.md#create-project
 *     @see composer/src/Composer/Command/CreateProjectCommand.php —
 *          `new RepositorySet($stability)`; it bounds the ROOT package's
 *          candidates and is never written into the created project.
 *
 *   composer config repositories.<name> vcs <url>
 *     "php composer.phar config repositories.foo vcs https://github.com/foo/bar"
 *     @see https://getcomposer.org/doc/03-cli.md#config
 *
 *   doctrine:schema:validate --skip-sync / --skip-mapping
 *     @see vendor/doctrine/orm/src/Tools/Console/Command/ValidateSchemaCommand.php —
 *          "Skip checking if the mapping is in sync with the database" / "Skip
 *          the mapping validation check": the two runs below ask the two
 *          questions separately on purpose.
 *
 *   asset-map:compile
 *     @see https://symfony.com/doc/current/frontend/asset_mapper.html#deploying
 */
final readonly class GatePlanner
{
    public const string ADMIN_EMAIL = 'gate@example.test';
    public const string ADMIN_PASSWORD = 'fleet-gate-passphrase';
    public const string ADMIN_FIRST_NAME = 'Ada';
    public const string ADMIN_LAST_NAME = 'Mwangi';

    /** The variable the created project reads its own database through. */
    public const string DATABASE_ENV = 'DATABASE_URL';

    public function __construct(private CheckoutVersionsInterface $versions)
    {
    }

    public function plan(FleetSettings $settings, GateRequest $request): GatePlan
    {
        $steps = [];

        if ($request->isHead()) {
            // The starter's lock agrees with its manifest, or nothing is created
            // from it: `composer create-project` warns about a stale lock and
            // installs on, so without this the gate would prove a project the
            // starter's own CI refuses.
            // https://getcomposer.org/doc/03-cli.md#validate
            $steps[] = new GateStep(
                GateStepKind::ValidateStarter,
                'the starter validates',
                ['composer', 'validate', '--strict', '--no-check-publish'],
                subject: $request->skeletonCheckout(),
            );

            // Only head mode has the starter on disk to read. The list the gate
            // installs and the list the starter's table offers an installer are
            // the same list, and they drift silently: a module configured here
            // and missing from the table is a module nobody is told to install.
            $steps[] = new GateStep(
                GateStepKind::ReadmeListsModules,
                'the starter lists every official module',
                subject: $request->skeletonCheckout(),
            );
        }

        $steps = [...$steps, ...$this->createTheProject($request)];
        $steps = [...$steps, ...$this->giveItADatabase($request)];
        $steps = [...$steps, ...$this->install($settings, 'the core')];

        $steps[] = new GateStep(
            GateStepKind::Shell,
            'the first administrator',
            ['php', 'bin/console', 'team:user:create', self::ADMIN_EMAIL, self::ADMIN_FIRST_NAME, self::ADMIN_LAST_NAME, '--tier=super-admin', '--password='.self::ADMIN_PASSWORD, '--no-interaction'],
            expect: self::ADMIN_EMAIL,
        );

        $steps[] = new GateStep(GateStepKind::Serve, 'the project is served');
        $steps[] = new GateStep(GateStepKind::SignIn, 'the administrator signs in');
        $steps[] = new GateStep(GateStepKind::CreateArea, \sprintf('the area "%s" is created', $settings->areaName), subject: $settings->areaName);
        $steps[] = new GateStep(GateStepKind::OpenPersonRecord, 'the administrator\'s record answers');

        foreach ($request->modules as $module) {
            $steps = [...$steps, ...$this->addTheModule($settings, $request, $module)];
        }

        return new GatePlan($steps);
    }

    /**
     * README §1. Head mode reads the sibling checkouts as git repositories, so
     * the project gets each one's committed HEAD exported the way a tag is —
     * never a working tree, whose var/ and vendor/ would come along and whose
     * copied container would report itself fresh forever.
     *
     * @return list<GateStep>
     */
    private function createTheProject(GateRequest $request): array
    {
        $command = ['composer', 'create-project', 'uhifadhi/skeleton', $request->project, '--no-interaction', '--no-progress'];

        if (!$request->isHead()) {
            // Released mode is the README's own line: Packagist, no flags.
            return [new GateStep(GateStepKind::Shell, 'create the project', $command, inProject: false)];
        }

        $command[] = '--stability=dev';
        $command[] = '--repository='.json_encode(['type' => 'vcs', 'url' => $request->skeletonCheckout()], \JSON_THROW_ON_ERROR);

        return [
            new GateStep(GateStepKind::Shell, 'create the project from the starter checkout', $command, inProject: false),
            ...$this->pointAt('uhifadhi/uhifadhi', $request->coreCheckout()),
        ];
    }

    /**
     * README §2: every database this run owns, made from nothing, and the
     * .env.local naming them. A module keeping its tables in a database of its
     * own is no different in kind from the application's, so both arrive here.
     *
     * @return list<GateStep>
     */
    private function giveItADatabase(GateRequest $request): array
    {
        $steps = [];
        foreach ($request->databases as $variable => $url) {
            $steps[] = new GateStep(GateStepKind::FreshDatabase, \sprintf('a fresh database for %s', $variable), subject: $url);
        }
        $steps[] = new GateStep(GateStepKind::WriteEnvironment, 'the project is pointed at them (.env.local)');

        return $steps;
    }

    /**
     * README §3, and the same three commands again after every module, because a
     * module adds its own tables and its own assets.
     *
     * The middle of it is {@see FleetSettings::$afterMigrate} — configuration,
     * not code. The catalogue is filled by an explicit command run after the
     * migration; when the fleet gains another such command it is a line of
     * config in the installation, and nothing here is released for it.
     *
     * @return list<GateStep>
     */
    private function install(FleetSettings $settings, string $what): array
    {
        // Clear and warm are split because a clear that warms in-process needs
        // more than PHP's default 128 MB.
        $steps = [new GateStep(GateStepKind::Shell, $what.': cache:clear --no-warmup', ['php', 'bin/console', 'cache:clear', '--no-warmup'])];

        foreach ($settings->afterMigrate as $command) {
            $steps[] = new GateStep(GateStepKind::Shell, $what.': '.$command, ['php', 'bin/console', $command, '--no-interaction']);
        }

        // Not one of the README's commands: the production image runs it when it
        // is built. The gate runs it here to prove the build step still works.
        $steps[] = new GateStep(GateStepKind::Shell, $what.': asset-map:compile (the image\'s build step)', ['php', 'bin/console', 'asset-map:compile']);

        // The shipped migrations and the shipped entities must agree: a package
        // whose entity moved on without its migration is caught here.
        $steps[] = new GateStep(GateStepKind::Shell, $what.': the mapping is valid', ['php', 'bin/console', 'doctrine:schema:validate', '--skip-sync', '--no-interaction']);
        $steps[] = new GateStep(
            GateStepKind::Shell,
            $what.': the schema is in sync',
            ['php', 'bin/console', 'doctrine:schema:validate', '--skip-mapping', '--no-interaction'],
            expect: 'in sync',
            allowFailure: true,
        );

        return $steps;
    }

    /**
     * README §6, once per module. The require, this module's own database where
     * it keeps one, the three commands, both schema checks, the catalogue
     * listing it, the project's own smoke suite, the sign-in again, and the
     * module switched on for the area and answering.
     *
     * @return list<GateStep>
     */
    private function addTheModule(FleetSettings $settings, GateRequest $request, ModuleUnderGate $module): array
    {
        $package = $module->package();
        $steps = [];

        if ($request->isHead()) {
            $checkout = $module->checkout($request->workspace);
            $steps = [...$steps, ...$this->pointAt($package, $checkout)];
        } else {
            // A module outside Packagist names its repository first; the rest is
            // the README's own line.
            if (null !== $module->repository) {
                $steps[] = new GateStep(GateStepKind::Shell, $package.': its repository', ['composer', 'config', 'repositories.'.$module->name, 'vcs', $module->repository]);
            }
            $steps[] = new GateStep(GateStepKind::Shell, $package.': require', ['composer', 'require', $package, '--no-interaction', '--no-progress']);
        }

        if (null !== $module->databaseEnv && null !== $module->migrateCommand) {
            // Its tables live in a database of its own, created by its own
            // command; the README's row for it says so.
            $steps[] = new GateStep(GateStepKind::Shell, $package.': '.$module->migrateCommand, ['php', 'bin/console', $module->migrateCommand, '--no-interaction']);
        }

        $steps = [...$steps, ...$this->install($settings, $package)];

        if (null !== $module->catalogueSlug) {
            // The assertion that was missing when a freshly installed module sat
            // in the packages but never reached the catalogue: everything else
            // still booted, signed in and passed.
            $steps[] = new GateStep(
                GateStepKind::Shell,
                \sprintf('%s: the catalogue lists "%s"', $package, $module->catalogueSlug),
                ['php', 'bin/console', 'dbal:run-sql', 'SELECT slug FROM module ORDER BY slug', '--no-interaction'],
                expect: $module->catalogueSlug,
            );
        }

        $steps[] = new GateStep(GateStepKind::Shell, $package.': the project\'s own smoke suite', ['composer', 'test']);
        $steps[] = new GateStep(GateStepKind::Serve, $package.': the project is served again');
        $steps[] = new GateStep(GateStepKind::SignIn, $package.': the administrator signs in');
        $steps[] = new GateStep(GateStepKind::OpenModule, \sprintf('%s: switched on, and %s answers', $package, str_replace('%s', '{uuid}', $module->page)), subject: $module->name);
        $steps[] = new GateStep(GateStepKind::OpenPersonRecord, $package.': the administrator\'s record answers, with every contributed card', subject: $module->name);

        return $steps;
    }

    /**
     * The sibling checkout standing in for a published repository, and the
     * require that names the branch it has out.
     *
     * @return list<GateStep>
     */
    private function pointAt(string $package, string $checkout): array
    {
        return [
            new GateStep(GateStepKind::Shell, $package.': from '.$checkout, ['composer', 'config', 'repositories.'.str_replace('/', '-', $package), 'vcs', $checkout]),
            new GateStep(GateStepKind::Shell, $package.': require the branch it has out', ['composer', 'require', $package.':'.$this->versions->versionOf($checkout), '--no-interaction', '--no-progress']),
        ];
    }
}
