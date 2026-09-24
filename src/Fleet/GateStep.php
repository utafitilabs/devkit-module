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
 * ONE STEP OF THE GATE — what it is called, and what performing it means.
 *
 * A step is data, not behaviour: {@see GatePlanner} builds the whole plan before
 * anything runs, which is what makes `--dry-run` the same list that a real run
 * performs rather than a description of it, and what makes the plan unit-testable
 * without a network, a database or a project on disk.
 */
final readonly class GateStep
{
    /**
     * @param GateStepKind $kind         what performing this step means
     * @param string       $label        the line the report prints, pass or fail
     * @param list<string> $command      a Shell step's argv
     * @param string|null  $subject      the module a module-shaped step is about, or the url a database step names
     * @param string|null  $expect       a substring the step's output must contain
     * @param bool         $allowFailure a non-zero exit is not the failure; {@see $expect} decides
     * @param bool         $inProject    the step runs in the created project rather than beside it
     */
    public function __construct(
        public GateStepKind $kind,
        public string $label,
        public array $command = [],
        public ?string $subject = null,
        public ?string $expect = null,
        public bool $allowFailure = false,
        public bool $inProject = true,
    ) {
    }

    /** The step as a line of a dry run: what it is, and the command it is. */
    public function describe(): string
    {
        return [] === $this->command
            ? $this->label
            : $this->label.'   $ '.implode(' ', $this->command);
    }
}
