import { Application } from '@hotwired/stimulus';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import IssueRealtimeController from './issue_realtime_controller';

class FakeEventSource {
  static instances: FakeEventSource[] = [];
  url: string;
  onmessage: ((event: MessageEvent) => void) | null = null;
  closed = false;

  constructor(url: string) {
    this.url = url;
    FakeEventSource.instances.push(this);
  }

  close(): void {
    this.closed = true;
  }
}

describe('issue-realtime controller', () => {
  let application: Application;
  let fetchMock: ReturnType<typeof vi.fn>;

  beforeEach(() => {
    FakeEventSource.instances = [];
    vi.stubGlobal('EventSource', FakeEventSource as unknown as typeof EventSource);
    fetchMock = vi.fn();
    vi.stubGlobal('fetch', fetchMock);
    document.body.innerHTML = `
      <div
        data-controller="issue-realtime"
        data-issue-realtime-enabled-value="true"
        data-issue-realtime-config-url-value="/config"
        data-issue-realtime-subscribe-url-value="/subscribe"
        data-issue-realtime-unsubscribe-url-value="/unsubscribe"
        data-issue-realtime-csrf-token-value="csrf"
        data-issue-realtime-labels-value='{"viewIssue":"View issue","fallbackTitle":"New alert","events":{"issue.assigned":"Issue assigned"}}'
      ></div>
    `;
  });

  afterEach(() => {
    application?.stop();
    document.body.innerHTML = '';
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
  });

  const start = async (): Promise<IssueRealtimeController> => {
    application?.stop();
    fetchMock.mockClear();
    application = Application.start();
    application.register('issue-realtime', IssueRealtimeController);
    await Promise.resolve();
    await Promise.resolve();
    const el = document.querySelector('[data-controller="issue-realtime"]') as HTMLElement;
    return application.getControllerForElementAndIdentifier(
      el,
      'issue-realtime',
    ) as IssueRealtimeController;
  };

  it('connects Mercure and shows structured toast on message', async () => {
    fetchMock.mockResolvedValue({
      ok: true,
      json: async () => ({
        mercure: {
          enabled: true,
          hubUrl: 'https://hub.example/.well-known/mercure',
          token: 'tok',
          topics: ['issues'],
        },
        push: { preferenceEnabled: false, vapidPublicKey: null, configured: false },
      }),
    });

    await start();
    expect(FakeEventSource.instances).toHaveLength(1);
    const es = FakeEventSource.instances[0];
    es.onmessage?.(
      new MessageEvent('message', {
        data: JSON.stringify({
          event: 'issue.assigned',
          summary:
            'Issue assigned: [error] Symfony\\Component\\Mercure\\Exception\\InvalidArgumentException: "$grants" must be a list',
          url: '/issues/1',
          project: { name: 'Symfony Beacon' },
          issue: {
            id: 4110,
            title:
              'Symfony\\Component\\Mercure\\Exception\\InvalidArgumentException: "$grants" must be a list of Grant instances, string given.',
            culprit: 'App\\Fail::run',
            level: 'error',
          },
        }),
      }),
    );

    const toast = document.querySelector('.flash-toast') as HTMLElement;
    expect(toast).toBeTruthy();
    expect(toast.classList.contains('flash-info')).toBe(true);
    expect(toast.querySelector('.nowo-ui-toast__title')?.textContent).toBe('Issue assigned');
    expect(toast.querySelector('.nowo-ui-toast__message')?.textContent).toContain('Symfony Beacon ·');
    expect(toast.querySelector('.nowo-ui-toast__message')?.textContent).toContain('InvalidArgumentException');
    expect(toast.querySelector('.nowo-ui-toast__message')?.textContent).not.toContain(
      'Symfony\\Component\\Mercure',
    );
    const link = toast.querySelector('a.nowo-ui-toast__action') as HTMLAnchorElement;
    expect(link.getAttribute('href')).toBe('/issues/1');
    expect(link.textContent).toBe('View issue');
  });

  it('ignores invalid hub URLs and disabled mercure', async () => {
    fetchMock.mockResolvedValue({
      ok: true,
      json: async () => ({
        mercure: { enabled: true, hubUrl: '/relative', token: 't', topics: ['x'] },
        push: { preferenceEnabled: false, vapidPublicKey: null, configured: false },
      }),
    });
    await start();
    expect(FakeEventSource.instances).toHaveLength(0);

    fetchMock.mockResolvedValue({
      ok: true,
      json: async () => ({
        mercure: { enabled: false, hubUrl: null, token: null, topics: [] },
        push: { preferenceEnabled: false, vapidPublicKey: null, configured: false },
      }),
    });
    const controller = await start();
    await controller.enablePush();
    expect(FakeEventSource.instances).toHaveLength(0);
  });

  it('drops javascript and cross-origin toast URLs', async () => {
    fetchMock.mockResolvedValue({
      ok: true,
      json: async () => ({
        mercure: {
          enabled: true,
          hubUrl: 'https://hub.example/m',
          token: 'tok',
          topics: ['t'],
        },
        push: { preferenceEnabled: false, vapidPublicKey: null, configured: false },
      }),
    });
    await start();
    FakeEventSource.instances[0].onmessage?.(
      new MessageEvent('message', {
        data: JSON.stringify({
          event: 'issue.new',
          url: 'javascript:alert(1)',
          project: { name: 'P' },
          issue: { title: 'T' },
        }),
      }),
    );
    expect(document.querySelector('a.nowo-ui-toast__action')).toBeNull();

    document.body.querySelector('.flash-toast')?.remove();
    FakeEventSource.instances[0].onmessage?.(
      new MessageEvent('message', {
        data: JSON.stringify({
          event: 'issue.new',
          url: 'https://evil.example/phish',
          project: { name: 'P' },
          issue: { title: 'T' },
        }),
      }),
    );
    expect(document.querySelector('a.nowo-ui-toast__action')).toBeNull();

    document.body.querySelector('.flash-toast')?.remove();
    FakeEventSource.instances[0].onmessage?.(
      new MessageEvent('message', {
        data: JSON.stringify({
          event: 'issue.new',
          url: `${window.location.origin}/issues/9`,
          project: { name: 'P' },
          issue: { title: 'T' },
        }),
      }),
    );
    const link = document.querySelector('a.nowo-ui-toast__action') as HTMLAnchorElement;
    expect(link.getAttribute('href')).toBe('/issues/9');
  });

  it('shows fallback toast on invalid JSON payload', async () => {
    fetchMock.mockResolvedValue({
      ok: true,
      json: async () => ({
        mercure: {
          enabled: true,
          hubUrl: 'https://hub.example/m',
          token: 'tok',
          topics: ['t'],
        },
        push: { preferenceEnabled: false, vapidPublicKey: null, configured: false },
      }),
    });
    await start();
    FakeEventSource.instances[0].onmessage?.(new MessageEvent('message', { data: 'not-json' }));
    expect(document.querySelector('.flash-toast .nowo-ui-toast__title')?.textContent).toBe('New alert');
  });

  it('no-ops when disabled', async () => {
    application?.stop();
    document.body.innerHTML = `
      <div data-controller="issue-realtime" data-issue-realtime-enabled-value="false"
        data-issue-realtime-config-url-value="/c"
        data-issue-realtime-subscribe-url-value="/s"
        data-issue-realtime-unsubscribe-url-value="/u"
        data-issue-realtime-csrf-token-value="t"></div>
    `;
    fetchMock = vi.fn();
    vi.stubGlobal('fetch', fetchMock);
    application = Application.start();
    application.register('issue-realtime', IssueRealtimeController);
    await Promise.resolve();
    await Promise.resolve();
    expect(fetchMock).not.toHaveBeenCalled();
  });

  it('disablePush posts unsubscribe when PushManager exists', async () => {
    const unsubscribe = vi.fn().mockResolvedValue(undefined);
    const subscription = {
      endpoint: 'https://push.example/1',
      unsubscribe,
      toJSON: () => ({ endpoint: 'https://push.example/1' }),
    };
    Object.defineProperty(navigator, 'serviceWorker', {
      configurable: true,
      value: {
        ready: Promise.resolve({
          pushManager: {
            getSubscription: vi.fn().mockResolvedValue(subscription),
            subscribe: vi.fn(),
          },
        }),
      },
    });
    Object.defineProperty(window, 'PushManager', { configurable: true, value: function PushManager() {} });
    fetchMock.mockResolvedValue({
      ok: true,
      json: async () => ({
        mercure: { enabled: false, hubUrl: null, token: null, topics: [] },
        push: { preferenceEnabled: false, vapidPublicKey: null, configured: false },
      }),
    });

    const controller = await start();
    fetchMock.mockClear();
    fetchMock.mockResolvedValue({ ok: true, json: async () => ({}) });
    await controller.disablePush();
    expect(unsubscribe).toHaveBeenCalled();
    expect(fetchMock).toHaveBeenCalledWith(
      '/unsubscribe',
      expect.objectContaining({ method: 'POST' }),
    );
  });

  it('closes mercure when topics or token missing and ignores fetch failures', async () => {
    fetchMock.mockResolvedValue({
      ok: true,
      json: async () => ({
        mercure: { enabled: true, hubUrl: 'https://hub.example/m', token: null, topics: ['t'] },
        push: { preferenceEnabled: false, vapidPublicKey: null, configured: false },
      }),
    });
    await start();
    expect(FakeEventSource.instances).toHaveLength(0);

    fetchMock.mockResolvedValue({ ok: false });
    await start();
    expect(FakeEventSource.instances).toHaveLength(0);

    fetchMock.mockRejectedValue(new Error('offline'));
    await start();
    expect(FakeEventSource.instances).toHaveLength(0);
  });

  it('formats toast branches for project-only preview-only summary and truncation', async () => {
    fetchMock.mockResolvedValue({
      ok: true,
      json: async () => ({
        mercure: {
          enabled: true,
          hubUrl: 'https://hub.example/m',
          token: 'tok',
          topics: ['t'],
        },
        push: { preferenceEnabled: false, vapidPublicKey: null, configured: false },
      }),
    });
    await start();
    const es = FakeEventSource.instances[0];

    es.onmessage?.(
      new MessageEvent('message', {
        data: JSON.stringify({ project: { name: 'Only project' } }),
      }),
    );
    expect(document.querySelector('.nowo-ui-toast__message')?.textContent).toBe('Only project');
    document.querySelector('.flash-toast')?.remove();

    es.onmessage?.(
      new MessageEvent('message', {
        data: JSON.stringify({ issue: { culprit: 'App\\Fail::run' } }),
      }),
    );
    expect(document.querySelector('.nowo-ui-toast__message')?.textContent).toBe('Fail::run');
    document.querySelector('.flash-toast')?.remove();

    es.onmessage?.(
      new MessageEvent('message', {
        data: JSON.stringify({
          summary: 'x'.repeat(140),
        }),
      }),
    );
    const summaryMsg = document.querySelector('.nowo-ui-toast__message')?.textContent ?? '';
    expect(summaryMsg.endsWith('…')).toBe(true);
    expect(summaryMsg.length).toBeLessThanOrEqual(110);
  });

  it('subscribes to web push when permission granted and vapid configured', async () => {
    const subscribe = vi.fn().mockResolvedValue({
      toJSON: () => ({ endpoint: 'https://fcm.googleapis.com/fcm/send/1' }),
    });
    Object.defineProperty(navigator, 'serviceWorker', {
      configurable: true,
      value: {
        ready: Promise.resolve({
          pushManager: {
            getSubscription: vi.fn().mockResolvedValue(null),
            subscribe,
          },
        }),
      },
    });
    Object.defineProperty(window, 'PushManager', { configurable: true, value: function PushManager() {} });
    // Valid-ish URL-safe base64 for urlBase64ToUint8Array (padding handled in controller).
    const vapidPublicKey = btoa(String.fromCharCode(...Array.from({ length: 65 }, (_, i) => i % 256)))
      .replace(/\+/g, '-')
      .replace(/\//g, '_')
      .replace(/=+$/g, '');
    vi.stubGlobal('Notification', {
      permission: 'granted',
      requestPermission: vi.fn(),
    });

    fetchMock.mockImplementation(async (url: RequestInfo | URL) => {
      if (String(url) === '/config') {
        return {
          ok: true,
          json: async () => ({
            mercure: { enabled: false, hubUrl: null, token: null, topics: [] },
            push: {
              preferenceEnabled: true,
              vapidPublicKey,
              configured: true,
            },
          }),
        };
      }
      return { ok: true, json: async () => ({}) };
    });

    await start();
    await vi.waitFor(() => {
      expect(subscribe).toHaveBeenCalled();
      expect(fetchMock).toHaveBeenCalledWith(
        '/subscribe',
        expect.objectContaining({ method: 'POST' }),
      );
    });
  });

  it('skips push sync when preference off or notification denied', async () => {
    Object.defineProperty(navigator, 'serviceWorker', {
      configurable: true,
      value: { ready: Promise.resolve({ pushManager: { getSubscription: vi.fn(), subscribe: vi.fn() } }) },
    });
    Object.defineProperty(window, 'PushManager', { configurable: true, value: function PushManager() {} });
    vi.stubGlobal('Notification', { permission: 'denied', requestPermission: vi.fn() });

    fetchMock.mockResolvedValue({
      ok: true,
      json: async () => ({
        mercure: { enabled: false, hubUrl: null, token: null, topics: [] },
        push: { preferenceEnabled: false, vapidPublicKey: 'x', configured: true },
      }),
    });
    await start();
    expect(fetchMock).not.toHaveBeenCalledWith('/subscribe', expect.anything());

    const vapidPublicKey = btoa(String.fromCharCode(...Array.from({ length: 65 }, (_, i) => i % 256)))
      .replace(/\+/g, '-')
      .replace(/\//g, '_')
      .replace(/=+$/g, '');
    fetchMock.mockResolvedValue({
      ok: true,
      json: async () => ({
        mercure: { enabled: false, hubUrl: null, token: null, topics: [] },
        push: { preferenceEnabled: true, vapidPublicKey, configured: true },
      }),
    });
    await start();
    expect(fetchMock).not.toHaveBeenCalledWith('/subscribe', expect.anything());
  });

  it('disablePush no-ops without PushManager and ignores unsubscribe errors', async () => {
    fetchMock.mockResolvedValue({
      ok: true,
      json: async () => ({
        mercure: { enabled: false, hubUrl: null, token: null, topics: [] },
        push: { preferenceEnabled: false, vapidPublicKey: null, configured: false },
      }),
    });
    const controller = await start();
    Reflect.deleteProperty(window, 'PushManager');
    Reflect.deleteProperty(navigator, 'serviceWorker');
    fetchMock.mockClear();
    await controller.disablePush();
    expect(fetchMock).not.toHaveBeenCalled();

    Object.defineProperty(navigator, 'serviceWorker', {
      configurable: true,
      value: {
        ready: Promise.reject(new Error('sw failed')),
      },
    });
    Object.defineProperty(window, 'PushManager', { configurable: true, value: function PushManager() {} });
    await controller.disablePush();
  });

  it('requests notification permission when default and aborts when denied', async () => {
    const requestPermission = vi.fn().mockResolvedValue('denied');
    Object.defineProperty(navigator, 'serviceWorker', {
      configurable: true,
      value: { ready: Promise.resolve({ pushManager: { getSubscription: vi.fn(), subscribe: vi.fn() } }) },
    });
    Object.defineProperty(window, 'PushManager', { configurable: true, value: function PushManager() {} });
    vi.stubGlobal('Notification', {
      permission: 'default',
      requestPermission,
    });
    const vapidPublicKey = btoa(String.fromCharCode(...Array.from({ length: 65 }, (_, i) => i % 256)))
      .replace(/\+/g, '-')
      .replace(/\//g, '_')
      .replace(/=+$/g, '');
    fetchMock.mockResolvedValue({
      ok: true,
      json: async () => ({
        mercure: { enabled: false, hubUrl: null, token: null, topics: [] },
        push: { preferenceEnabled: true, vapidPublicKey, configured: true },
      }),
    });
    await start();
    await vi.waitFor(() => expect(requestPermission).toHaveBeenCalled());
    expect(fetchMock).not.toHaveBeenCalledWith('/subscribe', expect.anything());
  });

  it('closes existing EventSource on reconnect and disconnect', async () => {
    fetchMock.mockResolvedValue({
      ok: true,
      json: async () => ({
        mercure: {
          enabled: true,
          hubUrl: 'https://hub.example/m',
          token: 'tok',
          topics: ['t'],
        },
        push: { preferenceEnabled: false, vapidPublicKey: null, configured: false },
      }),
    });
    const controller = await start();
    expect(FakeEventSource.instances).toHaveLength(1);
    const first = FakeEventSource.instances[0];

    fetchMock.mockResolvedValue({
      ok: true,
      json: async () => ({
        mercure: { enabled: false, hubUrl: null, token: null, topics: [] },
        push: { preferenceEnabled: false, vapidPublicKey: null, configured: false },
      }),
    });
    await controller.enablePush();
    expect(first.closed).toBe(true);

    fetchMock.mockResolvedValue({
      ok: true,
      json: async () => ({
        mercure: {
          enabled: true,
          hubUrl: 'https://hub.example/m',
          token: 'tok',
          topics: ['t'],
        },
        push: { preferenceEnabled: false, vapidPublicKey: null, configured: false },
      }),
    });
    await controller.enablePush();
    expect(FakeEventSource.instances.length).toBeGreaterThan(1);
    controller.disconnect();
    expect(FakeEventSource.instances.at(-1)?.closed).toBe(true);
  });
});
