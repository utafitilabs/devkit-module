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
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Uhifadhi\Contracts\Devkit\ContentProviderInterface;
use Uhifadhi\Devkit\Content\ContentOrderer;
use Uhifadhi\Devkit\Content\ContentOrderingException;

/**
 * `fixtures:seed` — seed every installed module's seed content, in dependency
 * order.
 *
 * There is no hand-written list of steps to keep in step with the installed
 * modules. It collects every {@see ContentProviderInterface} the modules tagged,
 * asks {@see ContentOrderer} to topologically sort them on their dependsOn()
 * edges, and calls load() on each in turn — printing the label and description
 * of each slice as it goes.
 *
 * It exists only in a dev install, because devkit — where it is registered — is
 * a require-dev package. In production the providers it would call are inert
 * tagged services and this command is not there to call them.
 *
 * An installation that has registered no providers is not an error: the command
 * says there is nothing to seed and exits cleanly.
 */
#[AsCommand(
    name: 'fixtures:seed',
    description: 'Seed every installed module\'s seed content, in dependsOn() order (dev-only).',
)]
final class SeedCommand extends Command
{
    /**
     * @param iterable<ContentProviderInterface> $providers every content
     *                                                      provider the modules
     *                                                      tagged, in
     *                                                      registration order
     */
    public function __construct(
        private readonly iterable $providers,
        private readonly ContentOrderer $orderer,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $ordered = $this->orderer->order($this->providers);
        } catch (ContentOrderingException $e) {
            // A malformed graph is a module bug, reported where a developer runs
            // the seed rather than as an uncaught stack trace.
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        if ([] === $ordered) {
            $io->note('No seed-content providers are installed — nothing to seed. A module contributes one by tagging a Devkit\ContentProviderInterface service.');

            return Command::SUCCESS;
        }

        $io->title('Seeding seed content');

        foreach ($ordered as $provider) {
            $io->section(\sprintf('%s (%s)', $provider->label(), $provider->key()));
            $io->text($provider->description());
            $provider->load();
        }

        $io->success(\sprintf('Seeded %d seed-content slice(s), in dependency order.', \count($ordered)));

        return Command::SUCCESS;
    }
}
