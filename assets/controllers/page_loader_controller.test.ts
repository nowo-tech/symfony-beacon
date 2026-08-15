import { Application } from '@hotwired/stimulus';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import PageLoaderController from './page_loader_controller';

describe('page-loader controller', () => {
  let application: Application;

  beforeEach(() => {
    vi.useFakeTimers();
    document.documentElement.classList.remove('is-page-loading');
    document.body.innerHTML = `
      <div
        data-controller="page-loader"
        data-page-loader-min-visible-value="100"
        data-page-loader-link-delay-value="10"
        data-page-loader-leave-ms-value="50"
      >
        <div data-page-loader-target="overlay" class="is-active" hidden></div>
      </div>
      <a href="/next">Next</a>
      <a href="/other" data-no-page-loader="true">Skip</a>
      <a href="https://example.com/out">External</a>
      <a href="#hash">Hash</a>
    `;
    Object.defineProperty(document, 'readyState', {
      configurable: true,
      get: () => 'complete',
    });
    application = Application.start();
    application.register('page-loader', PageLoaderController);
  });

  afterEach(() => {
    application.stop();
    document.body.innerHTML = '';
    document.documentElement.classList.remove('is-page-loading');
    vi.useRealTimers();
    vi.restoreAllMocks();
  });

  it('shows overlay on connect and schedules hide when document is complete', async () => {
    await Promise.resolve();
    const overlay = document.querySelector('[data-page-loader-target="overlay"]') as HTMLElement;
    expect(overlay.hidden).toBe(false);
    expect(document.documentElement.classList.contains('is-page-loading')).toBe(true);

    vi.advanceTimersByTime(200);
    overlay.dispatchEvent(new Event('transitionend'));
    expect(overlay.hidden).toBe(true);
  });

  it('shows overlay for same-origin navigation clicks', async () => {
    await Promise.resolve();
    vi.advanceTimersByTime(200);
    const overlay = document.querySelector('[data-page-loader-target="overlay"]') as HTMLElement;
    overlay.hidden = true;
    overlay.classList.remove('is-active');

    const link = document.querySelector('a[href="/next"]') as HTMLAnchorElement;
    link.dispatchEvent(
      new MouseEvent('click', { bubbles: true, cancelable: true, button: 0 }),
    );
    vi.advanceTimersByTime(20);
    expect(overlay.hidden).toBe(false);
  });

  it('ignores skip / external / hash links', async () => {
    await Promise.resolve();
    vi.advanceTimersByTime(200);
    const overlay = document.querySelector('[data-page-loader-target="overlay"]') as HTMLElement;
    overlay.hidden = true;

    for (const href of ['/other', 'https://example.com/out', '#hash']) {
      const link = document.querySelector(`a[href="${href}"]`) as HTMLAnchorElement;
      link.dispatchEvent(
        new MouseEvent('click', { bubbles: true, cancelable: true, button: 0 }),
      );
    }
    vi.advanceTimersByTime(50);
    expect(overlay.hidden).toBe(true);
  });

  it('hides immediately on bfcache pageshow', async () => {
    await Promise.resolve();
    const overlay = document.querySelector('[data-page-loader-target="overlay"]') as HTMLElement;
    expect(overlay.hidden).toBe(false);
    window.dispatchEvent(new PageTransitionEvent('pageshow', { persisted: true }));
    expect(overlay.hidden).toBe(true);
  });

  it('covers click guards, load listener, safety hide, and uikit overlay fallback', async () => {
    await Promise.resolve();
    vi.advanceTimersByTime(200);

    const overlay = document.querySelector('[data-page-loader-target="overlay"]') as HTMLElement;
    overlay.hidden = true;

    document.dispatchEvent(
      new MouseEvent('click', { bubbles: true, cancelable: true, button: 1 }),
    );
    const prevented = new MouseEvent('click', { bubbles: true, cancelable: true, button: 0 });
    prevented.preventDefault();
    document.dispatchEvent(prevented);
    document.dispatchEvent(
      new MouseEvent('click', { bubbles: true, cancelable: true, button: 0, metaKey: true }),
    );

    application.stop();
    Object.defineProperty(document, 'readyState', {
      configurable: true,
      get: () => 'loading',
    });
    document.body.innerHTML = `
      <div
        data-controller="page-loader"
        data-page-loader-min-visible-value="50"
        data-page-loader-link-delay-value="5"
        data-nowo-ui-page-loader
        class="is-active"
        hidden
      ></div>
      <a href="/same#section">Same path hash</a>
      <a href="/go" target="_blank">Blank</a>
      <a href="/file" download>Download</a>
      <a href="javascript:void(0)">Js</a>
    `;
    application = Application.start();
    application.register('page-loader', PageLoaderController);
    await Promise.resolve();

    window.dispatchEvent(new Event('load'));
    vi.advanceTimersByTime(100);

    for (const sel of ['a[href="/same#section"]', 'a[target="_blank"]', 'a[download]', 'a[href^="javascript"]']) {
      document.querySelector(sel)?.dispatchEvent(
        new MouseEvent('click', { bubbles: true, cancelable: true, button: 0 }),
      );
    }
    vi.advanceTimersByTime(20);

    const go = document.createElement('a');
    go.href = '/navigate-away';
    document.body.appendChild(go);
    go.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true, button: 0 }));
    vi.advanceTimersByTime(10);
    expect(document.documentElement.classList.contains('is-page-loading')).toBe(true);
    vi.advanceTimersByTime(8000);
  });

  it('resolves external uikit overlay and clears timers on disconnect during navigation', async () => {
    application.stop();
    document.body.innerHTML = `
      <div data-controller="page-loader" data-page-loader-link-delay-value="0" data-page-loader-min-visible-value="10"></div>
      <div data-nowo-ui-page-loader class="is-active" hidden></div>
      <a href="/elsewhere">Go</a>
    `;
    Object.defineProperty(document, 'readyState', {
      configurable: true,
      get: () => 'complete',
    });
    application = Application.start();
    application.register('page-loader', PageLoaderController);
    await Promise.resolve();

    const uikit = document.querySelector('[data-nowo-ui-page-loader]') as HTMLElement;
    expect(uikit.hidden).toBe(false);

    document.querySelector('a')?.dispatchEvent(
      new MouseEvent('click', { bubbles: true, cancelable: true, button: 0 }),
    );
    vi.advanceTimersByTime(1);

    const root = document.querySelector('[data-controller="page-loader"]') as HTMLElement;
    const controller = application.getControllerForElementAndIdentifier(
      root,
      'page-loader',
    ) as PageLoaderController;
    expect(() => controller.disconnect()).not.toThrow();
  });
});
