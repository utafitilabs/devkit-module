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
 * A BRANCH NAME AS COMPOSER NAMES IT.
 *
 * Head mode requires each package by the branch its checkout has out, never by
 * `@dev` — `@dev` still prefers a tag where one exists, and a tag is exactly
 * what head mode must not test.
 *
 *   "you must specify a version constraint that looks like this: `v1.x-dev`.
 *    The `.x` is an arbitrary string that Composer requires to tell it that
 *    we're talking about the `v1` branch and not a `v1` tag"
 *
 *   @see https://getcomposer.org/doc/articles/versions.md#branches
 *   @see composer/vendor/composer/semver/src/VersionParser.php — normalizeBranch();
 *        no `extra.branch-alias` is involved.
 *
 * And it installs under a `stable` floor without `@dev` and without editing the
 * created project's minimum-stability:
 *   @see composer/src/Composer/Package/Loader/RootPackageLoader.php —
 *        extractStabilityFlags(): a constraint whose own stability is not stable
 *        sets that package's stability flag.
 *
 * The fleet is branched one way — a branch per version line, named after it —
 * so `0.3` is `0.3.x-dev` and anything else is a working branch, `dev-<branch>`.
 */
final readonly class HeadVersion
{
    public static function fromBranch(string $branch): string
    {
        $branch = trim($branch);

        if ('' === $branch || 'HEAD' === $branch) {
            throw new \InvalidArgumentException('That checkout is on a detached HEAD; check out a branch before gating it.');
        }

        return 1 === preg_match('/^\d+\.\d+$/', $branch) ? $branch.'.x-dev' : 'dev-'.$branch;
    }
}
