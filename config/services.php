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

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Uhifadhi\Devkit\Command\DemoCommand;
use Uhifadhi\Devkit\Content\ContentOrderer;
use Uhifadhi\Devkit\UhifadhiDevkitBundle;

/*
 * The collector's static wiring.
 *
 * PHP (not YAML) on purpose: a reusable bundle must not force symfony/yaml onto
 * hosts, and FQCN references stay refactor-safe and phpstan-checked. Everything
 * is defined EXPLICITLY — no autowire(), no autoconfigure(), ids prefixed with
 * the bundle alias — because this bundle is installed by other projects via
 * Composer, which is what Symfony calls a reusable bundle:
 *
 *   "Services should not use autowiring or autoconfiguration. Instead, all
 *    services should be defined explicitly."
 *   "If the bundle defines services, they must be prefixed with the bundle alias."
 *   — https://symfony.com/doc/current/bundles/best_practices.html
 *
 * The `console.command` tag below is applied BY HAND for the same reason, and it
 * carries no `command:` attribute on purpose. The docs present the tag as the
 * fallback "If you can't use PHP attributes", and add: "When registering commands
 * manually with the `console.command` tag, set the command name in the `command`
 * attribute of the tag to get the same [lazy] behavior"
 * (https://symfony.com/doc/current/console.html). The framework's own source
 * settles what that leaves open for a class that DOES carry #[AsCommand]:
 * vendor/symfony/console/DependencyInjection/AddConsoleCommandPass.php reads the
 * attribute off the reflection class (`getCommandAttribute()`), resolves the name
 * as `$tags[0]['command'] ?? $defaultName`, takes the description from the
 * attribute too, and builds the `LazyCommand` wrapper from both — so a
 * hand-tagged service whose class has the attribute is named, described and lazy
 * without either string being retyped in the tag. The pass is registered at
 * TYPE_BEFORE_REMOVING by vendor/symfony/console/ConsoleBundle.php.
 */
return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    // The topological sorter behind fixtures:demo. Stateless, so one shared
    // instance serves every run.
    $services->set('devkit.content_orderer', ContentOrderer::class);

    /*
     * `fixtures:demo` — collects every tagged ContentProviderInterface and seeds
     * them in dependsOn() order. The iterator is EMPTY on an installation that
     * has registered no providers, and an empty seed is the correct reading of
     * that rather than an error (see DemoCommand).
     */
    $services->set('devkit.command.demo', DemoCommand::class)
        ->args([
            tagged_iterator(UhifadhiDevkitBundle::CONTENT_PROVIDER_TAG),
            service('devkit.content_orderer'),
        ])
        ->tag('console.command');

    /*
     * The CommandProviderInterface half of the contract — the modules' descriptors
     * becoming real console commands — is wired NOT here but in
     * DecorateCommandLoaderPass. Their names are only known at runtime, so it is
     * a command LOADER decorating the framework's own; and that loader is created
     * so late in compilation (symfony/console's AddConsoleCommandPass) that a
     * declared `->decorate()` here would reference a service that does not exist
     * yet. The pass does the decoration by hand at the right moment.
     */

    /*
     * THE DEV CONSOLE'S UI (slice 2) is wired in config/console.php, imported by
     * UhifadhiDevkitBundle::loadExtension ONLY where twig-bundle is present — a
     * real devkit install, which requires it. The collector runs without a UI
     * (no router, no twig), and its wiring must not drag one in.
     */
};
