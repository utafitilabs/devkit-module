# The release workflow

A tag in the fleet is minted by one thing: a **Release** workflow in the
repository being tagged, dispatched by hand with a version. It gates the fleet at
`HEAD`, tags only if that is green, waits for Packagist, and then gates the fleet
as published.

`.github/workflows/release.yml` in this repository is the reference
implementation. Every other fleet repository — the starter, the core and each
module — gets the same file, with two lines changed.

## Contents

- [Why a workflow and not a person](#why-a-workflow-and-not-a-person)
- [What it does](#what-it-does)
- [Copying it to another fleet repository](#copying-it-to-another-fleet-repository)
- [What decides the mechanisms](#what-decides-the-mechanisms)
- [The file](#the-file)

## Why a workflow and not a person

A tag is a promise that the fleet installs. A person tagging locally makes that
promise from a working tree, with whatever is installed in it, against whatever
database happens to be running. The workflow makes it from a clean runner, with
every fleet repository checked out at its current line, and it cannot skip the
gate: the tag is a step that only runs after the gate passed.

And the released gate afterwards is the half a person forgets. The tag is not
done until the *published* fleet installs, which nobody can know until Packagist
has handed the version out.

## What it does

1. **Checks out the workspace** — this repository at the dispatched ref, and every
   other fleet repository at its default branch, laid out as sibling checkouts
   under `fleet/`, exactly as a developer's workspace is.
2. **Builds a host** — an ordinary installation from the starter with devkit at
   `HEAD` in it, through a composer `path` repository. This is what runs
   `fleet:gate`; it is never the project under test, which the gate creates from
   nothing on every run.
3. **`fleet:gate --mode=head`** — the fleet as it would be if everything were
   tagged right now, with this branch in it. A red run ends the workflow and
   **nothing is tagged**.
4. **`gh release create v<version>`** — the tag and the GitHub release, on the
   branch the dispatch ran from.
5. **Waits for Packagist** — polls the package's p2 metadata for the new version,
   bounded at ten minutes, because released mode resolves against exactly that
   file.
6. **`fleet:gate`** (released) — the fleet as published. A red run here leaves the
   tag standing, fails the workflow, and says in an annotation what that means:
   the tag exists and the fleet it belongs to does not install. The cure is
   another release, not a deleted tag.

## Copying it to another fleet repository

Copy `.github/workflows/release.yml` verbatim and change the two `env` lines:

```yaml
env:
  PACKAGE: uhifadhi/patrol-module     # what this repository is on Packagist
  CHECKOUT: patrol-module             # the directory name in the workspace
```

Nothing else changes. Each checkout step skips itself where that repository is
the one the workflow lives in, and the starter uses `CHECKOUT: skeleton`, the
core `CHECKOUT: uhifadhi`.

A **private** repository of the managed-hosting tier adds `--private-module=` to
both gate steps and a token with `Contents: read` on the private repositories to
the checkout steps that need one.

**Do not add the workflow by hand to a repository and then tag by hand anyway.**
The point of the file is that the tag is unreachable except through the gate.

## What decides the mechanisms

| Mechanism | Source |
|---|---|
| `workflow_dispatch` with a `version` input | <https://docs.github.com/en/actions/reference/workflows-and-actions/events-that-trigger-workflows#workflow_dispatch> |
| reading it as `${{ inputs.version }}` | <https://docs.github.com/en/actions/reference/workflows-and-actions/contexts#inputs-context> |
| `permissions: contents: write` for tagging | <https://docs.github.com/en/actions/how-tos/secure-your-work/automatic-token-authentication> |
| `gh release create`, and `GH_TOKEN` in Actions | <https://cli.github.com/manual/gh_release_create> · <https://docs.github.com/en/actions/how-tos/write-workflows/use-github-cli> |
| the p2 metadata a `composer require` reads | <https://packagist.org/apidoc> |
| a composer `path` repository for the host | <https://getcomposer.org/doc/05-repositories.md#path> |

## The file

```yaml
name: Release

# THE ONLY THING THAT MINTS A TAG IN THIS REPOSITORY.
#
# Dispatched by hand with a version. It gates the fleet at HEAD with this
# repository's current branch in it, tags and releases only if that is green,
# waits for Packagist to hand the new version out, and then gates the fleet as
# PUBLISHED. A red released run leaves the tag standing and the run red, saying
# so: the tag exists, the fleet it belongs to does not install.
#
#   workflow_dispatch and its inputs
#     "To enable a workflow to be triggered manually, you need to configure the
#      workflow_dispatch event. … you can configure custom-defined input
#      properties"
#     @see https://docs.github.com/en/actions/reference/workflows-and-actions/events-that-trigger-workflows#workflow_dispatch
#     Read as `${{ inputs.<name> }}`:
#     @see https://docs.github.com/en/actions/reference/workflows-and-actions/contexts#inputs-context
#
#   permissions: contents: write
#     The default GITHUB_TOKEN is read-only for a repository's contents; creating
#     a tag and a release writes them, and a job asks for exactly what it needs
#     and nothing else.
#     @see https://docs.github.com/en/actions/how-tos/secure-your-work/automatic-token-authentication
#     @see https://docs.github.com/en/actions/reference/workflows-and-actions/workflow-syntax#permissions
#
#   gh release create
#     "Create a new GitHub Release for a repository. A list of asset files may be
#      given to upload to the new release. To create a release from an annotated
#      tag, first create one locally with git, push the tag to GitHub, then run
#      this command."
#     gh is preinstalled on the runners and reads GH_TOKEN from the environment.
#     @see https://cli.github.com/manual/gh_release_create
#     @see https://docs.github.com/en/actions/how-tos/write-workflows/use-github-cli
#
#   the wait for Packagist
#     The metadata a `composer require` reads is the p2 file for the package;
#     polling it for the new version is polling exactly what the released gate
#     will resolve against.
#     @see https://packagist.org/apidoc

on:
  workflow_dispatch:
    inputs:
      version:
        description: 'The version to tag, without the v — for example 0.2.0'
        required: true
        type: string

permissions:
  contents: write

# THE PACKAGE THIS REPOSITORY IS, and the module word the gate knows it by. These
# two lines are the whole of what changes when this file is copied to another
# fleet repository (see docs/release-workflow.md).
env:
  PACKAGE: uhifadhi/devkit-module
  CHECKOUT: devkit-module

jobs:
  release:
    name: Gate, tag, gate again
    runs-on: ubuntu-latest

    # The gate creates its own databases on this server and drops them first.
    services:
      postgres:
        image: ghcr.io/utafitilabs/postgis:17
        env:
          POSTGRES_USER: app
          POSTGRES_PASSWORD: app
          POSTGRES_DB: postgres
        ports:
          - 5432:5432
        options: >-
          --health-cmd "pg_isready -U app"
          --health-interval 10s
          --health-timeout 5s
          --health-retries 5

    env:
      # Head mode reads the sibling checkouts from here, exactly as a developer's
      # workspace is laid out.
      WORKSPACE: ${{ github.workspace }}/fleet
      GATE_DATABASE_URL: "postgresql://app:app@127.0.0.1:5432/fleet_gate?serverVersion=17&charset=utf8"

    steps:
      # ── the workspace: every fleet repository at its current line ──────────
      - name: Check out this repository
        uses: actions/checkout@v4
        with:
          fetch-depth: 0
          path: fleet/${{ env.CHECKOUT }}

      - name: Check out the starter
        uses: actions/checkout@v4
        with:
          repository: utafitilabs/skeleton
          path: fleet/skeleton

      - name: Check out the core
        uses: actions/checkout@v4
        with:
          repository: utafitilabs/uhifadhi
          path: fleet/uhifadhi

      # No ref on any of these: a repository's default branch IS its current
      # version line. Each step skips itself where that repository is the one
      # this workflow lives in, which is already checked out above at the
      # dispatched ref — so copying this file changes the two env lines and
      # nothing else.
      - name: Check out storage-module
        if: env.CHECKOUT != 'storage-module'
        uses: actions/checkout@v4
        with:
          repository: utafitilabs/storage-module
          path: fleet/storage-module

      - name: Check out patrol-module
        if: env.CHECKOUT != 'patrol-module'
        uses: actions/checkout@v4
        with:
          repository: utafitilabs/patrol-module
          path: fleet/patrol-module

      - name: Check out incident-module
        if: env.CHECKOUT != 'incident-module'
        uses: actions/checkout@v4
        with:
          repository: utafitilabs/incident-module
          path: fleet/incident-module

      - name: Check out roster-module
        if: env.CHECKOUT != 'roster-module'
        uses: actions/checkout@v4
        with:
          repository: utafitilabs/roster-module
          path: fleet/roster-module

      - name: Check out devkit-module
        if: env.CHECKOUT != 'devkit-module'
        uses: actions/checkout@v4
        with:
          repository: utafitilabs/devkit-module
          path: fleet/devkit-module

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'
          extensions: ctype, iconv, gd, fileinfo, pdo_pgsql
          coverage: none
          tools: composer:v2

      # ── the host the gate is run FROM ──────────────────────────────────────
      # An ordinary installation with devkit at HEAD in it. It is not the project
      # under test: the gate creates that one itself, from nothing, on every run.
      - name: Build the host the gate runs from
        run: |
          composer create-project uhifadhi/skeleton host --no-interaction --no-progress
          cd host
          composer config repositories.devkit '{"type":"path","url":"../fleet/devkit-module","options":{"symlink":true}}'
          composer require --dev "uhifadhi/devkit-module:*@dev" --no-interaction --no-progress
        env:
          COMPOSER_MEMORY_LIMIT: '-1'

      # ── 1. the fleet at HEAD, with this branch in it ───────────────────────
      - name: Fleet gate — head
        run: |
          php host/bin/console fleet:gate \
            --mode=head \
            --workspace="$WORKSPACE" \
            --database-url="$GATE_DATABASE_URL"
        env:
          DATABASE_URL: ${{ env.GATE_DATABASE_URL }}
          COMPOSER_MEMORY_LIMIT: '-1'

      # ── 2. the tag, and only now ───────────────────────────────────────────
      - name: Tag and release
        working-directory: fleet/${{ env.CHECKOUT }}
        run: |
          gh release create "v${{ inputs.version }}" \
            --title "v${{ inputs.version }}" \
            --target "${{ github.ref_name }}" \
            --generate-notes
        env:
          GH_TOKEN: ${{ github.token }}

      # ── 3. Packagist has to have it before released mode can resolve it ────
      - name: Wait for Packagist to hand out the version
        run: |
          for attempt in $(seq 1 40); do
            if curl -fsS "https://repo.packagist.org/p2/${PACKAGE}.json" \
                 | grep -q "\"version\":\"v\\?${{ inputs.version }}\""; then
              echo "Packagist has v${{ inputs.version }} after ${attempt} attempts."
              exit 0
            fi
            sleep 15
          done
          echo "::error::Packagist did not hand out ${PACKAGE} v${{ inputs.version }} within ten minutes."
          exit 1

      # ── 4. the fleet as PUBLISHED. The tag stays; a red run says it is wrong ──
      - name: Fleet gate — released
        id: released
        run: |
          php host/bin/console fleet:gate \
            --mode=released \
            --database-url="$GATE_DATABASE_URL"
        env:
          DATABASE_URL: ${{ env.GATE_DATABASE_URL }}
          COMPOSER_MEMORY_LIMIT: '-1'

      - name: Say what a red released run means
        if: failure() && steps.released.conclusion == 'failure'
        run: |
          echo "::error::v${{ inputs.version }} is tagged and released, and the PUBLISHED fleet did not install. The tag stands; fix the fleet and release again."
```
