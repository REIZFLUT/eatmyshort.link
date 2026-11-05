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

        // The dashboard quick form only posts target, domain and code; a full form would
        // read the missing "active" checkbox as unchecked.
        $full = !$request->isMethod('POST') || \array_key_exists('redirectType', $request->request->all('link'));
        $form = $this->createForm(LinkType::class, $link, ['domains' => $usable, 'full' => $full]);

        return $this->handleForm($request, $form, $link, 'link/new.html.twig', 'flash.link_created');
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
