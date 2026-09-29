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

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\CommandLoader\CommandLoaderInterface;
use Symfony\Component\Console\Exception\CommandNotFoundException;
use Uhifadhi\Contracts\Devkit\CommandDescriptor;
use Uhifadhi\Contracts\Devkit\CommandProviderInterface;

/**
 * Makes the modules' {@see CommandDescriptor}s appear as real console commands
 * by DECORATING the framework's own command loader.
 *
 * The descriptors' names are only known at runtime — a provider produces them by
 * calling commands() on a live service, closing over that service's
 * dependencies — so they cannot be registered as `console.command` services at
 * compile time the way a fixed-name command is. A command LOADER is exactly the
 * extension point Symfony provides for "resolve a command by name, lazily": this one wraps
 * the container's ContainerCommandLoader, answers for every descriptor name, and
 * delegates everything else — the application's own commands, including
 * devkit's fixtures:seed — to the inner loader untouched.
 *
 * Two providers that declare the same command name is a module mistake, refused
 * loudly the first time the map is built rather than silently letting one shadow
 * the other.
 */
final class ProviderCommandLoader implements CommandLoaderInterface
{
    /**
     * @var array<string, CommandDescriptor>|null the descriptor map, built once
     *                                            on first use
     */
    private ?array $descriptors = null;

    /**
     * @param iterable<CommandProviderInterface> $providers every command
     *                                                      provider the modules
     *                                                      tagged
     */
    public function __construct(
        private readonly CommandLoaderInterface $inner,
        private readonly iterable $providers,
    ) {
    }

    public function get(string $name): Command
    {
        $descriptors = $this->descriptors();
        if (isset($descriptors[$name])) {
            return new DescriptorCommand($descriptors[$name]);
        }

        return $this->inner->get($name);
    }

    public function has(string $name): bool
    {
        return isset($this->descriptors()[$name]) || $this->inner->has($name);
    }

    /**
     * @return list<string>
     */
    public function getNames(): array
    {
        return array_values(array_unique([
            ...array_keys($this->descriptors()),
            ...$this->inner->getNames(),
        ]));
    }

    /**
     * @return array<string, CommandDescriptor>
     */
    private function descriptors(): array
    {
        if (null !== $this->descriptors) {
            return $this->descriptors;
        }

        $map = [];
        foreach ($this->providers as $provider) {
            foreach ($provider->commands() as $descriptor) {
                if (isset($map[$descriptor->name])) {
                    throw new CommandNotFoundException(\sprintf('Two devkit command providers both declare the command "%s". A command name must be unique across every installed module; namespace it by module (e.g. "patrol:seed:reset").', $descriptor->name));
                }
                $map[$descriptor->name] = $descriptor;
            }
        }

        return $this->descriptors = $map;
    }
}
