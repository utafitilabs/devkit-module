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

use Uhifadhi\Contracts\Devkit\ContentProviderInterface;

/**
 * Turns the collected {@see ContentProviderInterface} services into the order
 * `fixtures:seed` seeds them in — a TOPOLOGICAL SORT over the dependsOn() edges.
 *
 * The contract deliberately expresses ordering as dependencies rather than a
 * priority integer ("area needs to exist before an incident hangs on it"), so
 * devkit has to do the real work of turning those edges into a linear order.
 * That is this class. It is a stateless service: every run is self-contained, so
 * a compiled container can hand the same instance to the command each time.
 *
 * The sort is a depth-first post-order walk. Providers are visited in the order
 * the container collected them (tag registration order), and each provider's
 * dependencies are emitted before it — so among providers that do NOT depend on
 * each other the original order is preserved, which keeps the seed order stable
 * and readable rather than shuffled by the algorithm.
 *
 * All three ways the graph can be malformed are refused, not papered over
 * ({@see ContentOrderingException}): a duplicate key, a dependency no provider
 * supplies, and a cycle.
 */
final class ContentOrderer
{
    /**
     * @param iterable<ContentProviderInterface> $providers
     *
     * @return list<ContentProviderInterface> the providers in an order where
     *                                        every provider follows all of its
     *                                        dependsOn() dependencies
     *
     * @throws ContentOrderingException on a duplicate key, an unknown
     *                                  dependency, or a dependency cycle
     */
    public function order(iterable $providers): array
    {
        /** @var array<string, ContentProviderInterface> $byKey */
        $byKey = [];
        /** @var list<string> $keysInOrder */
        $keysInOrder = [];

        foreach ($providers as $provider) {
            $key = $provider->key();
            if (isset($byKey[$key])) {
                throw ContentOrderingException::duplicateKey($key);
            }
            $byKey[$key] = $provider;
            $keysInOrder[] = $key;
        }

        /** @var list<ContentProviderInterface> $sorted */
        $sorted = [];
        /** @var array<string, bool> $done true once a key has been emitted */
        $done = [];

        foreach ($keysInOrder as $key) {
            $this->visit($key, $byKey, $sorted, $done, []);
        }

        return $sorted;
    }

    /**
     * @param array<string, ContentProviderInterface> $byKey
     * @param list<ContentProviderInterface>          $sorted
     * @param array<string, bool>                     $done
     * @param list<string>                            $path   keys currently on
     *                                                        the recursion stack,
     *                                                        used to spot a cycle
     */
    private function visit(string $key, array $byKey, array &$sorted, array &$done, array $path): void
    {
        if (isset($done[$key])) {
            return;
        }

        if (\in_array($key, $path, true)) {
            $cycleStart = array_search($key, $path, true);
            /** @var list<string> $cycle */
            $cycle = \array_slice($path, \is_int($cycleStart) ? $cycleStart : 0);
            $cycle[] = $key;

            throw ContentOrderingException::cycle($cycle);
        }

        $path[] = $key;

        foreach ($byKey[$key]->dependsOn() as $dependency) {
            if (!isset($byKey[$dependency])) {
                throw ContentOrderingException::unknownDependency($key, $dependency);
            }
            $this->visit($dependency, $byKey, $sorted, $done, $path);
        }

        $done[$key] = true;
        $sorted[] = $byKey[$key];
    }
}
