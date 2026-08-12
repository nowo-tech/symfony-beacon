/**
 * Kit admin shells (menus / breadcrumbs / cookie-consent): layout helpers only.
 * CSS framework is UiKit `tailwind` + `nowo-ui.css` (asset package nowo_ui_kit).
 * Do not load Bootstrap here (Tailwind app chrome must not be reboot-overridden).
 * Config from <script type="application/json" id="…"> islands (CSP-safe).
 */

function readJson<T extends Record<string, unknown>>(id: string): T | null {
  const el = document.getElementById(id);
  if (!(el instanceof HTMLScriptElement)) {
    return null;
  }
  try {
    const parsed: unknown = JSON.parse(el.textContent ?? '{}');
    return parsed !== null && typeof parsed === 'object' ? (parsed as T) : null;
  } catch {
    return null;
  }
}

declare global {
  interface Window {
    dashboardMenuIconSelectorScriptUrl?: string;
    __nowoDashboardMenuConfig?: Record<string, unknown>;
    dashboardMenuI18n?: Record<string, string>;
    breadcrumbKitI18n?: Record<string, string>;
    __breadcrumbKitDashboard?: Record<string, unknown>;
  }
}

function bootDashboardMenu(): void {
  const boot = readJson<{
    iconSelectorScriptUrl?: string | null;
    cssFramework?: string;
    i18n?: Record<string, string>;
  }>('beacon-dashboard-menu-boot');
  if (!boot) {
    return;
  }
  if (boot.iconSelectorScriptUrl) {
    window.dashboardMenuIconSelectorScriptUrl = boot.iconSelectorScriptUrl;
  }
  window.__nowoDashboardMenuConfig = Object.assign(window.__nowoDashboardMenuConfig || {}, {
    cssFramework: boot.cssFramework ?? 'tailwind',
  });
  if (boot.i18n) {
    window.dashboardMenuI18n = boot.i18n;
  }
}

function bootBreadcrumbKit(): void {
  const boot = readJson<{
    cssFramework?: string;
    importPartialUrl?: string;
    dashboardBase?: string;
    i18n?: Record<string, string>;
  }>('beacon-breadcrumb-kit-boot');
  if (!boot) {
    return;
  }
  if (boot.i18n) {
    window.breadcrumbKitI18n = boot.i18n;
  }
  window.__breadcrumbKitDashboard = window.__breadcrumbKitDashboard || {};
  window.__breadcrumbKitDashboard.cssFramework = boot.cssFramework ?? 'tailwind';
  if (boot.importPartialUrl) {
    window.__breadcrumbKitDashboard.importPartialUrl = boot.importPartialUrl;
  }
  if (boot.dashboardBase) {
    window.__breadcrumbKitDashboard.dashboardBase = boot.dashboardBase;
  }
}

function mergePageConfigs(): void {
  document.querySelectorAll<HTMLScriptElement>('script.beacon-kit-page-config').forEach((el) => {
    let parsed: unknown;
    try {
      parsed = JSON.parse(el.textContent ?? '{}');
    } catch {
      return;
    }
    if (parsed === null || typeof parsed !== 'object') {
      return;
    }
    const data = parsed as Record<string, unknown>;
    if (el.dataset.kit === 'dashboard-menu') {
      window.__nowoDashboardMenuConfig = Object.assign(window.__nowoDashboardMenuConfig || {}, data);
      return;
    }
    if (el.dataset.kit === 'breadcrumb-kit') {
      window.__breadcrumbKitDashboard = Object.assign(window.__breadcrumbKitDashboard || {}, data);
    }
  });
}

function splitKitFilters(): void {
  document.querySelectorAll<HTMLElement>('.kit-admin[data-kit-split-filters]').forEach((root) => {
    if (root.dataset.kitSplitDone === '1') {
      return;
    }
    root.dataset.kitSplitDone = '1';

    const search = root.querySelector(':scope > .nowo-ui-search');
    if (!search) {
      root.classList.add('panel');
      return;
    }

    const panel = document.createElement('div');
    panel.className = 'panel';
    let node = search.nextSibling;
    while (node) {
      const next = node.nextSibling;
      panel.appendChild(node);
      node = next;
    }
    root.appendChild(panel);
  });
}

function portalKitModals(): void {
  document.querySelectorAll('dialog.nowo-ui-modal, .kit-modal.modal, .nowo-ui-modal.modal').forEach((el) => {
    if (el.parentElement !== document.body) {
      document.body.appendChild(el);
    }
  });
}

/**
 * Host kit modals use <dialog>. UiKit toggles .nowo-ui-modal-open via classList only;
 * bridge that to native showModal()/close() for top-layer + ::backdrop.
 */
function bridgeNativeDialogModals(): void {
  const sync = (dialog: HTMLDialogElement): void => {
    const wantOpen = dialog.classList.contains('nowo-ui-modal-open');
    if (wantOpen && !dialog.open) {
      try {
        dialog.showModal();
      } catch {
        // Not in document yet, or already transitioning.
      }
      return;
    }
    if (!wantOpen && dialog.open) {
      dialog.close();
    }
  };

  const observe = (dialog: HTMLDialogElement): void => {
    if (dialog.dataset.dialogBridged === '1') {
      return;
    }
    dialog.dataset.dialogBridged = '1';

    new MutationObserver(() => {
      sync(dialog);
    }).observe(dialog, { attributes: true, attributeFilter: ['class'] });

    dialog.addEventListener('close', () => {
      dialog.classList.remove('nowo-ui-modal-open');
      if (!document.querySelector('.nowo-ui-modal.nowo-ui-modal-open')) {
        document.body.classList.remove('nowo-modal-open');
      }
    });

    dialog.addEventListener('click', (event) => {
      if (event.target === dialog) {
        dialog.classList.remove('nowo-ui-modal-open');
        if (dialog.open) {
          dialog.close();
        }
      }
    });

    sync(dialog);
  };

  document.querySelectorAll('dialog.nowo-ui-modal').forEach((el) => {
    if (el instanceof HTMLDialogElement) {
      observe(el);
    }
  });
}

/**
 * Open/close kit <dialog> modals for Tailwind (data-nowo-modal-*).
 * Mirrors dashboard.js Pn()/On() so clicks work even if that script raced before config merge.
 */
function bindNowoModalTriggers(): void {
  if (document.documentElement.dataset.beaconNowoModalBound === '1') {
    return;
  }
  document.documentElement.dataset.beaconNowoModalBound = '1';

  const openModal = (id: string, relatedTarget: Element): void => {
    const dialog = document.getElementById(id);
    if (!(dialog instanceof HTMLElement)) {
      return;
    }
    dialog.classList.add('nowo-ui-modal-open');
    document.body.classList.add('nowo-modal-open');
    dialog.removeAttribute('hidden');
    dialog.setAttribute('aria-hidden', 'false');
    if (dialog instanceof HTMLDialogElement && !dialog.open) {
      try {
        dialog.showModal();
      } catch {
        // Ignore; class + CSS still surface the panel.
      }
    }
    const show = new Event('show.bs.modal', { bubbles: true });
    Object.defineProperty(show, 'relatedTarget', { value: relatedTarget, configurable: true });
    dialog.dispatchEvent(show);
    dialog.dispatchEvent(
      new CustomEvent('nowo:modal:show', { detail: { relatedTarget }, bubbles: true }),
    );
  };

  const closeModal = (id: string): void => {
    const dialog = document.getElementById(id);
    if (!(dialog instanceof HTMLElement)) {
      return;
    }
    dialog.classList.remove('nowo-ui-modal-open');
    dialog.setAttribute('aria-hidden', 'true');
    if (dialog instanceof HTMLDialogElement && dialog.open) {
      dialog.close();
    }
    if (!document.querySelector('.nowo-ui-modal.nowo-ui-modal-open')) {
      document.body.classList.remove('nowo-modal-open');
    }
  };

  document.addEventListener(
    'click',
    (event) => {
      const target = event.target;
      if (!(target instanceof Element)) {
        return;
      }

      const openBtn = target.closest('[data-nowo-modal-open]');
      if (openBtn) {
        const raw =
          (openBtn.getAttribute('data-nowo-modal-open') ||
            openBtn.getAttribute('data-nowo-modal-target') ||
            '').trim();
        const id = raw.replace(/^#/, '');
        if (id) {
          // Capture + stop so vendor dashboard.js Pn() does not double-open / double-fetch.
          event.preventDefault();
          event.stopImmediatePropagation();
          openModal(id, openBtn);
        }
        return;
      }

      const closeBtn = target.closest('[data-nowo-modal-close]');
      if (closeBtn) {
        const host =
          closeBtn.closest<HTMLElement>('.nowo-ui-modal[id], dialog.nowo-ui-modal[id]') ??
          null;
        const id =
          host?.id ||
          (closeBtn.getAttribute('data-nowo-modal-close') || '').replace(/^#/, '');
        if (id) {
          event.preventDefault();
          event.stopImmediatePropagation();
          closeModal(id);
        }
      }
    },
    true,
  );
}

function bootKitAdmin(): void {
  bootDashboardMenu();
  bootBreadcrumbKit();
  mergePageConfigs();
  splitKitFilters();
  portalKitModals();
  bridgeNativeDialogModals();
  bindNowoModalTriggers();
}

bootKitAdmin();
// Vite HMR / late body nodes: re-portal + re-bridge once the document is interactive.
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => {
    portalKitModals();
    bridgeNativeDialogModals();
  });
}
