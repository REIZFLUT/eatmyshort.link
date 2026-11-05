<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Link;
use App\Entity\User;
use App\Form\LinkType;
use App\Link\LinkManager;
use App\Repository\DomainRepository;
use App\Repository\LinkRepository;
use App\Security\AdminAccess;
use App\Stats\Period;
use App\Stats\StatsBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class DashboardController extends AbstractController
{
    #[Route('/', name: 'app_dashboard', methods: ['GET'])]
    public function index(
        #[CurrentUser] User $user,
        DomainRepository $domains,
        LinkRepository $links,
        LinkManager $manager,
        StatsBuilder $stats,
        AdminAccess $adminAccess,
    ): Response {
        $usable = $domains->findUsableBy($user, $adminAccess->isAdmin($user));
        $link = new Link($user);
        $link->setDomain($usable[0] ?? null);
        $manager->applyDefaults($link);

        $quickForm = [] === $usable ? null : $this->createForm(LinkType::class, $link, [
            'domains' => $usable,
            'full' => false,
            'autofocus' => true,
            'action' => $this->generateUrl('app_link_new'),
        ]);

        return $this->render('dashboard/index.html.twig', [
            'quick_form' => $quickForm,
            'recent' => $links->findRecentFor($user, 8),
            'link_count' => $links->countFor($user),
            'stats' => $stats->build(Period::lastDays(30), owner: $user),
        ]);
    }
}
