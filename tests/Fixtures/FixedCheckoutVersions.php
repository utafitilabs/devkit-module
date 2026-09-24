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

namespace Uhifadhi\Devkit\Tests\Fixtures;

use Uhifadhi\Devkit\Fleet\CheckoutVersionsInterface;

/**
 * The branch every checkout has out, decided by the test rather than by a git
 * repository on disk — so the plan can be built anywhere.
 */
final readonly class FixedCheckoutVersions implements CheckoutVersionsInterface
{
    public function __construct(private string $version = '0.1.x-dev')
    {
    }

    public function versionOf(string $checkout): string
    {
        return $this->version;
    }
}
