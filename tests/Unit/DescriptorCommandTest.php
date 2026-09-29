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
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Uhifadhi\Contracts\Devkit\CommandDescriptor;
use Uhifadhi\Contracts\Devkit\CommandIo;
use Uhifadhi\Devkit\Command\DescriptorCommand;

/**
 * A CommandDescriptor becomes a runnable console command: its name and help are
 * the command's, the argument tail reaches the handler as a list<string>, and
 * the handler's return value is the command's exit code.
 */
final class DescriptorCommandTest extends TestCase
{
    public function testItTakesItsNameAndDescriptionFromTheDescriptor(): void
    {
        $command = new DescriptorCommand(new CommandDescriptor(
            'patrol:seed:reset',
            'Wipe and reseed the patrol seed content.',
            static fn (array $arguments): int => 0,
        ));

        self::assertSame('patrol:seed:reset', $command->getName());
        self::assertSame('Wipe and reseed the patrol seed content.', $command->getDescription());
    }

    public function testItReturnsTheHandlersExitCode(): void
    {
        $command = new DescriptorCommand(new CommandDescriptor(
            'seed:fail',
            'Always fails.',
            static fn (array $arguments): int => 42,
        ));

        $tester = new CommandTester($command);

        self::assertSame(42, $tester->execute([]));
    }

    public function testItPassesTheArgumentTailToTheHandler(): void
    {
        $received = null;
        $command = new DescriptorCommand(new CommandDescriptor(
            'seed:echo',
            'Captures its argument tail.',
            static function (array $arguments) use (&$received): int {
                $received = $arguments;

                return 0;
            },
        ));

        $tester = new CommandTester($command);
        $tester->execute(['arguments' => ['alpha', 'beta']]);

        self::assertSame(['alpha', 'beta'], $received);
    }

    /**
     * WHAT THE HANDLER SAYS REACHES THE CONSOLE'S OUTPUT — the point of handing
     * it a channel at all. A handler that wrote to \STDOUT instead would put
     * this text somewhere no tester can see and no flag can govern.
     */
    public function testWhatTheHandlerWritesGoesToTheConsoleOutput(): void
    {
        $tester = new CommandTester(self::speaking());
        $tester->execute([]);

        self::assertStringContainsString('made the thing', $tester->getDisplay());
    }

    /**
     * AND `--quiet` SILENCES IT, which is the whole argument for the contract
     * carrying three plain verbs and no verbosity of its own: the adapter writes
     * through the real output, so the flag the person typed is obeyed by a
     * handler that has never heard of it.
     */
    public function testQuietSilencesWhatTheHandlerWrites(): void
    {
        $tester = new CommandTester(self::speaking());
        $tester->execute([], ['verbosity' => OutputInterface::VERBOSITY_QUIET]);

        self::assertSame('', $tester->getDisplay());
    }

    /**
     * DIAGNOSTICS GO TO THE ERROR STREAM, so a person still reads them when the
     * command's output is being piped somewhere — and so they never land in
     * that pipe.
     */
    public function testDiagnosticsGoToTheErrorStream(): void
    {
        $tester = new CommandTester(self::speaking());
        $tester->execute([], ['capture_stderr_separately' => true]);

        self::assertStringContainsString('could not make the other', $tester->getErrorOutput());
        self::assertStringNotContainsString('could not make the other', $tester->getDisplay());
    }

    /**
     * AND INPUT COMES FROM THE CONSOLE'S STREAM, not from \STDIN — which is what
     * lets a passphrase be piped in, and what lets this assert it at all.
     */
    public function testTheHandlerReadsALineFromTheConsolesInput(): void
    {
        $read = null;
        $tester = new CommandTester(self::reading($read));
        $tester->setInputs(['a-piped-passphrase']);
        $tester->execute([]);

        self::assertSame('a-piped-passphrase', $read);
    }

    /**
     * A SECRET COMES OFF THE SAME STREAM, which is what lets one verb serve
     * both a passphrase typed at a prompt and one piped in: where there is no
     * terminal there is no echo to switch off, and the line is simply read.
     */
    public function testTheHandlerReadsASecretFromTheConsolesInput(): void
    {
        $read = null;
        $tester = new CommandTester(self::reading($read, secret: true));
        $tester->setInputs(['a-typed-passphrase']);
        $tester->execute([]);

        self::assertSame('a-typed-passphrase', $read);
    }

    /**
     * AND IT DOES NOT COME BACK OUT. The whole of readSecret() is that what was
     * typed is not shown — so neither the answer nor the question it was given
     * in reply to may land on standard output, where a caller piping this
     * command's result would collect the passphrase along with it.
     */
    public function testASecretReachesNeitherOutputStream(): void
    {
        $read = null;
        $tester = new CommandTester(self::reading($read, secret: true));
        $tester->setInputs(['a-typed-passphrase']);
        $tester->execute([], ['capture_stderr_separately' => true]);

        self::assertSame('a-typed-passphrase', $read);
        self::assertStringNotContainsString('a-typed-passphrase', $tester->getDisplay());
        self::assertStringNotContainsString('a-typed-passphrase', $tester->getErrorOutput());
        self::assertSame('', $tester->getDisplay(), 'Asking for a secret is not the command\'s result, so none of it is on standard output.');
    }

    /**
     * NOTHING TYPED IS NULL, and with the echo off that is all null can mean: a
     * prompt answered with a return and a stream that closed under it arrive
     * identically, and a handler that requires a secret refuses either.
     */
    public function testAnInputThatOffersNothingHasNoSecretToGive(): void
    {
        $read = 'never read';
        $tester = new CommandTester(self::reading($read, secret: true));
        $tester->execute([]);

        self::assertNull($read);
    }

    /** A command whose handler records the one line, or the one secret, it was given. */
    private static function reading(mixed &$read, bool $secret = false): DescriptorCommand
    {
        return new DescriptorCommand(new CommandDescriptor(
            'seed:read',
            'Reads one line of input.',
            static function (array $arguments, CommandIo $io) use (&$read, $secret): int {
                $read = $secret ? $io->readSecret() : $io->readLine();

                return 0;
            },
        ));
    }

    /**
     * A HANDLER THAT WANTS NO CHANNEL STILL RUNS. The io was added to the
     * descriptor's signature additively: PHP passes the extra argument to a
     * closure that does not declare it, so a provider written against the older
     * one-parameter handler is not broken by this wrapper passing two.
     */
    public function testAHandlerThatDeclaresNoIoStillRuns(): void
    {
        $command = new DescriptorCommand(new CommandDescriptor(
            'seed:oblivious',
            'Ignores the io entirely.',
            static fn (array $arguments): int => 7,
        ));

        self::assertSame(7, new CommandTester($command)->execute([]));
    }

    /**
     * OPTION-LOOKING TOKENS REACH THE HANDLER, which is the whole of the
     * descriptor's bargain: the contract models no options precisely so that a
     * handler can parse its own tail, and a wrapper that let the console
     * validate that tail first would make the bargain unkeepable. Symfony
     * rejects an unknown `--option` while binding, so before this passed the
     * raw tokens through, every command that took one — `team:user:create
     * --password=…` among them — died with "The --password option does not
     * exist." and never reached its handler at all.
     */
    public function testOptionLookingTokensReachTheHandler(): void
    {
        $received = null;
        $status = self::runArgv(
            self::capturing($received),
            ['bin/console', 'seed:echo', 'a@b.c', 'Ada', '--password=x', '--tier=admin'],
        );

        self::assertSame(0, $status);
        self::assertSame(['a@b.c', 'Ada', '--password=x', '--tier=admin'], $received);
    }

    /**
     * IN THE ORDER THEY WERE TYPED, and with the separator and the bare
     * double-dashes intact — the handler is promised what the person wrote
     * after the command name, not a normalised reading of it.
     */
    public function testTheTailKeepsItsOrderAndItsOddities(): void
    {
        $received = null;
        $status = self::runArgv(
            self::capturing($received),
            ['bin/console', 'seed:echo', '--fresh', 'alpha', '--count=10', '-v', 'beta'],
        );

        self::assertSame(0, $status);
        self::assertSame(['--fresh', 'alpha', '--count=10', '-v', 'beta'], $received);
    }

    /**
     * AND THE APPLICATION'S OWN OPTIONS ARE NOT THE COMMAND'S TAIL. What comes
     * before the command name belongs to the console, so the tail begins after
     * it — which is exactly where ArgvInput::getRawTokens(true) starts.
     */
    public function testWhatPrecedesTheCommandNameIsNotPartOfTheTail(): void
    {
        $received = null;
        $status = self::runArgv(
            self::capturing($received),
            ['bin/console', '--no-ansi', 'seed:echo', 'alpha'],
        );

        self::assertSame(0, $status);
        self::assertSame(['alpha'], $received);
    }

    /**
     * `--help` STILL DESCRIBES THE COMMAND rather than being swallowed into the
     * tail: the application intercepts it before the command runs, so a
     * descriptor keeps the one piece of console courtesy it has — its help line
     * — and the handler is not invoked.
     */
    public function testHelpStillDescribesADescriptorCommand(): void
    {
        $received = null;
        $output = new BufferedOutput();
        $status = self::runArgv(self::capturing($received), ['bin/console', 'seed:echo', '--help'], $output);

        self::assertSame(0, $status);
        self::assertStringContainsString('Captures its argument tail.', $output->fetch());
        self::assertNull($received, 'Asking for help runs the help command, not the handler.');
    }

    /**
     * A tester binds an ArrayInput rather than an ArgvInput — there are no raw
     * tokens to take, so the tail comes off the bound argument, and the tests
     * that drive a command that way keep working.
     */
    public function testTheTailIsStillReadWhenThereAreNoRawTokens(): void
    {
        $received = null;
        $tester = new CommandTester(self::capturing($received));
        $tester->execute(['arguments' => ['alpha', '--count=10']]);

        self::assertSame(['alpha', '--count=10'], $received);
    }

    /** A command whose handler records the tail it was handed. */
    private static function capturing(mixed &$received): DescriptorCommand
    {
        return new DescriptorCommand(new CommandDescriptor(
            'seed:echo',
            'Captures its argument tail.',
            static function (array $arguments) use (&$received): int {
                $received = $arguments;

                return 0;
            },
        ));
    }

    /**
     * The command as a person actually reaches it: registered on an
     * application and driven by the tokens of a real command line.
     *
     * @param list<string> $argv
     */
    private static function runArgv(DescriptorCommand $command, array $argv, ?BufferedOutput $output = null): int
    {
        $application = new Application();
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);
        $application->addCommand($command);

        return $application->run(new ArgvInput($argv), $output ?? new BufferedOutput());
    }

    /** A command that says one thing on each stream. */
    private static function speaking(): DescriptorCommand
    {
        return new DescriptorCommand(new CommandDescriptor(
            'seed:say',
            'Says one thing on each stream.',
            static function (array $arguments, CommandIo $io): int {
                $io->write('made the thing');
                $io->error('could not make the other thing');

                return 0;
            },
        ));
    }
}
