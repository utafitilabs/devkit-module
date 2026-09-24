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

use Symfony\Component\Process\Process;

/**
 * The branch a checkout has out, read from git, turned into a composer version
 * by {@see HeadVersion}.
 *
 * Nobody switches branches to run the gate: it tests what is being worked on.
 *
 * @see https://symfony.com/doc/current/components/process.html
 * @see vendor/symfony/process/Process.php — mustRun() throws ProcessFailedException
 *      on a non-zero exit, which is the reading wanted here: a directory that is
 *      not a git repository is a mistake in the invocation, not a version.
 */
final readonly class GitCheckoutVersions implements CheckoutVersionsInterface
{
    public function versionOf(string $checkout): string
    {
        if (!is_dir($checkout.'/.git')) {
            throw new \RuntimeException(\sprintf('Head mode reads %s as a git repository, and there is none there.', $checkout));
        }

        $branch = new Process(['git', 'rev-parse', '--abbrev-ref', 'HEAD'], $checkout)->mustRun()->getOutput();

        return HeadVersion::fromBranch($branch);
    }
}
