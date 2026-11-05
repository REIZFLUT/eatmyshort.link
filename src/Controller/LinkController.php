<?php

declare(strict_types=1);

namespace App\Controller;

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
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/links')]
final class LinkController extends AbstractController
{
    private const int PER_PAGE = 25;

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

        $query = $links->searchQuery($user, $search, $domain, $onlyOwn)
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
            'domains' => $isAdmin ? $this->domains->findAllOrdered() : $this->domains->findUsableBy($user, false),
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

        // The bookmarklet and the popup flow hand over the target and title as query
        // parameters. Only take them over for the GET render and only when they look
        // plausible; a POST must keep using whatever the (validated) form carries.
        if ($request->isMethod('GET')) {
            $target = self::plausibleUrl($request->query->get('url'));
            if (null !== $target) {
                $link->setTargetUrl($target);
            }
            $title = self::plausibleTitle($request->query->get('title'));
            if (null !== $title) {
                $link->setTitle($title);
            }
        }

        // The dashboard quick form only posts target, domain and code; a full form would
        // read the missing "active" checkbox as unchecked.
        $full = !$request->isMethod('POST') || \array_key_exists('redirectType', $request->request->all('link'));
        $form = $this->createForm(LinkType::class, $link, ['domains' => $usable, 'full' => $full]);

        return $this->handleForm($request, $form, $link, 'link/new.html.twig', 'flash.link_created');
    }

    /**
     * Confirmation card of the bookmarklet popup flow. Rendering a form here - never
     * creating a link from a GET - keeps the flow CSRF-protected.
     */
    #[Route('/quick', name: 'app_link_quick', methods: ['GET'])]
    public function quick(Request $request, #[CurrentUser] User $user): Response
    {
        return $this->render('link/quick.html.twig', [
            'url' => self::plausibleUrl($request->query->get('url')),
            'title' => self::plausibleTitle($request->query->get('title')),
            'code' => self::plausibleCode($request->query->get('code')),
            'goto' => $request->query->getBoolean('goto'),
            'domain' => $this->domains->findUsableBy($user, $this->adminAccess->isAdmin($user))[0] ?? null,
        ]);
    }

    #[Route('/quick', name: 'app_link_quick_create', methods: ['POST'])]
    public function quickCreate(Request $request, #[CurrentUser] User $user, TranslatorInterface $translator): Response
    {
        if (!$this->isCsrfTokenValid('link-quick', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $usable = $this->domains->findUsableBy($user, $this->adminAccess->isAdmin($user));
        if ([] === $usable) {
            $this->addFlash('warning', 'flash.no_domains');

            return $this->redirectToRoute('app_dashboard');
        }

        $url = self::plausibleUrl($request->request->get('url'));
        $rawCode = $request->request->get('code');
        $code = \is_string($rawCode) ? trim($rawCode) : '';
        $goto = $request->request->getBoolean('goto');

        // This flow always uses the user's default (first usable) domain - no domain parameter.
        $link = new Link($user);
        $link->setDomain($usable[0]);
        if (null !== $url) {
            $link->setTargetUrl($url);
        }
        if ('' !== $code) {
            $link->setCode($code);
        }
        $this->manager->applyDefaults($link);

        $violations = $this->manager->validate($link);
        if (0 === \count($violations)) {
            $this->manager->save($link);
            $this->addFlash('success', $translator->trans('flash.link_quick_created', ['%url%' => $link->getShortUrl()]));

            return $goto
                ? $this->redirect($link->getTargetUrl())
                : $this->redirectToRoute('app_link_show', ['id' => $link->getId()]);
        }

        $messages = [];
        foreach ($violations as $violation) {
            $messages[] = $violation->getMessage();
        }

        return $this->render('link/quick.html.twig', [
            'url' => $url,
            'title' => self::plausibleTitle($request->request->get('title')),
            'code' => $code,
            'goto' => $goto,
            'domain' => $usable[0],
            'violations' => $messages,
        ], new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY));
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
     * Only http(s) URLs up to 2000 characters are taken over; anything else (javascript:,
     * data:, a missing scheme, ...) is dropped instead of being reflected into the form.
     */
    private static function plausibleUrl(mixed $value): ?string
    {
        if (!\is_string($value)) {
            return null;
        }
        $value = trim($value);
        if ('' === $value || \strlen($value) > 2000) {
            return null;
        }
        $scheme = strtolower((string) parse_url($value, \PHP_URL_SCHEME));

        return \in_array($scheme, ['http', 'https'], true) ? $value : null;
    }

    private static function plausibleTitle(mixed $value): ?string
    {
        if (!\is_string($value)) {
            return null;
        }
        $value = trim($value);
        if ('' === $value || mb_strlen($value) > 255) {
            return null;
        }

        return $value;
    }

    /** Only prefill a code that already matches the format the server will accept. */
    private static function plausibleCode(mixed $value): string
    {
        if (!\is_string($value)) {
            return '';
        }
        $value = trim($value);

        return 1 === preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_-]{0,63}$/', $value) ? $value : '';
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
