<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\AdminIpRule;
use App\Repository\AdminIpRuleRepository;
use App\Security\AdminIpPolicy;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[IsGranted('ADMIN_ACCESS')]
#[Route('/admin/ip-rules')]
final class IpRuleController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AdminIpRuleRepository $rules,
        private readonly AdminIpPolicy $policy,
    ) {
    }

    #[Route('', name: 'app_admin_ip_rules', methods: ['GET', 'POST'])]
    public function index(Request $request, ValidatorInterface $validator): Response
    {
        $rule = new AdminIpRule();
        $errors = [];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('ip-rule-add', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException();
            }
            $rule->setCidr((string) $request->request->get('cidr'))->setLabel((string) $request->request->get('label'));
            foreach ($validator->validate($rule) as $violation) {
                $errors[] = $violation->getMessage();
            }
            if ([] === $errors && !$this->policy->wouldAllow($request->getClientIp(), [...$this->rules->findAllCidrs(), $rule->getCidr()])) {
                $errors[] = 'ip_rule.would_lock_out';
            }
            if ([] === $errors) {
                $this->em->persist($rule);
                $this->em->flush();
                $this->addFlash('success', 'flash.ip_rule_added');

                return $this->redirectToRoute('app_admin_ip_rules');
            }
        }

        return $this->render('admin/ip_rules.html.twig', [
            'rules' => $this->rules->findAllOrdered(),
            'env_rules' => $this->policy->getEnvRules(),
            'client_ip' => $request->getClientIp(),
            'restricted' => $this->policy->isRestricted(),
            'errors' => $errors,
            'new_rule' => $rule,
        ], new Response(status: [] === $errors ? Response::HTTP_OK : Response::HTTP_UNPROCESSABLE_ENTITY));
    }

    #[Route('/{id}/delete', name: 'app_admin_ip_rule_delete', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function delete(Request $request, AdminIpRule $rule): Response
    {
        if (!$this->isCsrfTokenValid('ip-rule-delete-'.$rule->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $remaining = array_values(array_diff($this->rules->findAllCidrs(), [$rule->getCidr()]));
        if (!$this->policy->wouldAllow($request->getClientIp(), $remaining)) {
            $this->addFlash('danger', 'ip_rule.would_lock_out');

            return $this->redirectToRoute('app_admin_ip_rules');
        }
        $this->em->remove($rule);
        $this->em->flush();
        $this->addFlash('success', 'flash.ip_rule_deleted');

        return $this->redirectToRoute('app_admin_ip_rules');
    }
}
