<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Identity\Entity\User;
use App\Identity\Form\MemberAlertPreferencesType;
use App\Notifications\Enum\MemberAlertEvent;
use App\Notifications\Repository\PushSubscriptionRepository;
use App\Notifications\Service\MemberAlertPreferenceManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * Account member-alert preferences with Live cascading visibility.
 */
#[AsLiveComponent]
#[IsGranted('ROLE_USER')]
final class MemberAlertPreferencesLive extends AbstractController
{
    use ComponentWithFormTrait;
    use DefaultActionTrait;

    /**
     * @var array{
     *     memberAlertsEnabled?: bool,
     *     events?: array<string, array{enabled: bool, involved: bool}>,
     *     pushNotificationsEnabled?: bool
     * }
     */
    #[LiveProp]
    public array $initialFormData = [];

    #[LiveProp]
    public bool $pushAvailable = false;

    /**
     * @var list<array{uuid: string, name: string, hasOverrides: bool, formData: array<string, mixed>}>
     */
    #[LiveProp]
    public array $projects = [];

    public function __construct(
        private readonly MemberAlertPreferenceManager $memberAlertPreferenceManager,
        private readonly PushSubscriptionRepository $pushSubscriptionRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /** @return FormInterface<mixed> */
    protected function instantiateForm(): FormInterface
    {
        return $this->createForm(MemberAlertPreferencesType::class, $this->initialFormData, [
            'push_available' => $this->pushAvailable,
            // Live endpoint already requires X-Requested-With (+ signed props). Stateless
            // SameOrigin CSRF (`submit`) is one-shot and 422s on save after Live re-renders.
            'csrf_protection' => false,
        ]);
    }

    public function isMasterEnabled(): bool
    {
        return (bool) $this->getForm()->get('memberAlertsEnabled')->getData();
    }

    #[LiveAction]
    public function save(): RedirectResponse
    {
        $this->submitForm();
        /** @var User $user */
        $user = $this->getUser();
        /** @var array<string, mixed> $data */
        $data = $this->getForm()->getData();
        $masterEnabled = (bool) ($data['memberAlertsEnabled'] ?? true);
        /** @var array<string, mixed> $rawEvents */
        $rawEvents = \is_array($data['events'] ?? null) ? $data['events'] : [];
        $this->memberAlertPreferenceManager->saveAccountEvents(
            $user,
            $masterEnabled,
            MemberAlertEvent::mapEventsFromFormKeys($rawEvents),
        );
        if ($this->getForm()->has('pushNotificationsEnabled')) {
            $user->setPushNotificationsEnabled((bool) ($data['pushNotificationsEnabled'] ?? true));
            if (!$user->isPushNotificationsEnabled()) {
                foreach ($this->pushSubscriptionRepository->findByUser($user) as $subscription) {
                    $this->entityManager->remove($subscription);
                }
            }
        }
        $this->entityManager->flush();
        $this->addFlash('success', 'flash.preferences.display_saved');

        return $this->redirectToRoute('account_display_notifications');
    }
}
