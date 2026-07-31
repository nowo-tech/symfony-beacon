import { Controller } from '@hotwired/stimulus';

/**
 * Full-page Thinking Orb overlay (translucent veil).
 *
 * Shows on connect (initial paint), hides after `minVisible` ms once the
 * window has loaded. Also shows briefly when following same-origin links.
 */
export default class extends Controller {
  static targets = ['overlay'];

  static values = {
    minVisible: { type: Number, default: 650 },
    linkDelay: { type: Number, default: 120 },
  };

  declare readonly overlayTarget: HTMLElement;
  declare readonly hasOverlayTarget: boolean;
  declare readonly minVisibleValue: number;
  declare readonly linkDelayValue: number;

  private shownAt = 0;
  private hideTimer: number | null = null;
  private safetyTimer: number | null = null;
  private navigating = false;

  connect(): void {
    this.shownAt = performance.now();
    // Initial markup already has is-active (CSS keyframes handle the soft enter).
    this.show(false);

    if (document.readyState === 'complete') {
      this.scheduleHide();
    } else {
      window.addEventListener('load', this.onLoad, { once: true });
    }

    document.addEventListener('click', this.onClick, true);
    window.addEventListener('pageshow', this.onPageShow);
  }

  disconnect(): void {
    window.removeEventListener('load', this.onLoad);
    document.removeEventListener('click', this.onClick, true);
    window.removeEventListener('pageshow', this.onPageShow);
    this.clearTimers();
  }

  private onLoad = (): void => {
    this.scheduleHide();
  };

  private onPageShow = (event: PageTransitionEvent): void => {
    // Back-forward cache: ensure the overlay is not stuck visible.
    if (event.persisted) {
      this.navigating = false;
      this.hide(true);
    }
  };

  private onClick = (event: MouseEvent): void => {
    if (event.defaultPrevented || event.button !== 0) {
      return;
    }
    if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
      return;
    }
    const target = event.target;
    if (!(target instanceof Element)) {
      return;
    }
    const anchor = target.closest('a[href]');
    if (!(anchor instanceof HTMLAnchorElement)) {
      return;
    }
    if (
      anchor.dataset.noPageLoader === 'true' ||
      anchor.target === '_blank' ||
      anchor.hasAttribute('download')
    ) {
      return;
    }
    const href = anchor.getAttribute('href');
    if (!href || href.startsWith('#') || href.startsWith('javascript:')) {
      return;
    }
    try {
      const url = new URL(anchor.href, window.location.href);
      if (url.origin !== window.location.origin) {
        return;
      }
      if (
        url.pathname === window.location.pathname &&
        url.search === window.location.search &&
        url.hash !== ''
      ) {
        return;
      }
    } catch {
      return;
    }

    this.navigating = true;
    window.setTimeout(() => {
      if (this.navigating) {
        this.show();
        this.armSafetyHide();
      }
    }, Math.max(0, this.linkDelayValue));
  };

  private show(restartEnter = true): void {
    const overlay = this.resolveOverlay();
    if (!overlay) {
      return;
    }
    this.shownAt = performance.now();
    overlay.hidden = false;
    overlay.classList.remove('is-leaving');
    overlay.setAttribute('aria-busy', 'true');
    document.documentElement.classList.add('is-page-loading');

    if (!restartEnter && overlay.classList.contains('is-active')) {
      return;
    }

    // Retrigger enter keyframes (also used on in-app navigations).
    overlay.classList.remove('is-active');
    void overlay.offsetWidth;
    overlay.classList.add('is-active');
  }

  private scheduleHide(): void {
    if (this.navigating) {
      return;
    }
    const elapsed = performance.now() - this.shownAt;
    const wait = Math.max(0, this.minVisibleValue - elapsed);
    if (this.hideTimer !== null) {
      window.clearTimeout(this.hideTimer);
    }
    this.hideTimer = window.setTimeout(() => this.hide(), wait);
  }

  /** If navigation was cancelled (e.g. preventDefault), drop the overlay. */
  private armSafetyHide(): void {
    if (this.safetyTimer !== null) {
      window.clearTimeout(this.safetyTimer);
    }
    this.safetyTimer = window.setTimeout(() => {
      this.navigating = false;
      this.hide();
    }, 8000);
  }

  private hide(immediate = false): void {
    const overlay = this.resolveOverlay();
    if (!overlay || overlay.hidden) {
      document.documentElement.classList.remove('is-page-loading');
      return;
    }
    overlay.setAttribute('aria-busy', 'false');
    document.documentElement.classList.remove('is-page-loading');

    if (immediate) {
      overlay.hidden = true;
      overlay.classList.remove('is-leaving', 'is-active');
      return;
    }

    overlay.classList.add('is-leaving');
    const done = (): void => {
      overlay.hidden = true;
      overlay.classList.remove('is-leaving', 'is-active');
    };
    overlay.addEventListener('transitionend', done, { once: true });
    window.setTimeout(done, 500);
  }

  private clearTimers(): void {
    if (this.hideTimer !== null) {
      window.clearTimeout(this.hideTimer);
      this.hideTimer = null;
    }
    if (this.safetyTimer !== null) {
      window.clearTimeout(this.safetyTimer);
      this.safetyTimer = null;
    }
  }

  private resolveOverlay(): HTMLElement | null {
    if (this.hasOverlayTarget) {
      return this.overlayTarget;
    }
    return this.element instanceof HTMLElement ? this.element : null;
  }
}
