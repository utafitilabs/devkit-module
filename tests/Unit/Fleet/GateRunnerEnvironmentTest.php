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

use PHPUnit\Framework\TestCase;
use Uhifadhi\Devkit\Fleet\GateMode;
use Uhifadhi\Devkit\Fleet\GateRequest;
use Uhifadhi\Devkit\Fleet\GateRunner;

/**
 * The gate is a console command that spawns console commands, and a Symfony
 * Process hands the child the parent's `$_ENV` unless it is told otherwise. The
 * parent is a booted installation, so its `$_ENV` carries that installation's
 * dotenv state — the created project must see none of it.
 */
final class GateRunnerEnvironmentTest extends TestCase
{
    /** @var array<mixed> */
    private array $env = [];

    /** @var array<mixed> */
    private array $server = [];

    protected function setUp(): void
    {
        $this->env = $_ENV;
        $this->server = $_SERVER;
    }

    protected function tearDown(): void
    {
        $_ENV = $this->env;
        $_SERVER = $this->server;
    }

    public function testTheMarkerAndEveryVariableItNamesAreRemovedForTheChild(): void
    {
        $_SERVER['SYMFONY_DOTENV_VARS'] = $_ENV['SYMFONY_DOTENV_VARS'] = 'APP_ENV,APP_SECRET,DATABASE_URL,MAILER_DSN';

        $environment = new GateRunner()->childEnvironment($this->request());

        self::assertFalse($environment['SYMFONY_DOTENV_VARS']);
        self::assertFalse($environment['APP_ENV']);
        self::assertFalse($environment['APP_SECRET']);
        self::assertFalse($environment['MAILER_DSN']);
    }

    public function testTheChildIsIndependentOfHowTheGateWasLaunched(): void
    {
        $environment = new GateRunner()->childEnvironment($this->request());

        self::assertFalse($environment['KERNEL_CLASS']);
        self::assertFalse($environment['APP_DEBUG']);
        self::assertFalse($environment['SHELL_VERBOSITY']);
    }

    public function testTheGatesOwnDatabasesSurviveTheScrub(): void
    {
        $_SERVER['SYMFONY_DOTENV_VARS'] = $_ENV['SYMFONY_DOTENV_VARS'] = 'APP_ENV,DATABASE_URL';

        $environment = new GateRunner()->childEnvironment($this->request());

        self::assertSame('postgresql://app:app@127.0.0.1:5434/fleet_gate', $environment['DATABASE_URL']);
        self::assertSame('postgresql://app:app@127.0.0.1:5434/fleet_gate_telemetry', $environment['TELEMETRY_DATABASE_URL']);
    }

    public function testComposerIsStillGivenItsMemory(): void
    {
        $environment = new GateRunner()->childEnvironment($this->request());

        self::assertSame('-1', $environment['COMPOSER_MEMORY_LIMIT']);
    }

    private function request(): GateRequest
    {
        return new GateRequest(
            GateMode::Released,
            '/tmp/fleet-gate-project',
            '/workspace',
            [],
            [
                'DATABASE_URL' => 'postgresql://app:app@127.0.0.1:5434/fleet_gate',
                'TELEMETRY_DATABASE_URL' => 'postgresql://app:app@127.0.0.1:5434/fleet_gate_telemetry',
            ],
        );
    }
}
