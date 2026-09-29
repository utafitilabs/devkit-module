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

namespace Uhifadhi\Devkit\Console\Command;

use Uhifadhi\Contracts\Devkit\CommandProviderInterface;
use Uhifadhi\Contracts\Devkit\ContentProviderInterface;
use Uhifadhi\Devkit\Console\Package\PackageIntrospector;
use Uhifadhi\Devkit\Console\Package\ResolvedPackage;

/**
 * ASSEMBLES THE COMMAND LIST the console's Commands surface reads — the same
 * collection slice 1's `fixtures:seed` and command loader are built from, seen
 * from the side rather than run.
 *
 * It reads devkit's two tags — every tagged {@see ContentProviderInterface}
 * and {@see CommandProviderInterface} — and groups each contribution under the
 * module that shipped it, resolved from the provider's own package. devkit's own
 * `fixtures:seed` leads the list: devkit is itself a module that contributes one
 * command, and it is the one a builder reaches for first.
 *
 * It RUNS nothing. This is the inspector half of the console — it discovers what
 * the fleet contributes so a builder need not grep ten composer.json files;
 * running still happens at the CLI (v1).
 */
final class CommandInventory
{
    private const string DEVKIT_PACKAGE = 'uhifadhi/devkit-module';

    /**
     * @param iterable<ContentProviderInterface> $contentProviders every tagged seed-content provider
     * @param iterable<CommandProviderInterface> $commandProviders every tagged command provider
     */
    public function __construct(
        private readonly iterable $contentProviders,
        private readonly iterable $commandProviders,
        private readonly PackageIntrospector $packages,
    ) {
    }

    public function catalogue(): CommandCatalogue
    {
        /**
         * Keyed by group label so a module's content and commands land together;
         * insertion order is contribution order, which the surfaces read top to
         * bottom.
         *
         * @var array<string, array{package: ?ResolvedPackage, commands: list<AssembledCommand>}> $groups
         */
        $groups = [];

        // devkit first — its own fixtures:seed, the hero of the home surface.
        $groups['devkit'] = [
            'package' => $this->packages->package(self::DEVKIT_PACKAGE),
            'commands' => [new AssembledCommand(
                CommandCatalogue::HERO_COMMAND,
                CommandKind::SeedContent,
                'Load the full cross-module seed dataset — every installed module’s seed content, resolved and run in dependency order. The one command that stands a fresh park up.',
            )],
        ];

        foreach ($this->contentProviders as $provider) {
            $this->add(
                $groups,
                $provider,
                new AssembledCommand($provider->key(), CommandKind::SeedContent, $provider->description()),
            );
        }

        foreach ($this->commandProviders as $provider) {
            foreach ($provider->commands() as $descriptor) {
                $this->add(
                    $groups,
                    $provider,
                    new AssembledCommand($descriptor->name, CommandKind::Command, $descriptor->description),
                );
            }
        }

        $built = [];
        foreach ($groups as $label => $group) {
            $built[] = new CommandGroup($label, $group['package'], $group['commands']);
        }

        return new CommandCatalogue($built);
    }

    /**
     * @param array<string, array{package: ?ResolvedPackage, commands: list<AssembledCommand>}> $groups
     */
    private function add(array &$groups, object $provider, AssembledCommand $command): void
    {
        $package = $this->packages->ownerOf($provider);
        $label = null !== $package ? $package->shortName : 'app';

        if (!isset($groups[$label])) {
            $groups[$label] = ['package' => $package, 'commands' => []];
        }

        $groups[$label]['commands'][] = $command;
    }
}
