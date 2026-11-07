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

namespace Uhifadhi\Devkit\Console\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Twig\Environment;
use Uhifadhi\Bundle\ShellBundle\Service\TablePageService;
use Uhifadhi\Devkit\Console\Command\CommandInventory;
use Uhifadhi\Devkit\Console\Doctor\Conformance;
use Uhifadhi\Devkit\Console\Module\ModuleRegistry;
use Uhifadhi\Devkit\Console\Table\TablePager;
use Uhifadhi\Devkit\Console\Wiring\ContributionPointInspector;

/**
 * THE DEV CONSOLE'S CONTROLLER — the four inspector surfaces, and their home.
 *
 * A PRESENTATION CONTROLLER, like the shell's: it reads what the introspection
 * services assemble — the command list, the module registry, the conformance
 * report, the contribution-point inspector — and renders one of devkit's own templates in the
 * shell frame. It runs nothing and writes nothing; v1 is an inspector.
 *
 * TWO FIREWALLS KEEP IT OUT OF PRODUCTION. The first is Composer: devkit installs
 * through require-dev, so this class is not in a production build at all. The
 * second is here, belt to that braces — every action refuses unless the kernel is
 * in debug, so even an application that mistakenly required devkit outside dev
 * serves a 404, not a console. Debug is on in dev and test and off in prod, which
 * is exactly the line the console must not cross.
 *
 * NO BASE CLASS, wired explicitly in config/services.php — the reusable-bundle
 * rule the whole fleet follows.
 */
final class ConsoleController
{
    public function __construct(
        private readonly Environment $twig,
        private readonly CommandInventory $commands,
        private readonly ModuleRegistry $modules,
        private readonly Conformance $conformance,
        private readonly ContributionPointInspector $points,
        private readonly TablePager $pager,
        private readonly TablePageService $pages,
        private readonly bool $debug,
    ) {
    }

    public function home(): Response
    {
        $this->assertAvailable();

        return $this->render('@UhifadhiDevkit/console/home.html.twig', [
            'catalogue' => $this->commands->catalogue(),
            'registry' => $this->modules->view(),
            'report' => $this->conformance->report(),
            'inspector' => $this->points,
        ]);
    }

    /**
     * One register: the bar reads `module`, `kind` and `q`, the Shell's foot
     * `page` and `per`.
     */
    public function commands(Request $request): Response
    {
        $this->assertAvailable();
        $catalogue = $this->commands->catalogue();
        $filter = self::filter($request, ['module', 'kind', 'q']);
        $listed = $catalogue->listed($filter['module'] ?? null, $filter['kind'] ?? null, $filter['q'] ?? null);
        $table = $this->pages->read($request, \count($listed));

        return $this->render('@UhifadhiDevkit/console/commands.html.twig', [
            'catalogue' => $catalogue,
            'filter' => $filter,
            'rows' => $table->slice($listed),
            'table' => $table,
            'links' => $this->pages->links($request, $table),
            'moduleCounts' => $catalogue->moduleCounts(),
            'kindCounts' => $catalogue->kindCounts(),
        ]);
    }

    /**
     * Two tables, each with its own bar and foot: the registry reads `core`,
     * `q`, `size`, `page`; the per-area matrix the same keys under an `m`.
     */
    public function modules(Request $request): Response
    {
        $this->assertAvailable();
        $registry = $this->modules->view();
        $matrix = $this->modules->areaMatrix();
        $filter = self::filter($request, ['core', 'q', 'size']);
        $matrixFilter = self::filter($request, ['reach', 'mq', 'msize']);

        return $this->render('@UhifadhiDevkit/console/modules.html.twig', [
            'registry' => $registry,
            'table' => $this->pager->page($this->modules->listed($registry, $filter['core'] ?? null, $filter['q'] ?? null), $filter['size'] ?? null, self::value($request, 'page')),
            'filter' => $filter,
            'coreCounts' => $this->modules->coreCounts($registry),
            'matrix' => $matrix,
            'matrixTable' => null === $matrix ? null : $this->pager->page($matrix->listed($matrixFilter['reach'] ?? null, $matrixFilter['mq'] ?? null), $matrixFilter['msize'] ?? null, self::value($request, 'mpage')),
            'matrixFilter' => $matrixFilter,
        ]);
    }

    public function doctor(): Response
    {
        $this->assertAvailable();

        return $this->render('@UhifadhiDevkit/console/doctor.html.twig', [
            'report' => $this->conformance->report(),
        ]);
    }

    public function wiring(): Response
    {
        $this->assertAvailable();

        return $this->render('@UhifadhiDevkit/console/wiring.html.twig', [
            'inspector' => $this->points,
        ]);
    }

    /**
     * @param list<string> $keys
     *
     * @return array<string, string> the keys the request carries, as typed
     */
    private static function filter(Request $request, array $keys): array
    {
        $filter = [];
        foreach ($keys as $key) {
            $value = self::value($request, $key);
            if (null !== $value) {
                $filter[$key] = $value;
            }
        }

        return $filter;
    }

    private static function value(Request $request, string $key): ?string
    {
        $value = $request->query->get($key);

        return \is_string($value) && '' !== trim($value) ? trim($value) : null;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function render(string $template, array $context): Response
    {
        return new Response($this->twig->render($template, $context));
    }

    /**
     * The debug firewall: outside a debug kernel the console does not exist.
     */
    private function assertAvailable(): void
    {
        if (!$this->debug) {
            throw new NotFoundHttpException('The devkit console is a dev-only surface.');
        }
    }
}
