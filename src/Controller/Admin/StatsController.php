<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Repository\ClickRepository;
use App\Repository\DomainRepository;
use App\Repository\LinkRepository;
use App\Repository\UserRepository;
use App\Stats\Period;
use App\Stats\StatsBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ADMIN_ACCESS')]
final class StatsController extends AbstractController
{
    #[Route('/admin/stats', name: 'app_admin_stats', methods: ['GET'])]
    public function __invoke(
        Request $request,
        StatsBuilder $stats,
        ClickRepository $clicks,
        LinkRepository $links,
        UserRepository $users,
        DomainRepository $domains,
    ): Response {
        $period = Period::lastDays($request->query->getInt('days', 30));

        return $this->render('admin/stats.html.twig', [
            'stats' => $stats->build($period),
            'top_links' => $clicks->topLinks($period->from, $period->to),
            'periods' => Period::CHOICES,
            'counts' => [
                'links' => $links->countFor(),
                'users' => $users->count([]),
                'domains' => $domains->count([]),
            ],
        ]);
    }
}
