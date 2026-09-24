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

namespace Uhifadhi\Devkit\Tests\Unit\Fleet;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Uhifadhi\Devkit\Fleet\HeadVersion;

final class HeadVersionTest extends TestCase
{
    /**
     * A version line is a branch, and composer is told so with `.x-dev` —
     * `@dev` alone would still prefer a tag, and a tag is what head mode must
     * not test.
     */
    #[DataProvider('branches')]
    public function testABranchBecomesTheConstraintComposerUnderstands(string $branch, string $expected): void
    {
        self::assertSame($expected, HeadVersion::fromBranch($branch));
    }

    /** @return iterable<string, array{string, string}> */
    public static function branches(): iterable
    {
        yield 'a version line' => ['0.1', '0.1.x-dev'];
        yield 'a later line' => ['0.10', '0.10.x-dev'];
        yield 'a major line' => ['12.4', '12.4.x-dev'];
        yield 'a working branch' => ['zones-import', 'dev-zones-import'];
        yield 'a namespaced branch' => ['feature/zones', 'dev-feature/zones'];
        yield 'whitespace off git' => ["0.3\n", '0.3.x-dev'];
        // Not a version line: three parts is a tag's shape, not a branch's.
        yield 'not a line' => ['0.1.4', 'dev-0.1.4'];
    }

    public function testADetachedHeadIsRefusedByName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('detached HEAD');

        HeadVersion::fromBranch('HEAD');
    }

    public function testNoBranchAtAllIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        HeadVersion::fromBranch('  ');
    }
}
