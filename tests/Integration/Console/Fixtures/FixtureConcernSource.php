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

namespace Uhifadhi\Devkit\Tests\Integration\Console\Fixtures;

use Uhifadhi\Contracts\Access\Concern;
use Uhifadhi\Contracts\Access\ConcernSourceInterface;
use Uhifadhi\Contracts\Access\ScopeKind;
use Uhifadhi\Contracts\Access\Verb;

/**
 * A stand-in declaration — what a module tags with `uhifadhi.access.concerns`,
 * so the console's registry has grants to count without a real module bundle
 * for every scenario. One concern, carrying as many verbs as the scenario
 * wants rows for.
 */
final readonly class FixtureConcernSource implements ConcernSourceInterface
{
    /** Every verb, so a scenario can ask for any number of grants up to six. */
    private const array VERBS = [Verb::Read, Verb::Record, Verb::Manage, Verb::Configure, Verb::Delete, Verb::Export];

    public function __construct(
        private string $slug,
        private int $grants,
    ) {
    }

    public function declaredBy(): string
    {
        return ucfirst($this->slug);
    }

    public function concerns(): iterable
    {
        if (0 === $this->grants) {
            return;
        }

        yield new Concern(
            key: $this->slug.'-records',
            label: ucfirst($this->slug).' records',
            description: \sprintf('The records the %s module keeps.', $this->slug),
            verbs: \array_slice(self::VERBS, 0, $this->grants),
            scopeKinds: [ScopeKind::Organization, ScopeKind::Area],
            moduleSlug: $this->slug,
        );
    }
}
