<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Domain;
use App\Entity\Link;
use App\Entity\User;
use App\Form\LinkType;
use App\Link\LinkManager;
use App\Qr\QrCodeRenderer;
use App\Repository\ClickRepository;
use App\Repository\DomainRepository;
use App\Repository\LinkRepository;
use App\Security\AdminAccess;
use App\Security\Voter\LinkVoter;
use App\Stats\Period;
use App\Stats\StatsBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Translation\TranslatableMessage;

#[Route('/links')]
final class LinkController extends AbstractController
{
    private const int PER_PAGE = 25;

    /** Upper bound for one bulk request; anything larger is rejected with 422. */
    private const int BULK_MAX = 200;

    /** @var list<string> whitelisted sort keys; anything else falls back to created */
    private const array SORTS = ['created', 'clicks', 'last_click', 'target', 'code'];

    /** @var list<string> whitelisted sort directions; anything else falls back to desc */
    private const array DIRECTIONS = ['asc', 'desc'];

    /** @var list<string> whitelisted bulk actions */
    private const array BULK_ACTIONS = ['activate', 'deactivate', 'delete', 'move'];

    public function __construct(
        private readonly LinkManager $manager,
        private readonly DomainRepository $domains,
        private readonly AdminAccess $adminAccess,
    ) {
    }

    #[Route('', name: 'app_links', methods: ['GET'])]
    public function index(Request $request, #[CurrentUser] User $user, LinkRepository $links): Response
    {
        $search = trim((string) $request->query->get('q'));
        $domainId = $request->query->getInt('domain');
        $domain = $domainId > 0 ? $this->domains->find($domainId) : null;
        $isAdmin = $this->adminAccess->isAdmin($user);
        $onlyOwn = !$isAdmin || $request->query->getBoolean('mine');
        $page = max(1, $request->query->getInt('page', 1));
        // Unknown values silently fall back to the default order (created/desc).
        $sort = (string) $request->query->get('sort', 'created');
        $sort = \in_array($sort, self::SORTS, true) ? $sort : 'created';
        $dir = (string) $request->query->get('dir', 'desc');
        $dir = \in_array($dir, self::DIRECTIONS, true) ? $dir : 'desc';

        $query = $links->searchQuery($user, $search, $domain, $onlyOwn, $sort, $dir)
            ->setFirstResult(($page - 1) * self::PER_PAGE)
            ->setMaxResults(self::PER_PAGE);
        $paginator = new Paginator($query, fetchJoinCollection: false);

        return $this->render('link/index.html.twig', [
            'links' => $paginator,
            'total' => \count($paginator),
            'page' => $page,
            'pages' => max(1, (int) ceil(\count($paginator) / self::PER_PAGE)),
            'search' => $search,
            'domain' => $domain,
            'only_own' => $onlyOwn,
            'sort' => $sort,
            'dir' => $dir,
            'domains' => $isAdmin ? $this->domains->findAllOrdered() : $this->domains->findUsableBy($user, false),
            'move_domains' => $this->domains->findUsableBy($user, $isAdmin),
        ]);
    }

    #[Route('/new', name: 'app_link_new', methods: ['GET', 'POST'])]
    public function new(Request $request, #[CurrentUser] User $user): Response
    {
        $usable = $this->domains->findUsableBy($user, $this->adminAccess->isAdmin($user));
        if ([] === $usable) {
            $this->addFlash('warning', 'flash.no_domains');

            return $this->redirectToRoute('app_dashboard');
        }

        $link = new Link($user);
        $link->setDomain($usable[0]);
        $this->manager->applyDefaults($link);

        // The dashboard quick form only posts target, domain and code; a full form would
        // read the missing "active" checkbox as unchecked.
        $full = !$request->isMethod('POST') || \array_key_exists('redirectType', $request->request->all('link'));
        $form = $this->createForm(LinkType::class, $link, ['domains' => $usable, 'full' => $full]);

        return $this->handleForm($request, $form, $link, 'link/new.html.twig', 'flash.link_created');
    }

    #[Route('/bulk', name: 'app_links_bulk', methods: ['POST'])]
    public function bulk(Request $request, #[CurrentUser] User $user, LinkRepository $links): Response
    {
        if (!$this->isCsrfTokenValid('links-bulk', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $action = (string) $request->request->get('action');
        if (!\in_array($action, self::BULK_ACTIONS, true)) {
            throw new UnprocessableEntityHttpException('Unknown bulk action.');
        }

        $ids = $this->bulkIds($request);
        if (\count($ids) > self::BULK_MAX) {
            throw new UnprocessableEntityHttpException(\sprintf('At most %d links per bulk action.', self::BULK_MAX));
        }

        if ([] === $ids) {
            $this->addFlash('warning', 'bulk.none');

            return $this->redirectToRoute('app_links', $this->bulkFilters($request));
        }

        if ('move' === $action) {
            $target = $this->usableDomain($user, $request->request->getInt('domain'));
            if (null === $target) {
                $this->addFlash('warning', 'bulk.move_invalid');

                return $this->redirectToRoute('app_links', $this->bulkFilters($request));
            }
            [$changed, $denied, $conflicts] = $this->moveLinks($ids, $target, $links);
        } else {
            [$changed, $denied, $conflicts] = $this->applyToLinks($ids, $action, $links);
        }

        $this->addFlash($changed > 0 ? 'success' : 'warning', new TranslatableMessage('bulk.result', [
            '%changed%' => (string) $changed,
            '%denied%' => (string) $denied,
            '%conflicts%' => (string) $conflicts,
        ]));

        return $this->redirectToRoute('app_links', $this->bulkFilters($request));
    }

    #[Route('/{id}', name: 'app_link_show', requirements: ['id' => Requirement::DIGITS], methods: ['GET'])]
    #[IsGranted(LinkVoter::MANAGE, 'link')]
    public function show(Request $request, Link $link, StatsBuilder $stats): Response
    {
        return $this->render('link/show.html.twig', [
            'link' => $link,
            'stats' => $stats->build(Period::lastDays($request->query->getInt('days', 30)), link: $link),
            'periods' => Period::CHOICES,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_link_edit', requirements: ['id' => Requirement::DIGITS], methods: ['GET', 'POST'])]
    #[IsGranted(LinkVoter::MANAGE, 'link')]
    public function edit(Request $request, Link $link, #[CurrentUser] User $user): Response
    {
        $usable = $this->domains->findUsableBy($user, $this->adminAccess->isAdmin($user));
        if (null !== $link->getDomain() && !\in_array($link->getDomain(), $usable, true)) {
            // Keep the current domain selectable even if the assignment was revoked later.
            $usable[] = $link->getDomain();
        }
        $form = $this->createForm(LinkType::class, $link, ['domains' => $usable]);

        return $this->handleForm($request, $form, $link, 'link/edit.html.twig', 'flash.link_saved');
    }

    #[Route('/{id}/delete', name: 'app_link_delete', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    #[IsGranted(LinkVoter::MANAGE, 'link')]
    public function delete(Request $request, Link $link): Response
    {
        if (!$this->isCsrfTokenValid('delete-link-'.$link->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $this->manager->delete($link);
        $this->addFlash('success', 'flash.link_deleted');

        return $this->redirectToRoute('app_links');
    }

    #[Route('/{id}/qr.{format}', name: 'app_link_qr', requirements: ['id' => Requirement::DIGITS, 'format' => 'svg|png'], methods: ['GET'])]
    #[IsGranted(LinkVoter::MANAGE, 'link')]
    public function qr(Request $request, Link $link, string $format, QrCodeRenderer $qr): Response
    {
        $png = 'png' === $format;
        $response = new Response(
            $png ? $qr->png($link->getShortUrl()) : $qr->svg($link->getShortUrl()),
            Response::HTTP_OK,
            ['Content-Type' => $png ? 'image/png' : 'image/svg+xml', 'Cache-Control' => 'private, max-age=300'],
        );
        if ($request->query->getBoolean('download')) {
            $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(
                HeaderUtils::DISPOSITION_ATTACHMENT,
                \sprintf('qr-%s-%s.%s', $link->getDomain()?->getHostname(), $link->getCode(), $format),
            ));
        }

        return $response;
    }

    #[Route('/{id}/export.csv', name: 'app_link_export', requirements: ['id' => Requirement::DIGITS], methods: ['GET'])]
    #[IsGranted(LinkVoter::MANAGE, 'link')]
    public function export(Request $request, Link $link, ClickRepository $clicks): StreamedResponse
    {
        $period = Period::lastDays($request->query->getInt('days', 365));
        $response = new StreamedResponse(static function () use ($clicks, $link, $period): void {
            $out = fopen('php://output', 'w');
            if (false === $out) {
                return;
            }
            fputcsv($out, ['clicked_at', 'country', 'referrer', 'device', 'browser', 'os', 'language', 'bot'], escape: '');
            foreach ($clicks->iterateForExport($link, $period->from, $period->to) as $click) {
                fputcsv($out, [
                    $click->getClickedAt()->format(\DATE_ATOM),
                    $click->getCountryCode(),
                    $click->getReferrerHost(),
                    $click->getDeviceType(),
                    $click->getBrowser(),
                    $click->getOs(),
                    $click->getLanguage(),
                    $click->isBot() ? '1' : '0',
                ], escape: '');
            }
            fclose($out);
        });
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            \sprintf('clicks-%s-%s.csv', $link->getDomain()?->getHostname(), $link->getCode()),
        ));

        return $response;
    }

    /**
     * Positive, de-duplicated ids from the "ids[]" field.
     *
     * @return list<int>
     */
    private function bulkIds(Request $request): array
    {
        $ids = [];
        foreach ($request->request->all('ids') as $raw) {
            if (!\is_string($raw) && !\is_int($raw)) {
                continue;
            }
            $id = (int) $raw;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }

    /**
     * Rebuilds the list filters from the bulk form for the redirect. Only whitelisted
     * parameters survive, so a tampered form cannot turn this into an open redirect.
     *
     * @return array<string, string|int>
     */
    private function bulkFilters(Request $request): array
    {
        $return = $request->request->all('return');
        $params = [];

        $q = trim((string) ($return['q'] ?? ''));
        if ('' !== $q) {
            $params['q'] = $q;
        }
        $domain = (int) ($return['domain'] ?? 0);
        if ($domain > 0) {
            $params['domain'] = $domain;
        }
        if (!empty($return['mine'])) {
            $params['mine'] = '1';
        }
        $sort = (string) ($return['sort'] ?? '');
        if (\in_array($sort, self::SORTS, true)) {
            $params['sort'] = $sort;
        }
        $dir = (string) ($return['dir'] ?? '');
        if (\in_array($dir, self::DIRECTIONS, true)) {
            $params['dir'] = $dir;
        }
        $page = (int) ($return['page'] ?? 1);
        if ($page > 1) {
            $params['page'] = $page;
        }

        return $params;
    }

    /** The domain with that id if the user may use it (all active domains for admins), otherwise null. */
    private function usableDomain(User $user, int $domainId): ?Domain
    {
        foreach ($this->domains->findUsableBy($user, $this->adminAccess->isAdmin($user)) as $domain) {
            if ($domain->getId() === $domainId) {
                return $domain;
            }
        }

        return null;
    }

    /**
     * Activate, deactivate or delete every permitted link.
     *
     * @param list<int> $ids
     *
     * @return array{int, int, int} [changed, denied, conflicts]
     */
    private function applyToLinks(array $ids, string $action, LinkRepository $links): array
    {
        $changed = 0;
        $denied = 0;
        foreach ($ids as $id) {
            $link = $links->find($id);
            if (null === $link || !$this->isGranted(LinkVoter::MANAGE, $link)) {
                // Missing or foreign links are counted and reported, never touched.
                ++$denied;
                continue;
            }
            if ('delete' === $action) {
                $this->manager->delete($link);
            } else {
                $link->setActive('activate' === $action);
                $this->manager->save($link);
            }
            ++$changed;
        }

        return [$changed, $denied, 0];
    }

    /**
     * Move every permitted link to $target, skipping ones whose code is already taken there
     * (the unique index would otherwise abort the request with a 500).
     *
     * @param list<int> $ids
     *
     * @return array{int, int, int} [changed, denied, conflicts]
     */
    private function moveLinks(array $ids, Domain $target, LinkRepository $links): array
    {
        $changed = 0;
        $denied = 0;
        $conflicts = 0;
        foreach ($ids as $id) {
            $link = $links->find($id);
            if (null === $link || !$this->isGranted(LinkVoter::MANAGE, $link)) {
                ++$denied;
                continue;
            }
            if ($link->getDomain()?->getId() === $target->getId() || $links->codeExists($target, $link->getCode())) {
                ++$conflicts;
                continue;
            }
            $link->setDomain($target);
            $this->manager->save($link);
            ++$changed;
        }

        return [$changed, $denied, $conflicts];
    }

    /** @param FormInterface<Link> $form */
    private function handleForm(Request $request, FormInterface $form, Link $link, string $template, string $flash): Response
    {
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->manager->save($link);
            $this->addFlash('success', $flash);

            return $this->redirectToRoute('app_link_show', ['id' => $link->getId()]);
        }

        return $this->render($template, ['form' => $form, 'link' => $link], new Response(
            status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK,
        ));
    }
}
