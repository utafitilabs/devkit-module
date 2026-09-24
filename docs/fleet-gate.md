# The fleet gate

`fleet:gate` creates a project from the starter with the README's own commands
and installs the whole platform into it, one official module at a time. It is
the last check before a release is done, and the first after one.

## Contents

- [What it proves, and why the other suites cannot](#what-it-proves-and-why-the-other-suites-cannot)
- [The two modes](#the-two-modes)
- [The release rhythm](#the-release-rhythm)
- [Running it](#running-it)
- [Options](#options)
- [What the fleet is, as configuration](#what-the-fleet-is-as-configuration)
- [What it does, step by step](#what-it-does-step-by-step)
- [Why the gate scrubs its environment](#why-the-gate-scrubs-its-environment)
- [Reading a red run](#reading-a-red-run)
- [Adding an official module](#adding-an-official-module)
- [Why it lives here](#why-it-lives-here)

## What it proves, and why the other suites cannot

Every repository in the fleet tests itself at its own `HEAD`: the core's suite,
each module's suite, the core's fleet-conformance job that runs every module
against the core's next commit. None of them performs the starter's install. A
module can be pushed green and never tagged, and the tag `composer require` then
resolves names classes the core no longer has. Every build is green while the
install is broken, because no build ever performs the install.

The fleet gate performs the install. It answers one question — *does the released
fleet install and run as one product?* — and it is the only check whose answer
depends on tags rather than commits.

## The two modes

| | `fleet:gate` | `fleet:gate --mode=head` |
|---|---|---|
| resolves packages from | the published repositories, exactly as the starter's own commands do | the branch each sibling checkout has out (`../uhifadhi`, `../patrol-module`, …), read as git repositories at its last commit — commit before you run it, but never switch branches for it |
| answers | does the released fleet install and run | would the fleet install and run if everything were tagged right now |
| runs | **after any tag** in the fleet — core, starter or module | **before a tag**, while the work is still on a branch |

The steps are identical; only where the packages come from differs.

## The release rhythm

A tag is minted by [the release workflow](release-workflow.md), which performs
this rhythm in one dispatch:

1. Finish the change in the core, the starter or a module. That repository's own
   `composer check` is green.
2. `fleet:gate --mode=head`. Green means the fleet at `HEAD` installs as one
   product with your change in it.
3. Tag.
4. `fleet:gate`. Green means what the repositories now hand out installs as one
   product. **The tag is not done until this is green.**

Step 4 runs after *any* tag in the fleet and installs *every* official module,
never only the one that was tagged: a tag on the core is what flushes out a
module that was adapted on its branch but never released.

## Running it

Needs: PHP, composer with access to the fleet's repositories, and a PostgreSQL
server with PostGIS available. The local test cluster on port 5434 is the
default. Run it from any installation that has devkit:

```bash
php bin/console fleet:gate                    # released mode
php bin/console fleet:gate --mode=head        # head mode
php bin/console fleet:gate --dry-run          # the plan, and none of it performed
```

The installation it is run from is **not** the project it judges: the gate
creates that one from nothing on every run and removes it again.

## Options

| Option | Default | What it is |
|---|---|---|
| `--mode=` | `released` | `released` (the published repositories) or `head` (the sibling checkouts) |
| `--workspace=` | the directory holding this checkout of devkit | head mode: where the sibling checkouts are |
| `--module=` | the configured official list | a module to install instead of that list; repeatable |
| `--private-module=` | none | a managed-hosting module to install as well; repeatable, and `telemetry` brings its own database |
| `--database-url=` | `FLEET_GATE_DATABASE_URL`, else `postgresql://app:app@127.0.0.1:5434/fleet_gate` | the gate's own database. **It is dropped and recreated** |
| `--keep` | off | keep the created project afterwards, for a look |
| `--dry-run` | off | print the plan and run none of it |

**Every database the gate is given is dropped and recreated**, so the defaults
name databases nothing else uses and an override must do the same. A module with
a database of its own takes it from `FLEET_GATE_<ITS VARIABLE>`, then
`<ITS VARIABLE>` — `TELEMETRY_DATABASE_URL` for telemetry — and otherwise a
database named after the gate's own and the module, on the same server.

The default workspace is the directory holding devkit's own checkout, which is
right wherever devkit is the linked sibling a developer is working in. Anywhere
else, `--workspace=` says where the fleet is, and head mode fails by name on the
first checkout that is not there.

## What the fleet is, as configuration

The gate **discovers** what the fleet is made of. Nothing about the module list
is in devkit's code:

```yaml
# config/packages/devkit.yaml
devkit:
    fleet:
        official_modules: ['storage', 'patrol', 'incident', 'roster']
        private_modules: ['telemetry']
        after_migrate: ['doctrine:migrations:migrate', 'registry:sync', 'cache:warmup']
        area_name: 'Kilimani Crater Conservation Area'
        modules:
            storage:   { page: '/files' }
            patrol:    { page: '/areas/%s/modules/patrols',   catalogue_slug: 'patrols' }
            incident:  { page: '/areas/%s/modules/incidents', catalogue_slug: 'incidents' }
            roster:    { page: '/areas/%s/modules/roster',    catalogue_slug: 'roster' }
            telemetry:
                page: '/telemetry'
                private: true
                database_env: 'TELEMETRY_DATABASE_URL'
                migrate_command: 'telemetry:migrate'
                repository: 'https://github.com/utafitilabs/telemetry-module'
```

Those are the shipped defaults; an installation writes the block only to differ.

- **`official_modules`** is the install order: a module that builds on another
  comes after it.
- **`after_migrate`** is what every install is followed by, between the cache
  clear and the asset compile. The migration creates the tables; `registry:sync`
  fills the catalogue the area's module grid reads, which is a command and not a
  side effect of migrating. The day the fleet grows another such command it is a
  line here and no release of devkit.
- **`modules`** is everything the gate has to be *told* about a module. `page` is
  where it first answers once it is on, `%s` being the area's uuid. A module with
  no `catalogue_slug` is infrastructure: it declares no tile and answers
  everywhere at once.

## What it does, step by step

Each step passes or fails on its own line, and the first red one ends the run
with the command and its whole output.

1. **head mode only** — the starter's *Official modules* table lists every module
   this gate installs. The two lists drift silently otherwise.
2. `composer create-project uhifadhi/skeleton` into a fresh temporary directory —
   with the starter's own flags in released mode, and from the starter's checkout
   in head mode, each sibling then pointed at by a `vcs` repository and required
   by the branch it has out.
3. A fresh database for every variable this run owns, and the `.env.local` naming
   them.
4. `cache:clear --no-warmup`, the `after_migrate` commands in order,
   `asset-map:compile`, then `doctrine:schema:validate` twice — for the mapping
   and for the schema — so a package whose entities moved on without their
   migration is caught before anything is served.
5. `team:user:create` for the first administrator; the command ships with the
   core.

The `registry:sync` step is the one the catalogue assertion depends on: a fleet
whose `after_migrate` omits it fails at "the catalogue lists <slug>" for the
first module, because nothing else writes the catalogue — a request reconciles
nothing, and the cache commands read no database.
6. The project is served with PHP's built-in server and the administrator signs
   in over HTTP: the sign-in page answers, the form is submitted, the redirect is
   followed, and the page that lands names them. Then they create the area
   through the form at `/areas/new`.
7. **Once per module, in order** — the `composer require`, the module's own
   database and migration where it keeps one, step 4 again, the catalogue listing
   the module, the project's own `composer test`, the sign-in again, the module
   switched on for the area through the grid's own form, and the module's first
   page answering for the administrator. Storage and telemetry, which have no
   tile, answer at the files hub and the console.

## Why the gate scrubs its environment

The gate is a console command of one installation that runs another project's
composer, console and `composer test`. A child process is handed the parent's
variables unless it is told otherwise, and the parent booted through Dotenv, so
its variables are that installation's `.env` values plus `SYMFONY_DOTENV_VARS` —
the marker naming every variable Dotenv set.

That marker is not inert in the child. Dotenv overwrites a variable the marker
names *even when the process already has a value for it*; only a variable outside
the marker is left alone, which is the whole of "real environment variables win
over `.env` files". Inherited, it made the created project's own `composer test`
go red: phpunit forces `APP_ENV=test`, the project's `.env` then put `dev` back
over it, `.env.test` was never loaded, and the suite stopped at a missing
`KERNEL_CLASS`.

So every child of the gate — every shell step and the built-in server — is given
an environment scrubbed of the parent's: the marker and every variable it names
are *removed*, and with them `APP_DEBUG`, `KERNEL_CLASS` and `SHELL_VERBOSITY`,
which no `.env` writes but a launching context does. The created project's own
`.env` then decides its environment, and the gate answers the same whether it was
launched from an installation, a test kernel or a quiet console.

What the child is given is only the gate's own: composer's memory limit and the
databases this run owns, the same ones written into the project's `.env.local`.

## Reading a red run

The report ends with `FLEET GATE RED at:` and the step, then why, then the exact
command. Two shapes recur:

- **red in released mode, green in head mode** — a package is behind on its tags.
  Tag it and run released mode again.
- **red in both** — the fleet does not fit together at `HEAD`. That is a code
  change in one of the repositories, made with its own suite first.

`--keep` keeps the project directory and the report names it.

## Adding an official module

Add it to `official_modules` in the installation's `devkit.yaml`, after any
module it requires, describe it under `modules`, and add it to the starter's
*Official modules* table. Head mode's first step fails until the table is done. A
private module of the managed-hosting tier goes into `private_modules` instead,
stays out of the starter, and is gated with `--private-module=`.

## Why it lives here

devkit installs through `require-dev`, which is the production firewall, and a
command that creates projects, drops databases and spawns servers belongs behind
it. It is not in the starter, because the starter is copied once into every
installation and a line added there is a line every installation is stuck with.
