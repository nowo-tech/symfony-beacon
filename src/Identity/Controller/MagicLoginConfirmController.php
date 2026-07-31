<?php

declare(strict_types=1);

namespace App\Identity\Controller;

use LogicException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * GET interstitial for magic-login links when firewall login_link uses check_post_only.
 *
 * POST is handled by Symfony's login_link authenticator (never reaches this action).
 */
final class MagicLoginConfirmController extends AbstractController
{
    public function check(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            throw new LogicException('Magic login POST is handled by the login_link authenticator.');
        }

        $params = [];
        foreach (['user', 'expires', 'hash'] as $key) {
            $value = $request->query->get($key);
            if (\is_string($value) && '' !== $value) {
                $params[$key] = $value;
            }
        }

        if ([] === $params) {
            return $this->redirectToRoute('nowo_auth_kit_magic_login_request');
        }

        $response = $this->render('security/magic_login_confirm.html.twig', [
            'action' => $request->getPathInfo(),
            'params' => $params,
        ]);
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }
}
