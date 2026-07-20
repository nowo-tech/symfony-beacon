<?php

declare(strict_types=1);

namespace App\Shared\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Administration hub for ROLE_ADMIN operators.
 */
#[IsGranted('ROLE_ADMIN')]
final class AdminHubController extends AbstractController
{
    #[Route('/admin', name: 'admin_hub', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/hub.html.twig');
    }
}
