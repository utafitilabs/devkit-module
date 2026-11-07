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
use Uhifadhi\Bundle\AreaBundle\Entity\AreaOfInterest;
use Uhifadhi\Bundle\RegistryBundle\Entity\AreaModule;
use Uhifadhi\Devkit\Console\Module\AreaStatesInterface;

/**
 * THE MODULES SURFACE'S PER-AREA STATE IS THE REGISTRY'S LEDGER, read off a
 * seeded installation: every area it holds, and every module switched on in
 * each — no more and no fewer than the ledger's active rows.
 */
final class RegistryAreaStatesTest extends TestCase
{
    private InstallationKernel $kernel;

    protected function setUp(): void
    {
        $this->kernel = new InstallationKernel('test', true);
        $this->kernel->boot();

        $manager = $this->entityManager();
        $tool = new SchemaTool($manager);
        $metadata = $manager->getMetadataFactory()->getAllMetadata();
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);

        $console = new Application($this->kernel);
        $console->setAutoExit(false);
        $console->setCatchExceptions(false);
        $output = new BufferedOutput();
        self::assertSame(0, $console->run(new ArrayInput(['command' => 'fixtures:seed']), $output), $output->fetch());
    }

    protected function tearDown(): void
    {
        $this->kernel->shutdown();
    }

    public function testItReadsEveryAreaAndEveryActiveAssignment(): void
    {
        $states = $this->container()->get('devkit.console.area_states');
        self::assertInstanceOf(AreaStatesInterface::class, $states);

        $areas = $this->entityManager()->getRepository(AreaOfInterest::class)->findAll();
        self::assertNotEmpty($areas, 'the seed holds areas');
        $names = [];
        foreach ($areas as $area) {
            $names[(string) $area->getUuidString()] = $area->getName();
        }
        ksort($names);
        $read = $states->areas();
        ksort($read);
        self::assertSame($names, $read);

        $active = [];
        foreach ($this->entityManager()->getRepository(AreaModule::class)->findBy(['active' => true]) as $row) {
            $active[] = $row->getArea()?->getUuidString().'/'.$row->getModule()?->getSlug();
        }
        $switched = [];
        foreach ($states->switchedOn() as $uuid => $slugs) {
            foreach ($slugs as $slug) {
                $switched[] = $uuid.'/'.$slug;
            }
        }
        sort($active);
        sort($switched);
        self::assertSame($active, $switched);
    }

    private function container(): ContainerInterface
    {
        $container = $this->kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);

        return $container;
    }

    private function entityManager(): EntityManagerInterface
    {
        $registry = $this->container()->get('doctrine');
        self::assertInstanceOf(ManagerRegistry::class, $registry);
        $manager = $registry->getManager();
        self::assertInstanceOf(EntityManagerInterface::class, $manager);

        return $manager;
    }
}
