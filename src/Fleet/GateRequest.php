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
 * ONE RUN OF THE GATE, as asked for: the mode, where the project is made, where
 * the sibling checkouts are, which databases are its own, and which modules it
 * installs.
 *
 * Everything here comes off the command line or its defaults; nothing is read
 * from the ambient environment once this object exists, so a plan is reproducible
 * and a dry run prints the run that would happen.
 */
final readonly class GateRequest
{
    /**
     * @param list<ModuleUnderGate> $modules   the modules to install, in order
     * @param array<string, string> $databases the databases this run owns, keyed by the environment variable the
     *                                         project reads each one through; every one of them is DROPPED and
     *                                         recreated, so none of them may be a database anybody else uses
     */
    public function __construct(
        public GateMode $mode,
        public string $project,
        public string $workspace,
        public array $modules,
        public array $databases,
        public bool $keep = false,
    ) {
    }

    public function isHead(): bool
    {
        return GateMode::Head === $this->mode;
    }

    /** The starter's own checkout, which head mode creates the project from. */
    public function skeletonCheckout(): string
    {
        return rtrim($this->workspace, '/').'/skeleton';
    }

    /** The core's checkout, which head mode requires over the created project's own. */
    public function coreCheckout(): string
    {
        return rtrim($this->workspace, '/').'/uhifadhi';
    }
}
