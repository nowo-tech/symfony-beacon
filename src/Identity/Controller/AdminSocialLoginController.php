<?php

declare(strict_types=1);

namespace App\Identity\Controller;

use App\Identity\Service\SocialLoginCredentialSeeder;
use App\Shared\Settings\Form\SocialLoginCredentialType;
use Nowo\AuthKitBundle\Entity\SocialLoginCredential;
use Nowo\AuthKitBundle\Repository\SocialLoginCredentialRepository;
use Nowo\AuthKitBundle\SocialLogin\SocialLoginGate;
use Nowo\FormKitBundle\Form\CsrfOnlyFormFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Dashboard CRUD for AuthKit social OAuth app credentials ({@see SocialLoginCredential}).
 */
#[IsGranted('ROLE_ADMIN')]
final class AdminSocialLoginController extends AbstractController
{
    public function __construct(
        private readonly SocialLoginCredentialRepository $credentials,
        private readonly SocialLoginCredentialSeeder $seeder,
        private readonly SocialLoginGate $socialLoginGate,
        private readonly CsrfOnlyFormFactory $csrfOnlyFormFactory,
    ) {
    }

    #[Route('/admin/social-login', name: 'admin_social_login', methods: ['GET'])]
    public function index(): Response
    {
        /** @var list<SocialLoginCredential> $rows */
        $rows = $this->credentials->findBy([], ['label' => 'ASC']);
        $deleteForms = [];
        foreach ($rows as $row) {
            $provider = $row->getProvider();
            $deleteForms[$provider] = $this->csrfOnlyFormFactory->createNamed(
                $this->generateUrl('admin_social_login_delete', ['provider' => $provider]),
                'admin_social_login_delete_'.$provider,
            )->createView();
        }

        $existing = [];
        foreach ($rows as $row) {
            $existing[$row->getProvider()] = true;
        }

        $missingBuiltins = [];
        foreach (SocialLoginCredentialSeeder::BUILTIN_PROVIDERS as $provider) {
            if (!isset($existing[$provider])) {
                $missingBuiltins[] = $provider;
            }
        }

        return $this->render('admin/social_login/index.html.twig', [
            'credentials' => $rows,
            'missing_builtins' => $missingBuiltins,
            'social_login_active' => $this->socialLoginGate->isEnabled(),
            'deleteForms' => $deleteForms,
        ]);
    }

    #[Route('/admin/social-login/new', name: 'admin_social_login_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $preset = strtolower(trim($request->query->getString('provider')));
        if ('' !== $preset && !\in_array($preset, SocialLoginCredentialSeeder::BUILTIN_PROVIDERS, true)) {
            $preset = '';
        }

        if ('' !== $preset && $this->credentials->findOneByProvider($preset) instanceof SocialLoginCredential) {
            return $this->redirectToRoute('admin_social_login_edit', ['provider' => $preset]);
        }

        $isBuiltinPreset = '' !== $preset;
        $form = $this->createForm(SocialLoginCredentialType::class, [
            'provider' => $preset,
            'label' => $isBuiltinPreset ? ucfirst($preset) : '',
            'client_id' => '',
            'client_secret' => '',
            'enabled' => true,
            'enterprise_sso' => false,
            'authorize_url' => '',
            'token_url' => '',
            'userinfo_url' => '',
            'scopes' => '',
        ], [
            'is_new' => true,
            'provider_locked' => $isBuiltinPreset,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array{
             *     provider: string,
             *     label: string,
             *     client_id: string,
             *     client_secret: string,
             *     enabled: bool,
             *     enterprise_sso: bool,
             *     authorize_url: string,
             *     token_url: string,
             *     userinfo_url: string,
             *     scopes: string
             * } $data
             */
            $data = $form->getData();
            $slug = $isBuiltinPreset ? $preset : strtolower(trim($data['provider']));

            if ($this->credentials->findOneByProvider($slug) instanceof SocialLoginCredential) {
                $this->addFlash('error', 'flash.social_login.provider_exists');

                return $this->redirectToRoute('admin_social_login_edit', ['provider' => $slug]);
            }

            if (!$this->urlsValidForProvider($slug, $data)) {
                $this->addFlash('error', 'flash.social_login.custom_urls_required');

                return $this->render('admin/social_login/form.html.twig', [
                    'form' => $form,
                    'provider' => $slug,
                    'is_new' => true,
                    'credential' => null,
                ]);
            }

            $this->persistFromForm($slug, $data, null);
            $this->addFlash('success', 'flash.social_login.saved');

            return $this->redirectToRoute('admin_social_login');
        }

        return $this->render('admin/social_login/form.html.twig', [
            'form' => $form,
            'provider' => $preset,
            'is_new' => true,
            'credential' => null,
        ]);
    }

    #[Route('/admin/social-login/{provider}/edit', name: 'admin_social_login_edit', requirements: ['provider' => '[a-z][a-z0-9_-]*'], methods: ['GET', 'POST'])]
    public function edit(Request $request, string $provider): Response
    {
        $credential = $this->credentials->findOneByProvider($provider);
        if (!$credential instanceof SocialLoginCredential) {
            throw $this->createNotFoundException();
        }

        $form = $this->createForm(SocialLoginCredentialType::class, [
            'provider' => $credential->getProvider(),
            'label' => $credential->getLabel(),
            'client_id' => $credential->getClientId(),
            'client_secret' => '',
            'enabled' => $credential->isEnabled(),
            'enterprise_sso' => $credential->isEnterpriseSso(),
            'authorize_url' => $credential->getAuthorizeUrl() ?? '',
            'token_url' => $credential->getTokenUrl() ?? '',
            'userinfo_url' => $credential->getUserinfoUrl() ?? '',
            'scopes' => implode(', ', $credential->getScopes()),
        ], [
            'is_new' => false,
            'provider_locked' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array{
             *     provider: string,
             *     label: string,
             *     client_id: string,
             *     client_secret: string,
             *     enabled: bool,
             *     enterprise_sso: bool,
             *     authorize_url: string,
             *     token_url: string,
             *     userinfo_url: string,
             *     scopes: string
             * } $data
             */
            $data = $form->getData();

            if (!$this->urlsValidForProvider($credential->getProvider(), $data)) {
                $this->addFlash('error', 'flash.social_login.custom_urls_required');

                return $this->render('admin/social_login/form.html.twig', [
                    'form' => $form,
                    'provider' => $provider,
                    'is_new' => false,
                    'credential' => $credential,
                ]);
            }

            $this->persistFromForm($credential->getProvider(), $data, $credential);
            $this->addFlash('success', 'flash.social_login.saved');

            return $this->redirectToRoute('admin_social_login');
        }

        return $this->render('admin/social_login/form.html.twig', [
            'form' => $form,
            'provider' => $provider,
            'is_new' => false,
            'credential' => $credential,
        ]);
    }

    #[Route('/admin/social-login/{provider}/delete', name: 'admin_social_login_delete', requirements: ['provider' => '[a-z][a-z0-9_-]*'], methods: ['POST'])]
    public function delete(Request $request, string $provider): RedirectResponse
    {
        $credential = $this->credentials->findOneByProvider($provider);
        if (!$credential instanceof SocialLoginCredential) {
            throw $this->createNotFoundException();
        }

        $form = $this->csrfOnlyFormFactory->createNamed(
            $this->generateUrl('admin_social_login_delete', ['provider' => $provider]),
            'admin_social_login_delete_'.$provider,
        );
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            throw $this->createAccessDeniedException();
        }

        $this->seeder->delete($credential);
        $this->addFlash('success', 'flash.social_login.deleted');

        return $this->redirectToRoute('admin_social_login');
    }

    /**
     * @param array{
     *     label: string,
     *     client_id: string,
     *     client_secret: string,
     *     enabled: bool,
     *     enterprise_sso: bool,
     *     authorize_url: string,
     *     token_url: string,
     *     userinfo_url: string,
     *     scopes: string
     * } $data
     */
    private function persistFromForm(string $provider, array $data, ?SocialLoginCredential $existing): void
    {
        $secret = trim((string) ($data['client_secret'] ?? ''));
        if ('' === $secret && $existing instanceof SocialLoginCredential) {
            $secret = $existing->getClientSecret();
        }

        $this->seeder->upsert(
            $provider,
            trim($data['label']),
            trim($data['client_id']),
            $secret,
            (bool) $data['enabled'],
            $this->nullableUrl($data['authorize_url']),
            $this->nullableUrl($data['token_url']),
            $this->nullableUrl($data['userinfo_url']),
            $this->parseScopes($data['scopes'] ?? ''),
            flush: true,
            enterpriseSso: (bool) $data['enterprise_sso'],
        );
    }

    /**
     * @param array{authorize_url: string, token_url: string, userinfo_url: string} $data
     */
    private function urlsValidForProvider(string $provider, array $data): bool
    {
        if (\in_array($provider, SocialLoginCredentialSeeder::BUILTIN_PROVIDERS, true)) {
            return true;
        }

        return !\in_array('', [trim($data['authorize_url']), trim($data['token_url']), trim($data['userinfo_url'])], true);
    }

    private function nullableUrl(?string $value): ?string
    {
        $trimmed = trim($value ?? '');

        return '' === $trimmed ? null : $trimmed;
    }

    /**
     * @return list<string>
     */
    private function parseScopes(string $raw): array
    {
        $parts = preg_split('/[\s,]+/', trim($raw)) ?: [];
        $scopes = [];
        foreach ($parts as $part) {
            $scope = trim($part);
            if ('' !== $scope) {
                $scopes[] = $scope;
            }
        }

        return array_values(array_unique($scopes));
    }
}
