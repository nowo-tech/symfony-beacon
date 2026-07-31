<?php

declare(strict_types=1);

namespace App\Identity\Controller;

use App\Identity\Entity\User;
use App\Identity\Exception\AccountAnonymizeException;
use App\Identity\Service\AccountAnonymizer;
use App\Identity\Service\AccountDataExporter;
use App\Identity\Service\UserActionRecorder;
use App\Identity\UserActionType;
use JsonException;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Account privacy: GDPR data export and self-service anonymize.
 */
#[IsGranted('ROLE_USER')]
final class AccountPrivacyController extends AbstractController
{
    public function __construct(
        private readonly AccountDataExporter $accountDataExporter,
        private readonly AccountAnonymizer $accountAnonymizer,
        private readonly UserActionRecorder $actionRecorder,
        private readonly TokenStorageInterface $tokenStorage,
    ) {
    }

    #[Route('/account/privacy', name: 'account_privacy', methods: ['GET'])]
    public function privacy(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('account/privacy.html.twig', [
            'profile_user' => $user,
            'sole_owner_projects' => $this->accountAnonymizer->soleOwnerProjects($user),
            'is_last_admin' => $this->accountAnonymizer->isLastAdmin($user),
            'can_anonymize' => !$user->isAnonymized()
                && [] === $this->accountAnonymizer->soleOwnerProjects($user)
                && !$this->accountAnonymizer->isLastAdmin($user),
        ]);
    }

    #[Route('/account/privacy/export', name: 'account_privacy_export', methods: ['GET'])]
    public function export(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $payload = $this->accountDataExporter->export($user);
        $this->actionRecorder->recordAndFlush(
            UserActionType::AccountExported,
            $user,
            $user,
            ['scope' => 'self'],
        );

        return $this->jsonDownload($payload, $user->getUuid());
    }

    #[Route('/account/privacy/anonymize', name: 'account_privacy_anonymize', methods: ['POST'])]
    public function anonymize(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$this->isCsrfTokenValid('account_privacy_anonymize', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        try {
            $this->accountAnonymizer->anonymize($user, $user);
        } catch (AccountAnonymizeException $e) {
            $this->addFlash('error', $this->flashForAnonymizeException($e));

            return $this->redirectToRoute('account_privacy');
        }

        $request->getSession()->invalidate();
        $this->tokenStorage->setToken(null);
        $this->addFlash('success', 'flash.privacy.anonymized');

        return $this->redirectToRoute('nowo_auth_kit_login');
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function jsonDownload(array $payload, string $uuid): Response
    {
        try {
            $json = json_encode($payload, \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES);
        } catch (JsonException $e) {
            throw new RuntimeException('Unable to encode account export.', 0, $e);
        }

        return new Response($json, Response::HTTP_OK, [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="beacon-account-'.$uuid.'.json"',
        ]);
    }

    private function flashForAnonymizeException(AccountAnonymizeException $e): string
    {
        return match ($e->reasonCode) {
            AccountAnonymizeException::ALREADY_ANONYMIZED => 'flash.privacy.already_anonymized',
            AccountAnonymizeException::SOLE_OWNER => 'flash.privacy.sole_owner',
            AccountAnonymizeException::LAST_ADMIN => 'flash.privacy.last_admin',
            default => 'flash.privacy.anonymize_failed',
        };
    }
}
