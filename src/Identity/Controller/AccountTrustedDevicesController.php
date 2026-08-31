<?php

declare(strict_types=1);

namespace App\Identity\Controller;

use App\Identity\Entity\User;
use App\Identity\Service\AccountTrustedDevices;
use App\Shared\Controller\RequiresValidFormTrait;
use Nowo\DeviceIntelligence\Device\DeviceId;
use Nowo\DeviceIntelligence\Device\DeviceManager;
use Nowo\DeviceIntelligence\Exception\InvalidValueException;
use Nowo\DeviceIntelligenceBundle\Request\DeviceContext;
use Nowo\DeviceIntelligenceBundle\Trust\DeviceTrustService;
use Nowo\FormKitBundle\Form\CsrfOnlyFormFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Account → Security → Devices: explicit Device Intelligence trust (not a login factor).
 */
#[IsGranted('ROLE_USER')]
final class AccountTrustedDevicesController extends AbstractController
{
    use RequiresValidFormTrait;

    private const string DEVICE_ID = '[0-9A-HJKMNP-TV-Z]{26}';

    public function __construct(
        private readonly AccountTrustedDevices $accountTrustedDevices,
        private readonly DeviceTrustService $deviceTrust,
        private readonly DeviceManager $devices,
        private readonly CsrfOnlyFormFactory $csrfOnlyFormFactory,
    ) {
    }

    #[Route('/account/security/devices', name: 'account_security_devices', methods: ['GET'])]
    public function index(?DeviceContext $device = null): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $devices = $this->accountTrustedDevices->listFor($user, $device);
        $revokeForms = [];
        foreach ($devices as $row) {
            $revokeForms[$row->deviceId] = $this->csrfOnlyFormFactory->createNamed(
                $this->generateUrl('account_security_devices_revoke', ['deviceId' => $row->deviceId]),
                'account_device_revoke_'.$row->deviceId,
            )->createView();
        }

        $currentLabel = null;
        $currentId = $this->accountTrustedDevices->currentDeviceId($device);
        if (null !== $device) {
            $label = $device->device()->label;
            $currentLabel = '' !== $label ? $label : $currentId;
        }

        return $this->render('account/security_devices.html.twig', [
            'has_current_device' => null !== $device,
            'current_trusted' => $device?->isTrusted() ?? false,
            'current_label' => $currentLabel,
            'current_id' => $currentId,
            'devices' => $devices,
            'trustForm' => $this->csrfOnlyFormFactory->createNamed(
                $this->generateUrl('account_security_devices_trust'),
                'account_device_trust',
            )->createView(),
            'revokeForms' => $revokeForms,
        ]);
    }

    #[Route('/account/security/devices/trust', name: 'account_security_devices_trust', methods: ['POST'])]
    public function trust(Request $request, ?DeviceContext $device = null): RedirectResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $form = $this->csrfOnlyFormFactory->createNamed(
            $this->generateUrl('account_security_devices_trust'),
            'account_device_trust',
        );
        $form->handleRequest($request);
        $this->requireValidCsrfForm($form);

        if (null === $device) {
            $this->addFlash('error', 'flash.preferences.device_missing');

            return $this->redirectToRoute('account_security_devices');
        }

        $label = '' !== $device->device()->label ? $device->device()->label : null;
        $this->deviceTrust->trust(
            $device->device(),
            $this->accountTrustedDevices->identifier($user),
            null,
            $label,
        );
        $this->addFlash('success', 'flash.preferences.device_trusted');

        return $this->redirectToRoute('account_security_devices');
    }

    #[Route(
        '/account/security/devices/{deviceId}/revoke',
        name: 'account_security_devices_revoke',
        requirements: ['deviceId' => self::DEVICE_ID],
        methods: ['POST'],
    )]
    public function revoke(string $deviceId, Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $form = $this->csrfOnlyFormFactory->createNamed(
            $this->generateUrl('account_security_devices_revoke', ['deviceId' => $deviceId]),
            'account_device_revoke_'.$deviceId,
        );
        $form->handleRequest($request);
        $this->requireValidCsrfForm($form);

        try {
            $id = new DeviceId($deviceId);
        } catch (InvalidValueException) {
            throw $this->createNotFoundException();
        }

        $stored = $this->devices->get($id);
        if (null === $stored) {
            $this->addFlash('error', 'flash.preferences.device_unknown');

            return $this->redirectToRoute('account_security_devices');
        }

        $this->deviceTrust->revoke($stored, $this->accountTrustedDevices->identifier($user));
        $this->addFlash('success', 'flash.preferences.device_revoked');

        return $this->redirectToRoute('account_security_devices');
    }
}
