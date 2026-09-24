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

namespace Uhifadhi\Devkit\Tests\Integration\Installation;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Uhifadhi\Bundle\TeamBundle\Entity\Department;
use Uhifadhi\Bundle\TeamBundle\Entity\User;
use Uhifadhi\Bundle\TeamBundle\Enum\TeamRoleEnum;

/**
 * THE POINT OF THE WHOLE ARRANGEMENT, asked of a real installation.
 *
 * The core ships an inert content provider and one console command, the
 * documented exception a production installation is bootstrapped through.
 * devkit installs via require-dev and ships no provider of its own. Separately
 * each is a half that does nothing: a provider nothing collects, and a
 * collector with nothing to collect. This suite installs both and asks whether
 * somebody who has just run `composer require --dev uhifadhi/devkit-module`
 * gets, on the console, the one account an installation cannot make through a
 * screen, and a populated first screen to look at.
 *
 * IT ASKS THE CONSOLE APPLICATION, not devkit's loader. The loader is unit-tested
 * for what it builds; what is in question here is whether `bin/console` — the
 * thing somebody actually types — finds the command, and that is a question about
 * the framework's command loader, devkit's decoration of it and the compiled
 * container at once. Nothing smaller answers it.
 *
 * IT WRITES TO A REAL DATABASE, because "creates an administrator" is a claim
 * about rows. The schema is built from the installed core's own mapping metadata
 * and dropped again, so the suite needs no migration to have been run and leaves
 * nothing behind.
 */
final class CoreProvidersMaterialiseTest extends TestCase
{
    private KernelInterface $kernel;
    private Application $console;

    protected function setUp(): void
    {
        $this->kernel = new InstallationKernel('test', true);
        $this->kernel->boot();

        $this->console = new Application($this->kernel);
        $this->console->setAutoExit(false);
        $this->console->setCatchExceptions(false);

        $this->rebuildSchema();
    }

    protected function tearDown(): void
    {
        $this->kernel->shutdown();
    }

    /**
     * The command survives devkit's decoration of the command loader: a
     * collector that swallowed the commands already there would break the one
     * account an installation cannot do without.
     */
    public function testTheFirstAdministratorCommandIsListedByTheConsole(): void
    {
        $listing = $this->execute(['command' => 'list']);

        self::assertStringContainsString(
            'team:user:create',
            $listing,
            'The core offers this command as a descriptor and devkit is what registers it. Absent from the listing means nothing collected the provider.',
        );
    }

    /**
     * Its help is the core's own line, so somebody reading the listing is
     * reading what the core wrote rather than something devkit invented.
     */
    public function testTheCommandCarriesTheDescriptionTheCoreGaveIt(): void
    {
        $help = $this->execute(['command' => 'help', 'command_name' => 'team:user:create']);

        self::assertStringContainsString('the administrator an installation is bootstrapped with', $help);
    }

    /**
     * The one account an installation cannot make through a screen, made.
     */
    public function testItCreatesTheFirstAdministrator(): void
    {
        $output = new BufferedOutput();
        $exitCode = $this->console->run(
            new ArrayInput([
                'command' => 'team:user:create',
                'email' => 'ada@example.test',
                'first-name' => 'Ada',
                'last-name' => 'Mwangi',
                '--password' => 'a-long-enough-passphrase',
            ]),
            $output,
        );

        self::assertSame(0, $exitCode, $output->fetch());

        $created = $this->entityManager()->getRepository(User::class)->findOneBy(['email' => 'ada@example.test']);

        self::assertNotNull($created, 'The command writes through the roster\'s own service, so what it leaves is an ordinary account.');
        self::assertSame(
            TeamRoleEnum::SuperAdmin,
            $created->getTeamRole(),
            'A first administrator who could not administer would leave an installation with nobody who can.',
        );
        self::assertTrue($created->isVerified(), 'This is the credential somebody signs in with in the installation\'s first minute, not an invitation waiting to be accepted.');
        self::assertTrue($created->isActive());
    }

    /**
     * The tier is the one thing the handler's tail decides, so an installation
     * that wants a lesser account can say so.
     */
    public function testTheTailChoosesTheTier(): void
    {
        $this->console->run(
            new ArrayInput([
                'command' => 'team:user:create',
                'email' => 'kofi@example.test',
                'first-name' => 'Kofi',
                'last-name' => 'Mensah',
                '--tier' => 'staff',
                '--password' => 'a-long-enough-passphrase',
            ]),
            new BufferedOutput(),
        );

        $created = $this->entityManager()->getRepository(User::class)->findOneBy(['email' => 'kofi@example.test']);

        self::assertNotNull($created);
        self::assertSame(TeamRoleEnum::Staff, $created->getTeamRole());
    }

    /**
     * The other half of the contract: the content providers, ordered and run.
     */
    public function testTheDemoContentCommandSeedsTheCoresOrganization(): void
    {
        $output = $this->execute(['command' => 'fixtures:demo']);

        self::assertStringContainsString('Team', $output, 'The provider names itself while seeding.');

        $departments = $this->entityManager()->getRepository(Department::class)->findAll();

        self::assertNotEmpty(
            $departments,
            'The demo organization is departments, the positions filed under them and the people who hold them. An empty table means the provider was described but never called.',
        );

        $people = $this->entityManager()->getRepository(User::class)->findAll();
        self::assertNotEmpty($people, 'Nobody in the roster is a first screen with nothing on it.');
    }

    /** @param array<string, mixed> $input */
    private function execute(array $input): string
    {
        $output = new BufferedOutput();
        $this->console->run(new ArrayInput($input), $output);

        return $output->fetch();
    }

    private function rebuildSchema(): void
    {
        $manager = $this->entityManager();
        $metadata = $manager->getMetadataFactory()->getAllMetadata();

        $tool = new SchemaTool($manager);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    private function entityManager(): EntityManagerInterface
    {
        // The test service container, so the suite reaches the same objects the
        // console run reaches rather than a second set built beside them.
        $testContainer = $this->kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $testContainer);

        $registry = $testContainer->get('doctrine');
        self::assertInstanceOf(ManagerRegistry::class, $registry);

        $manager = $registry->getManager();
        self::assertInstanceOf(EntityManagerInterface::class, $manager);

        return $manager;
    }
}
