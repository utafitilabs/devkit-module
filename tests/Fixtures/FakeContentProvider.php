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

use Uhifadhi\Contracts\Devkit\ContentProviderInterface;

/**
 * A content provider standing in for the inert ones real modules ship: it
 * carries a key and its dependsOn() edges, and on load() runs an optional
 * callback so a test can record the order load() was called in.
 */
final class FakeContentProvider implements ContentProviderInterface
{
    /**
     * @param list<string>            $dependsOn
     * @param (\Closure(): void)|null $onLoad
     */
    public function __construct(
        private readonly string $key,
        private readonly array $dependsOn = [],
        private readonly ?\Closure $onLoad = null,
        private readonly ?string $label = null,
        private readonly string $description = 'Fake seed content.',
    ) {
    }

    public function key(): string
    {
        return $this->key;
    }

    public function label(): string
    {
        return $this->label ?? ucfirst($this->key);
    }

    public function description(): string
    {
        return $this->description;
    }

    public function dependsOn(): array
    {
        return $this->dependsOn;
    }

    public function load(): void
    {
        if (null !== $this->onLoad) {
            ($this->onLoad)();
        }
    }
}
