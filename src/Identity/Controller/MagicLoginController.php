<?php

declare(strict_types=1);

namespace App\Identity\Controller;

use App\Identity\Entity\User;
use App\Identity\Repository\UserRepository;
use App\Identity\Service\UserActionRecorder;
use App\Identity\UserActionType;
use App\Shared\Mailer\ConfiguredMailer;
use LogicException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\Email;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\LoginLink\LoginLinkHandlerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Passwordless magic-link login (Symfony Security login_link + Mailer).
 */
final class MagicLoginController extends AbstractController
{
    public function __construct(
        #[Autowire(service: 'security.authenticator.login_link_handler.main')]
        private readonly LoginLinkHandlerInterface $loginLinkHandler,
        private readonly UserRepository $userRepository,
        private readonly ConfiguredMailer $mailer,
        private readonly UserActionRecorder $userActionRecorder,
        private readonly TranslatorInterface $translator,
        #[Autowire(service: 'limiter.magic_login')]
        private readonly RateLimiterFactory $magicLoginLimiter,
    ) {
    }

    #[Route('/login/magic', name: 'app_magic_login_request', methods: ['GET', 'POST'])]
    public function request(Request $request): Response
    {
        if ($this->getUser() instanceof User) {
            return $this->redirectToRoute('dashboard_home');
        }

        if (!$this->mailer->isMagicLoginAvailable()) {
            $this->addFlash('error', 'auth.magic.unavailable');

            return $this->redirectToRoute('nowo_auth_kit_login');
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('magic_login', $request->request->getString('_token'))) {
                $this->addFlash('error', 'auth.magic.invalid');

                return $this->redirectToRoute('app_magic_login_request');
            }

            $limiter = $this->magicLoginLimiter->create($request->getClientIp() ?? 'unknown');
            if (!$limiter->consume(1)->isAccepted()) {
                $this->addFlash('error', 'auth.magic.throttled');

                return $this->redirectToRoute('app_magic_login_request');
            }

            $email = trim(strtolower($request->request->getString('email')));
            if ('' !== $email && filter_var($email, \FILTER_VALIDATE_EMAIL)) {
                $user = $this->userRepository->findOneBy(['email' => $email]);
                if ($user instanceof User && $user->isEnabled()) {
                    $details = $this->loginLinkHandler->createLoginLink($user, $request);
                    $message = new Email()
                        ->from($this->mailer->getFromAddress())
                        ->to($user->getEmail())
                        ->subject($this->translator->trans('auth.magic.email_subject'))
                        ->text($this->translator->trans('auth.magic.email_body', [
                            '%link%' => $details->getUrl(),
                            '%expires%' => $details->getExpiresAt()->format('Y-m-d H:i:s T'),
                        ]));
                    $this->mailer->send($message);
                    $this->userActionRecorder->recordAndFlush(UserActionType::MagicLoginRequested, $user, $user, [
                        'email' => $user->getEmail(),
                    ]);
                }
            }

            // Anti-enumeration: same message whether or not the account exists.
            $this->addFlash('success', 'auth.magic.sent');

            return $this->redirectToRoute('app_magic_login_request');
        }

        return $this->render('security/magic_login.html.twig');
    }

    #[Route('/login/magic/check', name: 'app_magic_login_check')]
    public function check(): never
    {
        throw new LogicException('Magic login check is handled by the security authenticator.');
    }
}
