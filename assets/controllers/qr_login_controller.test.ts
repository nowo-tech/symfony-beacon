import { Application } from '@hotwired/stimulus';
import { afterEach, describe, expect, it, vi } from 'vitest';
import QrLoginController from './qr_login_controller';

describe('qr-login controller', () => {
  let application: Application;
  let fetchMock: ReturnType<typeof vi.fn>;

  const mount = async (fetchImpl: ReturnType<typeof vi.fn>): Promise<void> => {
    fetchMock = fetchImpl;
    vi.stubGlobal('fetch', fetchMock);
    const expiresAt = Math.floor(Date.now() / 1000) + 120;
    document.body.innerHTML = `
      <div
        data-controller="qr-login"
        data-qr-login-status-url-value="/status"
        data-qr-login-complete-url-value="/complete"
        data-qr-login-start-url-value="/start"
        data-qr-login-poll-interval-value="20"
        data-qr-login-expires-at-value="${expiresAt}"
        data-qr-login-waiting-value="Waiting"
        data-qr-login-approved-value="Approved"
        data-qr-login-denied-value="Denied"
        data-qr-login-expired-value="Expired"
        data-qr-login-retry-value="Retry"
      >
        <p data-qr-login-target="status">Waiting</p>
        <span data-qr-login-target="countdown">2:00</span>
        <div data-qr-login-target="expiresRow"></div>
        <div data-qr-login-target="actions" hidden>
          <a data-qr-login-target="retry" href="#">Retry</a>
        </div>
      </div>
    `;
    application = Application.start();
    application.register('qr-login', QrLoginController);
    await Promise.resolve();
    await Promise.resolve();
    await Promise.resolve();
  };

  afterEach(() => {
    application?.stop();
    document.body.innerHTML = '';
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
  });

  it('polls pending then completes on approved', async () => {
    const fetchImpl = vi
      .fn()
      .mockResolvedValueOnce({
        ok: true,
        json: async () => ({ status: 'pending', expires_in: 60 }),
      })
      .mockResolvedValueOnce({
        ok: true,
        json: async () => ({ status: 'approved' }),
      });

    await mount(fetchImpl);
    expect(document.querySelector('[data-qr-login-target="status"]')?.textContent).toBe('Waiting');

    await new Promise((r) => setTimeout(r, 550));
    await Promise.resolve();
    await Promise.resolve();

    expect(document.querySelector('[data-qr-login-target="status"]')?.textContent).toBe('Approved');
    expect(
      (document.querySelector('[data-qr-login-target="expiresRow"]') as HTMLElement).hidden,
    ).toBe(true);
  });

  it('shows denied terminal state', async () => {
    await mount(
      vi.fn().mockResolvedValueOnce({
        ok: true,
        json: async () => ({ status: 'denied' }),
      }),
    );

    const status = document.querySelector('[data-qr-login-target="status"]') as HTMLElement;
    const actions = document.querySelector('[data-qr-login-target="actions"]') as HTMLElement;
    const retry = document.querySelector('[data-qr-login-target="retry"]') as HTMLAnchorElement;
    expect(status.textContent).toBe('Denied');
    expect(actions.hidden).toBe(false);
    expect(retry.getAttribute('href')).toBe('/start');
  });

  it('retries after non-ok responses and network errors', async () => {
    await mount(
      vi
        .fn()
        .mockResolvedValueOnce({ ok: false })
        .mockRejectedValueOnce(new Error('network'))
        .mockResolvedValueOnce({
          ok: true,
          json: async () => ({ status: 'expired' }),
        }),
    );

    await new Promise((r) => setTimeout(r, 550));
    await Promise.resolve();
    await new Promise((r) => setTimeout(r, 550));
    await Promise.resolve();

    expect(document.querySelector('[data-qr-login-target="status"]')?.textContent).toBe('Expired');
    expect(document.querySelector('[data-qr-login-target="countdown"]')?.textContent).toBe('0:00');
  });

  it('treats consumed like approved and stops polling on disconnect', async () => {
    const assign = vi.fn();
    vi.stubGlobal('location', { ...window.location, assign });
    await mount(
      vi.fn().mockResolvedValueOnce({
        ok: true,
        json: async () => ({ status: 'consumed', expires_in: 30 }),
      }),
    );
    expect(document.querySelector('[data-qr-login-target="status"]')?.textContent).toBe('Approved');
    expect(assign).toHaveBeenCalledWith('/complete');

    application.stop();
    const fetchImpl = vi.fn().mockResolvedValue({
      ok: true,
      json: async () => ({ status: 'pending' }),
    });
    await mount(fetchImpl);
    application.stop();
    await new Promise((r) => setTimeout(r, 100));
    const callsAfterStop = fetchImpl.mock.calls.length;
    await new Promise((r) => setTimeout(r, 80));
    expect(fetchImpl.mock.calls.length).toBe(callsAfterStop);
  });
});
