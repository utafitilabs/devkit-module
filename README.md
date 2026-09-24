# uhifadhi/devkit-module

Devkit is **the** dev-only module for a uhifadhi installation. It installs through
`require-dev`, so it — and everything it registers — is absent from a production
build. `require-dev` is the production firewall.

Its job is to be a **collector**. Other modules ship **inert provider classes**
declared through two contracts the core publishes, and devkit gathers
them and materialises real developer tools — only in a dev install, because that
is the only place devkit exists.

## Contents

- [What it collects](#what-it-collects)
- [`fixtures:demo` — demo content in dependency order](#fixturesdemo--demo-content-in-dependency-order)
- [Descriptor commands](#descriptor-commands)
- [`area:zones:import` — a zoning scheme from a file](#areazonesimport--a-zoning-scheme-from-a-file)
- [`fleet:gate` — the whole fleet, installed](#fleetgate--the-whole-fleet-installed)
- [How a module contributes](#how-a-module-contributes)
- [The dev console](#the-dev-console)
- [Why the contracts live in the core, not here](#why-the-contracts-live-in-the-core-not-here)
- [Installing it](#installing-it)

## What it collects

Two contracts, both published by `uhifadhi/uhifadhi` under `Uhifadhi\Contracts\Devkit\`:

- `ContentProviderInterface` — a slice of demo content to seed, identified by a
  `key()` and ordered against other slices by `dependsOn()`.
- `CommandProviderInterface` — a bag of `CommandDescriptor`s, each a name, a help
  line, and a `\Closure(list<string>, CommandIo): int` that does the work.
- `CommandIo` — the three streams that closure speaks through: `write()` to
  stdout, `error()` to stderr, `readLine()` from stdin. devkit binds them to the
  console's real input and output, so a handler that has never heard of
  `--quiet` obeys it.

A module tags its provider services and devkit `tagged_iterator`s them.

## `fixtures:demo` — demo content in dependency order

`fixtures:demo` collects every tagged `ContentProviderInterface`, **topologically
sorts** them on their `dependsOn()` edges, and calls `load()` on each in order,
printing each slice's label and description.

Ordering is a dependency graph, not a priority number: an incidents slice that
`dependsOn` `['area', 'patrol']` is seeded strictly after both. The sort refuses
a malformed graph loudly — a duplicate key, a dependency no provider supplies, or
a cycle each fail with a message naming the offending keys, rather than guessing
an order that would break far from its cause.

An installation with no providers registered seeds nothing and exits cleanly.

## Descriptor commands

For every tagged `CommandProviderInterface`, each `CommandDescriptor` it returns
becomes a real Symfony console command. Because a descriptor's name is only known
at runtime, devkit registers them through a command **loader** that decorates the
framework's own — it answers for every descriptor name and delegates everything
else, including `fixtures:demo`, to the inner loader. The wrapper passes the
argument tail to the descriptor's closure as a `list<string>`, hands it a
`CommandIo` bound to this execution's input and output, and uses the returned int
as the command's exit code.

A module's handler therefore never touches `\STDOUT` or `\STDIN` itself. It has
no console to write to and reaching for the file descriptor would escape the one
it was given — output that ignores `--quiet`, that a caller capturing the
command cannot see, and that appears uninvited in a test run.

## `area:zones:import` — a zoning scheme from a file

```console
bin/console area:zones:import <area-uuid> <file.geojson>
```

One GeoJSON FeatureCollection, one feature per zone, into an area that already
exists. The core carries the import — the name property, the dropped altitudes,
WGS84, the zone invariant, what may be added to a set that already has zones in
it — and ships no command for it, because an installation's console holds one
command and the screen that will offer this is waiting on its design. Devkit is
where the path belongs in the meantime, and it is absent from a production build.

An import adds and never overwrites. Every feature that fits arrives, and the
ones that do not are tabled with their reason — a name the area already carries,
a ring over a zone that is already there, ground outside the boundary — under a
`N added · M skipped` summary and a zero exit, because nothing was destroyed to
make room for what did arrive.

The summary also names the property the zone names came out of and every property
the file carried that the import read past, so a `description` or a merge field
nobody stored is stated rather than silently lost.

A non-zero exit is reserved for the file the import refused whole — unreadable,
no property naming every feature, coordinates that are not degrees — and it
prints the import's own sentence. There is no `--dry-run`: the core's service
previews a file through its own plan, which is what the zones screen confirms
against, and a second shape of that from the console would be a second answer to
one question.

## `fleet:gate` — the whole fleet, installed

```console
bin/console fleet:gate                 # the fleet as published
bin/console fleet:gate --mode=head     # the fleet as the sibling checkouts have it
bin/console fleet:gate --dry-run       # the plan, and none of it performed
```

The gate creates a project from the starter with the starter's own commands and
installs the whole platform into it, one official module at a time: a fresh
database, the migrations, the catalogue command, the first administrator, a
sign-in over HTTP, an area, and then each module required, migrated, found in the
catalogue, smoke-tested, switched on for the area and opened. The step that
breaks is the step the report names, and nothing after it runs.

It answers the one question no other suite can: *does the released fleet install
and run as one product?* Every other suite in the fleet tests one package at its
own `HEAD`, and every build can be green while the install is broken, because no
build performs the install.

What the fleet is made of is **configuration** — `devkit.fleet.official_modules`,
`devkit.fleet.after_migrate` and a `devkit.fleet.modules` entry per module — so
adding an official module is a line in an installation's `devkit.yaml` and no
release of this bundle. The installation the command is run from is never the
project it judges: that one is created from nothing on every run.

The full option table, the configuration and the release rhythm are in
[docs/fleet-gate.md](docs/fleet-gate.md); the workflow that is the only thing
allowed to mint a tag is in [docs/release-workflow.md](docs/release-workflow.md).

## How a module contributes

A module's provider is an ordinary tagged service. The tag is added as a
**literal string** in the module's own service config — a module cannot reference
devkit's constants, because devkit is absent from the production build the module
also ships into:

```php
// in an always-installed module's config/services.php
$services->set('patrol.devkit.content', PatrolContentProvider::class)
    ->args([service('doctrine.orm.entity_manager')])
    ->tag('uhifadhi.devkit.content_provider');   // == UhifadhiDevkitBundle::CONTENT_PROVIDER_TAG

$services->set('patrol.devkit.commands', PatrolCommandProvider::class)
    ->tag('uhifadhi.devkit.command_provider');   // == UhifadhiDevkitBundle::COMMAND_PROVIDER_TAG
```

In production the tag has no consumer (devkit is not installed), so the provider
is inert data. In a dev install devkit is present and collects it.

## The dev console

Devkit also ships a **dev-only inspector console** — the home a module builder
leaves open on a second monitor. It renders in the core shell's frame
and has four surfaces, reached under `/_devkit`:

- **Commands** — the assembled dev commands and demo-content loaders, grouped by
  the module that contributed them (the same collection `fixtures:demo` and the
  descriptor commands are built from, seen from the side).
- **Modules** — the installed fleet as one register: each package's version, the
  core it pins (`Composer\Semver` against the installed `uhifadhi/uhifadhi`), its
  declared permissions and stamped routes, and its DB-free reach classification.
- **Doctor** — the compatibility matrix and the findings that turn it into
  pass / warn / fail. Devkit computes the checks it can read (pins the core, no
  `dev-main` marker, routes stamped) and **flags the rest as deferred** rather
  than faking them green.
- **Wiring** — the tag inspector: for every contribution point the platform
  defines, who is registered and how many collected.

The console **reads**; it runs nothing (v1). The Run affordances are drawn
deliberately inert — commands run from the CLI.

Two firewalls keep it out of production. The first is `require-dev` (devkit is
not in a production build at all); the second is a `%kernel.debug%` guard in the
controller. A dev application makes the surfaces reachable by importing the
route resource in a `when@dev` block it owns:

```yaml
# config/routes/devkit.yaml (your application)
when@dev:
    devkit:
        resource: '@UhifadhiDevkitBundle/config/routes/console.php'
```

The introspection is DB-free: everything reads Composer, the router and the
tagged services. The one thing it cannot read standalone — the exact **per-area**
on/off count — needs the registry's per-area ledger (a database) and the host's list
of areas, and is flagged deferred on the Modules surface rather than faked.

## Why the contracts live in the core, not here

The inert provider classes ship inside always-installed modules, so their
`implements` clause must resolve at runtime **even when devkit is absent**. An
interface those modules point at therefore has to live in a package they always
have — `uhifadhi/uhifadhi` — and devkit depends on the same interfaces to
collect the providers. Neither side depends on the other. See the core's
`src/Uhifadhi/Contracts/docs/devkit-contracts.md`.

## Installing it

```console
composer require --dev "uhifadhi/devkit-module:^0.1@dev"
```

Neither package is on Packagist and neither has a stable tag yet, so the
installation names where both come from and asks for the dev constraint
explicitly. Composer reads `repositories` from the root package only — an entry
in a dependency's own `composer.json` is ignored — so both lines belong in the
application's:

```json
"repositories": [
    { "type": "vcs", "url": "https://github.com/utafitilabs/uhifadhi" },
    { "type": "vcs", "url": "https://github.com/utafitilabs/devkit-module" }
]
```

The commands the installed modules describe appear on the console at once,
including the core's own:

```console
bin/console team:user:create
bin/console fixtures:demo
```

The first is how an installation gets its first administrator — the one account
no screen can make, because every screen is behind the sign-in it does not yet
have. It asks for whatever it was not told, and the passphrase is never echoed:

```console
$ bin/console team:user:create
Email address: ada@example.test
First name: Ada
Last name: Mwangi
Tier — super-admin, admin, staff [super-admin]:
Passphrase (not shown):
Created Ada Mwangi <ada@example.test> as Super Admin.
```

The prompts and the passphrase both come through `Devkit\CommandIo`, wired here
to the console's own input and error streams — `readSecret()` asks through
Symfony's `QuestionHelper` with `Question::setHidden(true)`, so what is typed
appears nowhere, and neither the question nor the answer touches standard
output.

A tail naming all three is asked nothing, which is what a provisioning script
wants — and there `readSecret()` reads the piped line plainly, because a pipe
has no echo to switch off:

```console
printf '%s' "$PASSPHRASE" | bin/console team:user:create ada@example.test Ada Mwangi
bin/console team:user:create ada@example.test Ada Mwangi --tier=staff --password="$PASSPHRASE"
```
