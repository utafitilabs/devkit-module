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

namespace Uhifadhi\Devkit\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Uhifadhi\Devkit\Fleet\FleetSettings;
use Uhifadhi\Devkit\Fleet\GateFailure;
use Uhifadhi\Devkit\Fleet\GateMode;
use Uhifadhi\Devkit\Fleet\GatePlanner;
use Uhifadhi\Devkit\Fleet\GateRequest;
use Uhifadhi\Devkit\Fleet\GateRunner;
use Uhifadhi\Devkit\Fleet\GateStep;

/**
 * `fleet:gate` — THE FLEET GATE: the starter's install, performed.
 *
 * Every other suite in the fleet tests one package at its own HEAD. This one
 * follows the starter's README from the top, in a directory that did not exist a
 * minute ago: create the project, give it a database, migrate, make the first
 * administrator, sign in over HTTP, create the area, and then require every
 * official module one by one — migrating, validating the schema, checking the
 * catalogue, running the project's own smoke suite, signing in again and opening
 * the module's first page after each. The step that breaks is the step the
 * report names, and nothing after it runs.
 *
 *   fleet:gate                  released — every package resolves the way the
 *                               README's own commands resolve it, from the
 *                               published repositories. Run after ANY tag in the
 *                               fleet; the tag is not done until this is green.
 *   fleet:gate --mode=head      head — the same steps against the branch each
 *                               sibling checkout has out, as last committed, so
 *                               "if I tagged everything right now, would an
 *                               install work?" is answered before the tag.
 *
 * It lives in devkit because devkit is the dev-only module: it installs through
 * require-dev, which is the production firewall, and a command that creates
 * projects, drops databases and spawns servers belongs behind it. It is not in
 * the starter, because the starter is copied once into every installation and a
 * line added there is a line every installation is stuck with.
 *
 * IT OWNS ITS DATABASES. Every database it is given is DROPPED and recreated, so
 * the defaults name databases nothing else uses and an override must do the
 * same.
 */
#[AsCommand(
    name: 'fleet:gate',
    description: 'Create a project from the starter and install the whole fleet into it, one module at a time (dev-only).',
)]
final class FleetGateCommand extends Command
{
    /** The gate's own databases. Dropped and recreated on every run. */
    private const string DEFAULT_DATABASE_URL = 'postgresql://app:app@127.0.0.1:5434/fleet_gate?serverVersion=17&charset=utf8';

    public function __construct(
        private readonly FleetSettings $settings,
        private readonly GatePlanner $planner,
        private readonly GateRunner $runner,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('mode', null, InputOption::VALUE_REQUIRED, 'released (the published repositories) or head (the sibling checkouts)', GateMode::Released->value)
            ->addOption('workspace', null, InputOption::VALUE_REQUIRED, 'Head mode: the directory holding the sibling checkouts', self::defaultWorkspace())
            ->addOption('module', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'A module to install instead of the configured official list; repeatable')
            ->addOption('private-module', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'A managed-hosting module to install as well; repeatable')
            ->addOption('database-url', null, InputOption::VALUE_REQUIRED, 'The gate\'s own database. IT IS DROPPED AND RECREATED', self::environment('FLEET_GATE_DATABASE_URL') ?? self::DEFAULT_DATABASE_URL)
            ->addOption('keep', null, InputOption::VALUE_NONE, 'Keep the created project afterwards, for a look')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Print the plan and run none of it');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $asked = self::text($input, 'mode');
        $mode = GateMode::tryFrom($asked);
        if (null === $mode) {
            $io->error(\sprintf('The mode is "released" or "head", not "%s".', $asked));

            return Command::INVALID;
        }

        try {
            $request = $this->request($mode, $input);
            $plan = $this->planner->plan($this->settings, $request);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            $io->error($e->getMessage());

            return Command::INVALID;
        }

        $io->title(\sprintf('Fleet gate · %s mode · %d steps', $mode->value, $plan->count()));
        $io->text([
            'project    '.$request->project,
            'workspace  '.($request->isHead() ? $request->workspace : '— released mode reads the published repositories'),
            'modules    '.implode(', ', array_map(static fn ($m): string => $m->package(), $request->modules)),
            'databases  '.implode(', ', array_keys($request->databases)).'  (dropped and recreated)',
        ]);
        $io->newLine();

        if (true === $input->getOption('dry-run')) {
            $io->section('The plan');
            foreach ($plan->describe() as $i => $line) {
                $io->writeln(\sprintf(' %2d. %s', $i + 1, $line));
            }
            $io->newLine();
            $io->note('Dry run: nothing was created, dropped or served.');

            return Command::SUCCESS;
        }

        try {
            $this->runner->run($plan, $request, static function (GateStep $step, bool $passed, string $out) use ($io): void {
                $io->writeln(($passed ? ' <info>✓</info> ' : ' <error>✗</error> ').$step->label);
                if (!$passed) {
                    $io->newLine();
                    $io->writeln($out);
                }
            });
        } catch (GateFailure $failure) {
            $io->newLine();
            $io->error([
                'FLEET GATE RED at: '.$failure->step->label,
                $failure->getMessage(),
                [] === $failure->step->command ? '' : '$ '.implode(' ', $failure->step->command),
            ]);
            if ($request->keep) {
                $io->note('kept '.$request->project);
            }
            $this->runner->tearDown($request);

            return Command::FAILURE;
        }

        $this->runner->tearDown($request);
        $io->success(\sprintf('The fleet installs and runs as one product (%s mode).', $mode->value));
        if ($request->keep) {
            $io->note('kept '.$request->project);
        }

        return Command::SUCCESS;
    }

    private function request(GateMode $mode, InputInterface $input): GateRequest
    {
        /** @var list<string> $only */
        $only = $input->getOption('module');
        /** @var list<string> $private */
        $private = $input->getOption('private-module');

        $modules = $this->settings->resolve([
            ...([] !== $only ? $only : $this->settings->officialModules),
            ...$private,
        ]);

        $url = self::text($input, 'database-url');
        $databases = [GatePlanner::DATABASE_ENV => $url];
        foreach ($modules as $module) {
            if (null === $module->databaseEnv) {
                continue;
            }
            // A module keeping its tables in a database of its own: the gate
            // makes that one too, and owns it the same way.
            $databases[$module->databaseEnv] = self::environment('FLEET_GATE_'.$module->databaseEnv)
                ?? self::environment($module->databaseEnv)
                ?? self::beside($url, $module->name);
        }

        return new GateRequest(
            $mode,
            rtrim(sys_get_temp_dir(), '/').'/fleet-gate-'.bin2hex(random_bytes(4)),
            self::text($input, 'workspace'),
            $modules,
            $databases,
            true === $input->getOption('keep'),
        );
    }

    /**
     * THE WORKSPACE, DERIVED: the directory holding this checkout, which is the
     * directory holding its siblings. It is right wherever devkit is the linked
     * sibling checkout a developer is working in, which is the only place head
     * mode has anything to read; anywhere else `--workspace` says so, and head
     * mode fails by name on the first checkout that is not there.
     */
    private static function defaultWorkspace(): string
    {
        $bundle = realpath(\dirname(__DIR__, 2));

        return false === $bundle ? '' : \dirname($bundle);
    }

    /** An option declared VALUE_REQUIRED is the string it was given, or its default. */
    private static function text(InputInterface $input, string $name): string
    {
        $value = $input->getOption($name);

        return \is_string($value) ? $value : '';
    }

    /**
     * A second database on the same server as the gate's own, named after it and
     * the module — so a run that was pointed somewhere takes its module database
     * there too, and a default run keeps every database it owns under one name.
     */
    private static function beside(string $url, string $suffix): string
    {
        $parts = parse_url($url);
        if (!\is_array($parts) || '' === ($parts['path'] ?? '')) {
            throw new \InvalidArgumentException(\sprintf('"%s" names no database.', $url));
        }

        return str_replace($parts['path'], $parts['path'].'_'.$suffix, $url);
    }

    private static function environment(string $name): ?string
    {
        $value = getenv($name);

        return \is_string($value) && '' !== $value ? $value : null;
    }
}
