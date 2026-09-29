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

/**
 * The whole assembled command list, grouped by contributing module, with the
 * counts the surfaces head themselves with.
 *
 * The devkit group is always present and always first: devkit contributes
 * `fixtures:seed`, the one command that stands a fresh park up, and it is the
 * hero of the home surface. The home page renders that hero and then the
 * modules' contributions; the Commands page renders every group, devkit
 * included.
 */
final readonly class CommandCatalogue
{
    /** The command name devkit contributes itself — the one it materialises from every module's seed content. */
    public const string HERO_COMMAND = 'fixtures:seed';

    /**
     * @param list<CommandGroup> $groups devkit first, then the modules in
     *                                   contribution order
     */
    public function __construct(
        public array $groups,
    ) {
    }

    /**
     * `fixtures:seed`, devkit's own — rendered loud on the home surface.
     */
    public function hero(): AssembledCommand
    {
        foreach ($this->groups as $group) {
            foreach ($group->commands as $command) {
                if (self::HERO_COMMAND === $command->name) {
                    return $command;
                }
            }
        }

        // devkit always contributes it; unreachable in a real install.
        return new AssembledCommand(self::HERO_COMMAND, CommandKind::SeedContent, 'Load the full cross-module seed dataset.');
    }

    /**
     * The modules' groups — everything except devkit's own — which the home
     * surface lists beneath the hero.
     *
     * @return list<CommandGroup>
     */
    public function contributingGroups(): array
    {
        return array_values(array_filter(
            $this->groups,
            static fn (CommandGroup $group): bool => 'devkit' !== $group->label,
        ));
    }

    /**
     * Every assembled command, across every group — the "assembled" count.
     */
    public function total(): int
    {
        return array_sum(array_map(static fn (CommandGroup $group): int => $group->count(), $this->groups));
    }

    /**
     * How many of the assembled entries are seed-content loaders.
     */
    public function seedContentCount(): int
    {
        return \count($this->commandsWhere(static fn (AssembledCommand $c): bool => CommandKind::SeedContent === $c->kind));
    }

    /**
     * How many are marked deprecated — always 0 in v1; see {@see AssembledCommand}.
     */
    public function deprecatedCount(): int
    {
        return \count($this->commandsWhere(static fn (AssembledCommand $c): bool => $c->deprecated));
    }

    /**
     * How many modules contribute at least one command — the devkit group counts,
     * because devkit is a module that contributes one.
     */
    public function contributorCount(): int
    {
        return \count(array_filter($this->groups, static fn (CommandGroup $g): bool => $g->count() > 0));
    }

    /**
     * @param callable(AssembledCommand): bool $predicate
     *
     * @return list<AssembledCommand>
     */
    private function commandsWhere(callable $predicate): array
    {
        $matched = [];
        foreach ($this->groups as $group) {
            foreach ($group->commands as $command) {
                if ($predicate($command)) {
                    $matched[] = $command;
                }
            }
        }

        return $matched;
    }
}
