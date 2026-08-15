import { Application } from '@hotwired/stimulus';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const { driveMock, destroyMock, driverFactory, isActiveMock } = vi.hoisted(() => {
  const destroyMock = vi.fn();
  const isActiveMock = vi.fn(() => true);
  const driveMock = vi.fn();
  const driverFactory = vi.fn((config: { onDestroyStarted?: Function; onDestroyed?: Function; onHighlightStarted?: Function }) => {
    const active = {
      destroy: destroyMock,
      drive: driveMock,
      isActive: isActiveMock,
    };
    // expose config for assertions
    (active as unknown as { config: typeof config }).config = config;
    return active;
  });
  return { driveMock, destroyMock, driverFactory, isActiveMock };
});

vi.mock('driver.js', () => ({ driver: driverFactory }));
vi.mock('driver.js/dist/driver.css', () => ({}));

import ProductTourController from './product_tour_controller';

describe('product-tour controller', () => {
  let application: Application;
  let fetchMock: ReturnType<typeof vi.fn>;

  beforeEach(() => {
    driverFactory.mockClear();
    driveMock.mockClear();
    destroyMock.mockClear();
    fetchMock = vi.fn().mockResolvedValue({ ok: true });
    vi.stubGlobal('fetch', fetchMock);
    vi.stubGlobal('requestAnimationFrame', (cb: FrameRequestCallback) => {
      cb(0);
      return 1;
    });
    document.body.innerHTML = `
      <div data-tour="user-menu"><details><summary>User</summary></details></div>
      <a data-tour="admin-link" href="/admin">Admin</a>
      <div
        data-controller="product-tour"
        data-product-tour-auto-start-value="true"
        data-product-tour-force-value="true"
        data-product-tour-page-value="dashboard"
        data-product-tour-mark-url-value="/mark"
        data-product-tour-mark-token-value="tok"
        data-product-tour-steps-value='[{"element":"[data-tour=\\"admin-link\\"]","popover":{"title":"Admin","description":"Go","side":"top","align":"center"}},{"popover":{"title":"Welcome","description":"Hi"}}]'
        data-product-tour-labels-value='{"next":"N","previous":"P","done":"D"}'
      ></div>
    `;
    // Simulate tour=1 query for clearForceQuery
    window.history.replaceState({}, '', '/dashboard?tour=1');
    application = Application.start();
    application.register('product-tour', ProductTourController);
  });

  afterEach(() => {
    application.stop();
    document.body.innerHTML = '';
    vi.unstubAllGlobals();
    window.history.replaceState({}, '', '/');
  });

  it('starts driver with resolved steps and persists seen on destroy', async () => {
    await Promise.resolve();
    expect(driverFactory).toHaveBeenCalled();
    expect(driveMock).toHaveBeenCalled();

    const config = driverFactory.mock.calls[0][0] as {
      steps: unknown[];
      nextBtnText: string;
      onHighlightStarted: (el?: Element) => void;
      onDestroyStarted: (...args: unknown[]) => void;
      onDestroyed: () => void;
    };
    expect(config.steps).toHaveLength(2);
    expect(config.nextBtnText).toBe('N');

    const details = document.querySelector('details') as HTMLDetailsElement;
    config.onHighlightStarted(document.querySelector('[data-tour="admin-link"]') as Element);
    expect(details.open).toBe(true);

    const active = { isActive: () => true, destroy: destroyMock };
    config.onDestroyStarted(undefined, undefined, { driver: active });
    expect(fetchMock).toHaveBeenCalledWith(
      '/mark',
      expect.objectContaining({ method: 'POST' }),
    );
    expect(window.location.search).not.toContain('tour=1');
    expect(details.open).toBe(false);

    details.open = true;
    config.onDestroyed();
    expect(details.open).toBe(false);
  });

  it('marks seen when no steps resolve', async () => {
    application.stop();
    fetchMock.mockClear();
    driveMock.mockClear();
    driverFactory.mockClear();
    document.body.innerHTML = `
      <div
        data-controller="product-tour"
        data-product-tour-auto-start-value="true"
        data-product-tour-force-value="false"
        data-product-tour-page-value="x"
        data-product-tour-mark-url-value="/mark"
        data-product-tour-mark-token-value="tok"
        data-product-tour-steps-value='[{"element":"#missing","popover":{"title":"T","description":"D"}}]'
        data-product-tour-labels-value="{}"
      ></div>
    `;
    application = Application.start();
    application.register('product-tour', ProductTourController);
    await Promise.resolve();
    expect(driveMock).not.toHaveBeenCalled();
    expect(driverFactory).not.toHaveBeenCalled();
    expect(fetchMock).toHaveBeenCalled();
  });

  it('does nothing without autoStart or force', async () => {
    application.stop();
    driverFactory.mockClear();
    document.body.innerHTML = `
      <div data-controller="product-tour"
        data-product-tour-auto-start-value="false"
        data-product-tour-force-value="false"
        data-product-tour-steps-value="[]"
        data-product-tour-labels-value="{}"
        data-product-tour-mark-url-value=""
        data-product-tour-mark-token-value=""
        data-product-tour-page-value=""
      ></div>
    `;
    application = Application.start();
    application.register('product-tour', ProductTourController);
    await Promise.resolve();
    expect(driverFactory).not.toHaveBeenCalled();
  });

  it('covers disconnect, side/align defaults, highlight skip, and persist retry', async () => {
    await Promise.resolve();
    const config = driverFactory.mock.calls[0][0] as {
      steps: Array<{ popover: { side?: string; align?: string } }>;
      onHighlightStarted: (el?: Element) => void;
      onDestroyed: () => void;
    };
    expect(config.steps[0].popover.side).toBe('top');
    expect(config.steps[0].popover.align).toBe('center');
    expect(config.steps[1].popover.side).toBe('bottom');
    expect(config.steps[1].popover.align).toBe('start');

    config.onHighlightStarted(document.createElement('div'));
    expect((document.querySelector('details') as HTMLDetailsElement).open).toBe(false);

    fetchMock.mockRejectedValueOnce(new Error('network'));
    const root = document.querySelector('[data-controller="product-tour"]') as HTMLElement;
    const controller = application.getControllerForElementAndIdentifier(
      root,
      'product-tour',
    ) as ProductTourController;
    destroyMock.mockClear();
    controller.disconnect();
    expect(destroyMock).toHaveBeenCalled();

    // Restart a tour instance to exercise persistSeen retry after a failed mark.
    application.stop();
    fetchMock.mockRejectedValueOnce(new Error('network'));
    document.body.innerHTML = `
      <div data-tour="user-menu"><details><summary>User</summary></details></div>
      <a data-tour="admin-link" href="/admin">Admin</a>
      <div
        data-controller="product-tour"
        data-product-tour-auto-start-value="true"
        data-product-tour-force-value="true"
        data-product-tour-page-value="dashboard"
        data-product-tour-mark-url-value="/mark"
        data-product-tour-mark-token-value="tok"
        data-product-tour-steps-value='[{"popover":{"title":"Welcome","description":"Hi"}}]'
        data-product-tour-labels-value="{}"
      ></div>
    `;
    window.history.replaceState({}, '', '/dashboard?tour=1');
    application = Application.start();
    application.register('product-tour', ProductTourController);
    await Promise.resolve();
    const cfg2 = driverFactory.mock.calls.at(-1)?.[0] as { onDestroyed: () => void };
    cfg2.onDestroyed();
    await Promise.resolve();
    await Promise.resolve();
  });
});
