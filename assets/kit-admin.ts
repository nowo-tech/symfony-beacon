/**
 * Kit admin shells (menus / breadcrumbs / cookie-consent): layout helpers only.
 * CSS framework is `custom` + vendor `nowo-ui.css` — do not load Bootstrap here
 * (Tailwind app chrome must not be reboot-overridden).
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
    cssFramework: boot.cssFramework ?? 'custom',
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
  window.__breadcrumbKitDashboard.cssFramework = boot.cssFramework ?? 'custom';
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
  document.querySelectorAll('.kit-modal.modal, .nowo-ui-modal.modal').forEach((el) => {
    if (el.parentElement !== document.body) {
      document.body.appendChild(el);
    }
  });
}

bootDashboardMenu();
bootBreadcrumbKit();
mergePageConfigs();
splitKitFilters();
portalKitModals();
