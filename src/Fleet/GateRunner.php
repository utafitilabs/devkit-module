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

namespace Uhifadhi\Devkit\Fleet;

use Symfony\Component\BrowserKit\HttpBrowser;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Process\Process;

/**
 * THE GATE, PERFORMED.
 *
 * The planner decided what happens; this does it, one step at a time, stopping
 * at the first red one and handing back what came out of it.
 *
 * The browser half is the half no shell can perform: the difference between "the
 * package is in the lock file" and "an administrator can open it".
 *
 *   HttpBrowser
 *
 *     @see https://symfony.com/doc/current/components/browser_kit.html
 *     @see vendor/symfony/browser-kit/AbstractBrowser.php —
 *          `protected bool $followRedirects = true;`, so `submit($form)` lands
 *          on the page the redirect points at, and absolute URIs are what a real
 *          HTTP client takes.
 */
final class GateRunner
{
    private ?Process $server = null;
    private string $baseUrl = '';
    private string $areaUuid = '';
    private ?HttpBrowser $session = null;

    /**
     * @param \Closure(GateStep, bool, string): void $report called once per step, before the next one starts
     *
     * @throws GateFailure on the first step that does not pass
     */
    public function run(GatePlan $plan, GateRequest $request, \Closure $report): void
    {
        try {
            foreach ($plan->steps as $step) {
                $output = $this->perform($step, $request);
                $report($step, true, $output);
            }
        } catch (GateFailure $failure) {
            $report($failure->step, false, $failure->output);

            throw $failure;
        } finally {
            $this->server?->stop();
            $this->server = null;
        }
    }

    /** What is left on disk after a run, for the report to name. */
    public function tearDown(GateRequest $request): void
    {
        $this->server?->stop();
        $this->server = null;

        if (!$request->keep && is_dir($request->project)) {
            new Process(['rm', '-rf', $request->project])->mustRun();
        }
    }

    private function perform(GateStep $step, GateRequest $request): string
    {
        return match ($step->kind) {
            GateStepKind::Shell => $this->shell($step, $request),
            GateStepKind::FreshDatabase => $this->freshDatabase($step),
            GateStepKind::WriteEnvironment => $this->writeEnvironment($request),
            GateStepKind::Serve => $this->serve($step, $request),
            GateStepKind::SignIn => $this->signIn($step),
            GateStepKind::CreateArea => $this->createTheArea($step),
            GateStepKind::OpenModule => $this->switchOnAndOpen($step, $request),
            GateStepKind::ReadmeListsModules => $this->readmeListsModules($step, $request),
        };
    }

    private function shell(GateStep $step, GateRequest $request): string
    {
        $cwd = $step->inProject ? $request->project : \dirname($request->project);
        $process = new Process($step->command, $cwd, ['COMPOSER_MEMORY_LIMIT' => '-1', 'APP_ENV' => 'dev'], timeout: 900);
        $process->run();
        $output = $process->getOutput().$process->getErrorOutput();

        if (!$process->isSuccessful() && !$step->allowFailure) {
            throw new GateFailure($step, $output, \sprintf('the command exited %d', (int) $process->getExitCode()));
        }

        if (null !== $step->expect && !str_contains($output, $step->expect)) {
            throw new GateFailure($step, $output, \sprintf('the output does not carry "%s"', $step->expect));
        }

        return $output;
    }

    /** The database named in the url is DROPPED and recreated. */
    private function freshDatabase(GateStep $step): string
    {
        $url = (string) $step->subject;
        $parts = parse_url($url);
        if (!\is_array($parts)) {
            throw new GateFailure($step, $url, 'that is not a database url');
        }

        $name = ltrim($parts['path'] ?? '', '/');
        if ('' === $name) {
            throw new GateFailure($step, $url, 'the url names no database');
        }

        try {
            $pdo = new \PDO(
                \sprintf('pgsql:host=%s;port=%d;dbname=postgres', $parts['host'] ?? '127.0.0.1', $parts['port'] ?? 5432),
                $parts['user'] ?? null,
                $parts['pass'] ?? null,
            );
            $pdo->exec(\sprintf('DROP DATABASE IF EXISTS "%s"', $name));
            $pdo->exec(\sprintf('CREATE DATABASE "%s"', $name));
        } catch (\PDOException $e) {
            throw new GateFailure($step, $e->getMessage(), 'the database server would not answer');
        }

        return $name.' dropped and recreated';
    }

    private function writeEnvironment(GateRequest $request): string
    {
        $lines = '';
        foreach ($request->databases as $variable => $url) {
            $lines .= $variable.'="'.$url.'"'."\n";
        }
        file_put_contents($request->project.'/.env.local', $lines);

        return $lines;
    }

    private function serve(GateStep $step, GateRequest $request): string
    {
        $this->server?->stop();

        $this->baseUrl = 'http://127.0.0.1:'.$this->freePort($step);
        $server = new Process(['php', '-S', substr($this->baseUrl, 7), '-t', 'public'], $request->project);

        // NOBODY READS THIS SERVER'S OUTPUT, SO IT MUST NOT HAVE ANY. Process
        // always fetches a child's stdout and stderr into pipes, and it drains
        // them only when the parent asks after the process — which this gate
        // never does for the server: it starts it and then talks HTTP to it. The
        // dev-mode server logs several lines per request, the pipe fills, the
        // server blocks on write, and the next request waits on a client timeout
        // instead of an answer.
        //
        //   "As standard output and error output are always fetched from the
        //    underlying process, it might be convenient to disable output in
        //    some cases to save memory. Use disableOutput()"
        //   @see https://symfony.com/doc/current/components/process.html#disabling-output
        //   @see vendor/symfony/process/Process.php — buildDescriptors():
        //        `new UnixPipes($this->isTty(), $this->isPty(), $this->input, !$this->outputDisabled || $hasCallback)`
        //   @see vendor/symfony/process/Pipes/UnixPipes.php — getDescriptors():
        //        with no read support the child is handed `/dev/null`, not a pipe
        //
        // And the timeout is lifted, because a Process is 60 s by default
        //   "?float $timeout = 60"  @see vendor/symfony/process/Process.php — __construct()
        // while this one is meant to outlive every module's install.
        $server->setTimeout(null);
        $server->disableOutput();
        $server->start();
        $this->server = $server;

        $deadline = microtime(true) + 15;
        do {
            usleep(200_000);
            $up = @file_get_contents($this->baseUrl.'/login', false, stream_context_create(['http' => ['timeout' => 2, 'ignore_errors' => true]]));
        } while (false === $up && microtime(true) < $deadline);

        if (false === $up) {
            throw new GateFailure($step, '', 'the built-in server did not answer within 15 s');
        }

        return $this->baseUrl;
    }

    private function browser(): HttpBrowser
    {
        return new HttpBrowser(HttpClient::create());
    }

    private function signIn(GateStep $step): string
    {
        $browser = $this->browser();

        $crawler = $browser->request('GET', $this->baseUrl.'/login');
        $this->expectStatus($step, $browser, 'the sign-in page answers');

        $form = $crawler->filter('form[action$="/login"]')->form([
            '_username' => GatePlanner::ADMIN_EMAIL,
            '_password' => GatePlanner::ADMIN_PASSWORD,
        ]);
        // The browser follows the redirect off the form itself.
        $browser->submit($form);
        $this->expectStatus($step, $browser, 'signing in lands on a page');

        $body = (string) $browser->getResponse()->getContent();
        if (str_contains($body, 'name="_password"')) {
            throw new GateFailure($step, $body, 'the sign-in form is still there after signing in');
        }
        if (!str_contains($body, GatePlanner::ADMIN_FIRST_NAME)) {
            throw new GateFailure($step, $body, 'the page does not name the person who signed in');
        }

        $this->session = $browser;

        return 'signed in as '.GatePlanner::ADMIN_EMAIL;
    }

    /**
     * THE AREA, created the way an administrator creates one: the form at
     * /areas/new, boundary to be imported later. The uuid in the address the
     * form lands on is the area's, and every module step works inside it.
     */
    private function createTheArea(GateStep $step): string
    {
        $browser = $this->signedIn($step);

        $crawler = $browser->request('GET', $this->baseUrl.'/areas/new');
        $this->expectStatus($step, $browser, 'the new-area form answers');

        $form = $crawler->filter('form')
            ->reduce(static fn (Crawler $node): bool => null !== $node->filter('input[name="name"]')->getNode(0))
            ->form();
        $form['name'] = (string) $step->subject;
        if ($form->has('boundary_mode')) {
            $form['boundary_mode'] = 'later';
        }
        $browser->submit($form);
        $this->expectStatus($step, $browser, 'creating the area lands on a page');

        $landed = $browser->getRequest()->getUri();
        if (1 !== preg_match('#/areas/([0-9a-f-]{36})#', $landed, $m)) {
            throw new GateFailure($step, $landed, 'the page the area landed on carries no uuid');
        }
        $this->areaUuid = $m[1];

        return $this->areaUuid;
    }

    /**
     * SWITCHED ON AND OPENED. A capability module is parked after its install;
     * the administrator switches it on for the area through the grid's own form,
     * and its first page then answers. An infrastructure module has no tile and
     * answers everywhere at once.
     */
    private function switchOnAndOpen(GateStep $step, GateRequest $request): string
    {
        $browser = $this->signedIn($step);
        $module = $this->moduleNamed((string) $step->subject, $step, $request);

        if (null !== $module->catalogueSlug) {
            $slug = $module->catalogueSlug;
            $crawler = $browser->request('GET', $this->baseUrl.'/areas/'.$this->areaUuid.'/modules/customize');
            $this->expectStatus($step, $browser, 'the area\'s module grid answers');

            $forms = $crawler->filter('form')->reduce(static function (Crawler $node) use ($slug): bool {
                $field = $node->filter('input[name="module"]')->getNode(0);

                return $field instanceof \DOMElement
                    && $slug === $field->getAttribute('value')
                    && str_contains((string) $node->attr('action'), '/install');
            });
            if ($forms->count() > 0) {
                $browser->submit($forms->first()->form());
                $this->expectStatus($step, $browser, 'switching the module on lands on a page');
            }
        }

        $page = $module->pageFor($this->areaUuid);
        $browser->request('GET', $this->baseUrl.$page);
        $this->expectStatus($step, $browser, $page.' answers for the administrator');

        $body = (string) $browser->getResponse()->getContent();
        if (str_contains($body, 'name="_password"')) {
            throw new GateFailure($step, $body, $page.' answered with the sign-in form');
        }

        return $page.' answers';
    }

    private function readmeListsModules(GateStep $step, GateRequest $request): string
    {
        $readme = (string) $step->subject.'/README.md';
        if (!is_file($readme)) {
            throw new GateFailure($step, $readme, 'there is no starter README to read there');
        }

        $text = (string) file_get_contents($readme);
        $missing = [];
        foreach ($request->modules as $module) {
            if (!$module->private && !str_contains($text, $module->package())) {
                $missing[] = $module->package();
            }
        }
        if ([] !== $missing) {
            throw new GateFailure($step, implode("\n", $missing), 'the starter\'s official-modules table does not list every module this gate installs');
        }

        return 'the two lists are one list';
    }

    private function moduleNamed(string $name, GateStep $step, GateRequest $request): ModuleUnderGate
    {
        foreach ($request->modules as $module) {
            if ($module->name === $name) {
                return $module;
            }
        }

        throw new GateFailure($step, $name, 'this run installs no such module');
    }

    private function signedIn(GateStep $step): HttpBrowser
    {
        return $this->session ?? throw new GateFailure($step, '', 'nobody has signed in yet');
    }

    private function expectStatus(GateStep $step, HttpBrowser $browser, string $what): void
    {
        $status = $browser->getResponse()->getStatusCode();
        if (200 !== $status) {
            throw new GateFailure($step, (string) $browser->getResponse()->getContent(), \sprintf('%s — it answered %d', $what, $status));
        }
    }

    private function freePort(GateStep $step): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        if (false === $socket) {
            throw new GateFailure($step, $errstr ?? '', 'no free port to serve on');
        }
        $name = (string) stream_socket_get_name($socket, false);
        fclose($socket);

        return (int) substr($name, (int) strrpos($name, ':') + 1);
    }
}
