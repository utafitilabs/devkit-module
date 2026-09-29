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

namespace Uhifadhi\Devkit\Tests\Integration\Fixtures;

use Uhifadhi\Contracts\Devkit\ContentProviderInterface;

/**
 * A container-managed content provider that records the order load() is called
 * in on a static log, so a boot test can prove devkit collected the tagged
 * providers and ran them in dependency order through the real container.
 */
final class RecordingContentProvider implements ContentProviderInterface
{
    /** @var list<string> */
    public static array $loaded = [];

    /**
     * @param list<string> $dependsOn
     */
    public function __construct(
        private readonly string $key,
        private readonly array $dependsOn = [],
    ) {
    }

    public static function reset(): void
    {
        self::$loaded = [];
    }

    public function key(): string
    {
        return $this->key;
    }

    public function label(): string
    {
        return ucfirst($this->key);
    }

    public function description(): string
    {
        return \sprintf('Recording seed content for "%s".', $this->key);
    }

    public function dependsOn(): array
    {
        return $this->dependsOn;
    }

    public function load(): void
    {
        self::$loaded[] = $this->key;
    }
}
