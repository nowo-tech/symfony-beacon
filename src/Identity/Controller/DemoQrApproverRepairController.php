<?php

declare(strict_types=1);

namespace App\Identity\Controller;

use App\Identity\Service\DemoIdentitySeeder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Non-prod repair for the demo QR approver after profile tests clear phone verification.
 *
 * Playwright cannot exec `app:seed-demo` from the official Playwright image (no Docker socket).
 */
#[IsGranted('ROLE_ADMIN')]
final class DemoQrApproverRepairController extends AbstractController
{
    public function __construct(
        private readonly DemoIdentitySeeder $demoIdentitySeeder,
        #[Autowire('%kernel.environment%')]
        private readonly string $environment,
    ) {
    }

    #[Route('/_internal/demo/ensure-qr-approver', name: 'internal_demo_ensure_qr_approver', methods: ['GET'])]
    public function __invoke(): Response
    {
        if ('prod' === $this->environment) {
            throw $this->createNotFoundException();
        }

        $this->demoIdentitySeeder->ensureDemoAdminQrApprover();

        return new Response('', Response::HTTP_NO_CONTENT);
    }
}
