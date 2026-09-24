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
 * WHAT VERSION A SIBLING CHECKOUT IS, asked of the checkout itself.
 *
 * An interface because the plan is built before anything runs and must be
 * buildable without a git repository on disk — the planner asks this, the
 * command hands it {@see GitCheckoutVersions}, and a unit test hands it a list.
 */
interface CheckoutVersionsInterface
{
    /**
     * @param string $checkout the directory holding the package's git repository
     *
     * @throws \RuntimeException where there is no readable git repository there
     */
    public function versionOf(string $checkout): string;
}
