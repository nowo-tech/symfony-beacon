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
const BEACON_PUSH_EVENT_TITLES = {
  'issue.new': 'New issue',
  'issue.regression': 'Issue regression',
  'issue.resolved': 'Issue resolved',
  'issue.reopened': 'Issue reopened',
  'issue.assigned': 'Issue assigned',
  'issue.commented': 'New comment',
};

function beaconPushTruncate(text, max) {
  if (!text || text.length <= max) {
    return text || '';
  }
  return text.slice(0, max - 1).trimEnd() + '…';
}

function beaconPushIssuePreview(title, culprit) {
  let text = (title || '').trim() || (culprit || '').trim();
  if (!text) {
    return '';
  }
  text = (text.split(/\r?\n/)[0] || text).trim();
  text = text.replace(/^((?:[A-Za-z_][\w$]*\\)+)([A-Za-z_][\w$]*)\b/, '$2');
  return beaconPushTruncate(text, 110);
}

self.addEventListener('push', (event) => {
  let data = {};
  try {
    data = event.data ? event.data.json() : {};
  } catch (error) {
    data = { summary: event.data ? event.data.text() : 'New Beacon alert' };
  }
  const eventKey = typeof data.event === 'string' ? data.event : '';
  const title = BEACON_PUSH_EVENT_TITLES[eventKey] || (eventKey ? 'New alert' : null) || beaconPushTruncate(data.summary || 'New alert', 80);
  const preview = beaconPushIssuePreview(
    data.issue && data.issue.title,
    data.issue && data.issue.culprit,
  );
  const projectName = data.project && data.project.name ? String(data.project.name).trim() : '';
  let body = '';
  if (projectName && preview) {
    body = projectName + ' · ' + preview;
  } else if (preview) {
    body = preview;
  } else if (projectName) {
    body = projectName;
  } else if (data.summary && data.summary !== title) {
    body = beaconPushTruncate(String(data.summary), 110);
  }
  const options = {
    body: body || title,
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
