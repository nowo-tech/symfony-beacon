<?php

declare(strict_types=1);

namespace App\Identity\Controller;

use App\Identity\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Minimal user directory for ROLE_ADMIN.
 * Prefer nowo-tech/user-kit-bundle for enable/disable, activity, and richer account UX.
 */
#[IsGranted('ROLE_ADMIN')]
final class AdminUserController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
    ) {
    }

    #[Route('/admin/users', name: 'admin_users', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/users/index.html.twig', [
            'users' => $this->userRepository->findBy([], ['email' => 'ASC']),
        ]);
    }
}
