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

namespace Uhifadhi\Devkit\Content;

/**
 * The seed-content dependency graph could not be resolved into a run order.
 *
 * Every one of the three ways that can happen is a MISTAKE IN A MODULE'S
 * PROVIDER, not a runtime condition to recover from: two providers claiming the
 * same key, a provider naming a dependency nobody supplies, or a cycle. So this
 * fails loudly with a message that names the offending keys, rather than
 * silently guessing an order — a guessed order would seed incidents before the
 * areas they hang on and fail far from the real cause.
 */
final class ContentOrderingException extends \RuntimeException
{
    public static function duplicateKey(string $key): self
    {
        return new self(\sprintf('Two seed-content providers both declare the key "%s". A key is a stable machine identity that other providers name in dependsOn(); it must be unique. A module that seeds two slices ships two providers with two DIFFERENT keys.', $key));
    }

    public static function unknownDependency(string $key, string $missing): self
    {
        return new self(\sprintf('The seed-content provider "%s" dependsOn "%s", but no installed provider declares that key. Install the module that supplies "%s", or correct the dependsOn() entry.', $key, $missing, $missing));
    }

    /**
     * @param list<string> $cycle the keys forming the loop, in the order they
     *                            were entered, closed back onto the first
     */
    public static function cycle(array $cycle): self
    {
        return new self(\sprintf('The seed-content providers form a dependency cycle and cannot be ordered: %s. dependsOn() must describe a one-way "built on top of" relationship; a loop has no first step to seed.', implode(' -> ', $cycle)));
    }
}
