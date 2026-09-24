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

namespace Uhifadhi\Devkit\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;

/**
 * `devkit:` — what an installation tells devkit.
 *
 * Only the fleet gate has anything to configure so far, and what it has is the
 * fleet itself: which modules make it up, in what order, and which commands an
 * install is followed by. The gate DISCOVERS the fleet from here rather than
 * carrying it in code, so adding an official module is a line of configuration
 * and no release of this bundle.
 *
 * A static `define()` so the same tree is used by the bundle's `configure()` and
 * by a plain `Processor` in a unit test.
 *
 * @see https://symfony.com/doc/current/bundles/configuration.html
 * @see vendor/symfony/framework-bundle/DependencyInjection/Configuration.php —
 *      the framework's own tree, which is what this is patterned on: children
 *      named in snake_case, every node carrying its default rather than the code
 *      that reads it carrying a fallback.
 */
final class DevkitConfiguration
{
    /** The fleet as it stands: the modules an installer is told to require, in install order. */
    public const array OFFICIAL_MODULES = ['storage', 'patrol', 'incident', 'roster'];

    /** The managed-hosting tier's modules: gated the same way, listed in no installer's table. */
    public const array PRIVATE_MODULES = ['telemetry'];

    /**
     * WHAT FOLLOWS EVERY INSTALL, in order, between the cache clear and the
     * asset compile. The migration creates the tables; `registry:sync` fills the
     * catalogue the area's module grid reads, which is a command and not a side
     * effect of migrating, and it is named here so that the day the fleet grows
     * another such command it is a line of configuration.
     */
    public const array AFTER_MIGRATE = ['doctrine:migrations:migrate', 'registry:sync', 'cache:warmup'];

    /**
     * THE AREA THE GATE WORKS IN is the manual's: the worked example's first
     * area, so the gate's data and the book's are one and the same and no
     * invented or real place ever enters it.
     */
    public const string AREA_NAME = 'Kilimani Crater Conservation Area';

    /**
     * EVERY MODULE THE GATE KNOWS HOW TO INSTALL, keyed by its bare name. `page`
     * is where it first answers once it is on, `%s` being the area's uuid; a
     * module with no `catalogue_slug` is infrastructure, declares no tile and
     * answers everywhere at once.
     */
    public const array MODULES = [
        'storage' => ['page' => '/files'],
        'patrol' => ['page' => '/areas/%s/modules/patrols', 'catalogue_slug' => 'patrols'],
        'incident' => ['page' => '/areas/%s/modules/incidents', 'catalogue_slug' => 'incidents'],
        'roster' => ['page' => '/areas/%s/modules/roster', 'catalogue_slug' => 'roster'],
        'telemetry' => [
            'page' => '/telemetry',
            'private' => true,
            'database_env' => 'TELEMETRY_DATABASE_URL',
            'migrate_command' => 'telemetry:migrate',
            'repository' => 'https://github.com/utafitilabs/telemetry-module',
        ],
    ];

    public static function define(NodeDefinition $root): void
    {
        if (!$root instanceof ArrayNodeDefinition) {
            throw new \LogicException('The devkit configuration is an array node.');
        }

        $root
            ->children()
                ->arrayNode('fleet')
                    ->addDefaultsIfNotSet()
                    ->info('What the fleet gate installs, and in what order.')
                    ->children()
                        ->arrayNode('official_modules')
                            ->info('The modules an installer is told to require, in install order: one that builds on another comes after it.')
                            ->scalarPrototype()->end()
                            ->defaultValue(self::OFFICIAL_MODULES)
                        ->end()
                        ->arrayNode('private_modules')
                            ->info('The managed-hosting tier\'s modules, gated after the official ones.')
                            ->scalarPrototype()->end()
                            ->defaultValue(self::PRIVATE_MODULES)
                        ->end()
                        ->arrayNode('after_migrate')
                            ->info('The console commands every install is followed by, in order.')
                            ->scalarPrototype()->end()
                            ->defaultValue(self::AFTER_MIGRATE)
                        ->end()
                        ->scalarNode('area_name')
                            ->info('The area the gate creates and works in.')
                            ->defaultValue(self::AREA_NAME)
                        ->end()
                        ->arrayNode('modules')
                            ->info('Everything the gate has to be told about a module; the module itself decides the rest.')
                            ->useAttributeAsKey('name')
                            ->arrayPrototype()
                                ->children()
                                    ->scalarNode('page')->isRequired()->info('Where the module first answers once it is on; %s is the area\'s uuid.')->end()
                                    ->scalarNode('catalogue_slug')->defaultNull()->info('The slug the catalogue must list after the install; null for infrastructure.')->end()
                                    ->scalarNode('database_env')->defaultNull()->info('The variable holding this module\'s own database url, where it keeps one.')->end()
                                    ->scalarNode('migrate_command')->defaultNull()->info('What migrates that separate database.')->end()
                                    ->scalarNode('repository')->defaultNull()->info('The repository to name before the require, for a module outside Packagist.')->end()
                                    ->booleanNode('private')->defaultFalse()->info('A module of the managed-hosting tier, in no installer\'s table.')->end()
                                ->end()
                            ->end()
                            ->defaultValue(self::MODULES)
                        ->end()
                    ->end()
                ->end()
            ->end();
    }
}
