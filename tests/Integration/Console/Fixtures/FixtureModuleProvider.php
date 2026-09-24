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

use Uhifadhi\Contracts\ModuleProviderInterface;
use Uhifadhi\Contracts\ModuleProviderTrait;

/**
 * A stand-in module provider — what an always-installed module tags with
 * `uhifadhi.module`, so the console's registry and contribution-point inspector have a fleet to
 * read in a test without a real module bundle for every scenario.
 */
final class FixtureModuleProvider implements ModuleProviderInterface
{
    use ModuleProviderTrait;

    public function __construct(
        private readonly string $slug,
        private readonly string $name,
        private readonly bool $isBase = false,
    ) {
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function category(): string
    {
        return 'pressure';
    }

    public function base(): bool
    {
        return $this->isBase;
    }
}
