<?php

declare(strict_types=1);

namespace App\Shared\Pwa;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Appends Web Push handlers to the nowo-tech/pwa-bundle generated service worker.
 */
#[AsEventListener(event: KernelEvents::RESPONSE)]
final class PushServiceWorkerListener
{
    private const string PUSH_SCRIPT = <<<'JS'

/* Beacon Web Push (appended) */
self.addEventListener('push', (event) => {
  let data = {};
  try {
    data = event.data ? event.data.json() : {};
  } catch (error) {
    data = { summary: event.data ? event.data.text() : 'New Beacon alert' };
  }
  const title = data.summary || 'New issue';
  const options = {
    body: (data.project && data.project.name ? data.project.name + ': ' : '') + (data.issue && data.issue.title ? data.issue.title : title),
    icon: '/icons/icon-192.png',
    badge: '/icons/icon-192.png',
    data: { url: data.url || '/dashboard' },
    tag: data.issue && data.issue.uuid ? ('issue-' + data.issue.uuid) : 'beacon-issue',
    renotify: true,
  };
  event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const targetUrl = (event.notification.data && event.notification.data.url) || '/dashboard';
  event.waitUntil(
    self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
      for (const client of clientList) {
        if ('focus' in client && client.url.includes(self.location.origin)) {
          client.navigate(targetUrl);
          return client.focus();
        }
      }
      if (self.clients.openWindow) {
        return self.clients.openWindow(targetUrl);
      }
      return undefined;
    }),
  );
});
JS;

    public function __invoke(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if ('nowo_pwa_service_worker' !== $request->attributes->get('_route')) {
            return;
        }

        $response = $event->getResponse();
        $content = $response->getContent();
        if (!\is_string($content) || str_contains($content, 'Beacon Web Push')) {
            return;
        }

        $response->setContent($content.self::PUSH_SCRIPT);
    }
}
