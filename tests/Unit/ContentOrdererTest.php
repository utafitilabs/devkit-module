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

namespace Uhifadhi\Devkit\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Uhifadhi\Contracts\Devkit\ContentProviderInterface;
use Uhifadhi\Devkit\Content\ContentOrderer;
use Uhifadhi\Devkit\Content\ContentOrderingException;
use Uhifadhi\Devkit\Tests\Fixtures\FakeContentProvider;

/**
 * The topological sort behind fixtures:seed: dependsOn() edges become a run
 * order where every provider follows the content it is built on, ties break to
 * registration order, and all three ways the graph can be malformed fail loudly.
 */
final class ContentOrdererTest extends TestCase
{
    /**
     * @param list<ContentProviderInterface> $providers
     *
     * @return list<string> the keys in the order they were sorted into
     */
    private function orderedKeys(array $providers): array
    {
        return array_map(
            static fn (ContentProviderInterface $p): string => $p->key(),
            new ContentOrderer()->order($providers),
        );
    }

    public function testItSeedsADependencyBeforeTheProviderThatNeedsIt(): void
    {
        // incident dependsOn area+patrol; patrol dependsOn area — declared in an
        // order that is NOT already valid, so only a real sort produces a run.
        $order = $this->orderedKeys([
            new FakeContentProvider('incident', ['area', 'patrol']),
            new FakeContentProvider('patrol', ['area']),
            new FakeContentProvider('area'),
        ]);

        self::assertSame(['area', 'patrol', 'incident'], $order);
    }

    public function testIndependentProvidersKeepRegistrationOrder(): void
    {
        // Nothing depends on anything: the sort must not shuffle them.
        $order = $this->orderedKeys([
            new FakeContentProvider('area'),
            new FakeContentProvider('team'),
            new FakeContentProvider('storage'),
        ]);

        self::assertSame(['area', 'team', 'storage'], $order);
    }

    public function testAnEmptyGraphOrdersToNothing(): void
    {
        self::assertSame([], $this->orderedKeys([]));
    }

    public function testDuplicateKeysAreRefused(): void
    {
        $this->expectException(ContentOrderingException::class);
        $this->expectExceptionMessage('both declare the key "area"');

        $this->orderedKeys([
            new FakeContentProvider('area'),
            new FakeContentProvider('area'),
        ]);
    }

    public function testAnUnknownDependencyIsRefused(): void
    {
        $this->expectException(ContentOrderingException::class);
        $this->expectExceptionMessage('dependsOn "area", but no installed provider declares that key');

        $this->orderedKeys([
            new FakeContentProvider('incident', ['area']),
        ]);
    }

    public function testADirectCycleIsRefused(): void
    {
        $this->expectException(ContentOrderingException::class);
        $this->expectExceptionMessage('dependency cycle');

        $this->orderedKeys([
            new FakeContentProvider('a', ['b']),
            new FakeContentProvider('b', ['a']),
        ]);
    }

    public function testTheCycleMessageNamesTheLoop(): void
    {
        try {
            $this->orderedKeys([
                new FakeContentProvider('a', ['b']),
                new FakeContentProvider('b', ['c']),
                new FakeContentProvider('c', ['a']),
            ]);
            self::fail('Expected a ContentOrderingException for the a -> b -> c -> a cycle.');
        } catch (ContentOrderingException $e) {
            self::assertStringContainsString('a -> b -> c -> a', $e->getMessage());
        }
    }
}
