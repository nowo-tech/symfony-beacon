<?php

declare(strict_types=1);

namespace App\Shared\Controller;

use App\Identity\Entity\User;
use App\Identity\Service\ProductTourStepsBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Administration hub for ROLE_ADMIN operators.
 */
#[IsGranted('ROLE_ADMIN')]
final class AdminHubController extends AbstractController
{
    public function __construct(
        private readonly ProductTourStepsBuilder $productTourStepsBuilder,
    ) {
    }

    #[Route('/admin', name: 'admin_hub', methods: ['GET'])]
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $tourVars = $this->productTourStepsBuilder->twigVars(
            $this->productTourStepsBuilder->contextForAdmin(),
            $user,
            $request,
        );

        return $this->render('admin/hub.html.twig', $tourVars);
    }
}
