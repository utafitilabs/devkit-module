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
 * THE FLEET AS CONFIGURED — what `devkit.fleet` says, in one object.
 *
 * The module list is configuration and not code on purpose: the gate discovers
 * what a fleet is made of, so a new official module is a line under
 * `devkit.fleet.official_modules` plus its entry under `devkit.fleet.modules`,
 * and nothing in this bundle is edited to gate it.
 *
 * {@see $afterMigrate} is the same idea applied to the commands a package's
 * install is followed by. It is a list because that list has changed and will
 * change again — the catalogue is filled by an explicit command run after the
 * migration, and the day another such command is added it is configuration, not
 * a release of devkit.
 */
final readonly class FleetSettings
{
    /**
     * @param list<string>                   $officialModules the modules an installer requires, in install order:
     *                                                        a module that builds on another comes after it
     * @param list<string>                   $privateModules  the managed-hosting tier's modules, gated after the
     *                                                        official ones
     * @param list<string>                   $afterMigrate    the console commands every install is followed by, in
     *                                                        order, between the cache clear and the asset compile
     * @param array<string, ModuleUnderGate> $modules         every module the two lists may name, keyed by name
     * @param string                         $areaName        the area the gate works in — the manual's worked example,
     *                                                        so the gate's data and the book's are one and the same
     */
    public function __construct(
        public array $officialModules,
        public array $privateModules,
        public array $afterMigrate,
        public array $modules,
        public string $areaName,
    ) {
    }

    /**
     * `devkit.fleet` as the container holds it, turned into this.
     *
     * Every key is read defensively rather than trusted: a node given its whole
     * default through `defaultValue()` is not normalised through its prototype,
     * so an entry that omits an optional key genuinely arrives without it.
     *
     * @param array<string, mixed> $config
     */
    public static function fromArray(array $config): self
    {
        $modules = [];
        /** @var array<string, array<string, mixed>> $configured */
        $configured = \is_array($config['modules'] ?? null) ? $config['modules'] : [];
        foreach ($configured as $name => $module) {
            $name = (string) $name;
            $modules[$name] = new ModuleUnderGate(
                $name,
                self::text($module['page'] ?? null) ?? throw new \InvalidArgumentException(\sprintf('devkit.fleet.modules.%s must say where the module first answers (page).', $name)),
                self::text($module['catalogue_slug'] ?? null),
                self::text($module['database_env'] ?? null),
                self::text($module['migrate_command'] ?? null),
                self::text($module['repository'] ?? null),
                (bool) ($module['private'] ?? false),
            );
        }

        return new self(
            self::words($config['official_modules'] ?? []),
            self::words($config['private_modules'] ?? []),
            self::words($config['after_migrate'] ?? []),
            $modules,
            self::text($config['area_name'] ?? null) ?? '',
        );
    }

    /**
     * The modules to gate, resolved: the names asked for, or both configured
     * lists when nothing was asked for, each turned into the module the config
     * describes.
     *
     * @param list<string> $only the names asked for on the command line, or [] for the configured lists
     *
     * @return list<ModuleUnderGate>
     */
    public function resolve(array $only = []): array
    {
        $names = [] !== $only ? $only : [...$this->officialModules, ...$this->privateModules];

        $resolved = [];
        foreach ($names as $name) {
            $resolved[] = $this->modules[$name]
                ?? throw new \InvalidArgumentException(\sprintf('The fleet knows no module "%s". Describe it under devkit.fleet.modules first; the ones it knows are: %s.', $name, implode(', ', array_keys($this->modules))));
        }

        return $resolved;
    }

    private static function text(mixed $value): ?string
    {
        if (null === $value) {
            return null;
        }

        if (!\is_string($value)) {
            throw new \InvalidArgumentException('Every devkit.fleet value that names something is a string.');
        }

        return '' === $value ? null : $value;
    }

    /**
     * @return list<string>
     */
    private static function words(mixed $value): array
    {
        if (!\is_array($value)) {
            return [];
        }

        return array_values(array_map(
            static fn (mixed $word): string => \is_string($word)
                ? $word
                : throw new \InvalidArgumentException('Every devkit.fleet list holds strings.'),
            $value,
        ));
    }
}
