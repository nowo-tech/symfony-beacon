import './styles/tailwind.css';
import './styles/app.scss';
import './stimulus_bootstrap';

document.documentElement.dataset.assets = 'ts+scss+tailwind+stimulus';

const THEME_KEY = 'beacon-theme';
const SIDEBAR_KEY = 'beacon-sidebar';

type Theme = 'light' | 'dark';

function isTheme(value: string | null): value is Theme {
  return value === 'light' || value === 'dark';
}

function resolveTheme(): Theme {
  try {
    const stored = localStorage.getItem(THEME_KEY);
    if (isTheme(stored)) {
      return stored;
    }
  } catch {
    // Ignore storage errors (private mode, etc.).
  }

  return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

function syncThemeControls(theme: Theme): void {
  document.querySelectorAll<HTMLElement>('[data-theme-toggle]').forEach((button) => {
    const nextLabel = theme === 'dark' ? button.dataset.labelLight : button.dataset.labelDark;
    const nextAria = theme === 'dark' ? button.dataset.ariaToLight : button.dataset.ariaToDark;
    const label = button.querySelector<HTMLElement>('[data-theme-label]');

    button.dataset.themeCurrent = theme;
    button.setAttribute('aria-pressed', theme === 'dark' ? 'true' : 'false');
    if (nextAria) {
      button.setAttribute('aria-label', nextAria);
    }
    if (label && nextLabel) {
      label.textContent = nextLabel;
    }
  });
}

function applyTheme(theme: Theme, persist: boolean): void {
  document.documentElement.dataset.theme = theme;
  if (persist) {
    try {
      localStorage.setItem(THEME_KEY, theme);
    } catch {
      // Ignore storage errors.
    }
  }
  syncThemeControls(theme);
}

function initTheme(): void
{
  applyTheme(resolveTheme(), false);

  document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
    if (button instanceof HTMLElement && button.dataset.themeBound === '1') {
      return;
    }
    if (button instanceof HTMLElement) {
      button.dataset.themeBound = '1';
    }
    button.addEventListener('click', () => {
      const current = document.documentElement.dataset.theme === 'dark' ? 'dark' : 'light';
      applyTheme(current === 'dark' ? 'light' : 'dark', true);
    });
  });

  if (document.documentElement.dataset.themeMediaBound === '1') {
    return;
  }
  document.documentElement.dataset.themeMediaBound = '1';

  window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (event) => {
    try {
      if (localStorage.getItem(THEME_KEY)) {
        return;
      }
    } catch {
      return;
    }
    applyTheme(event.matches ? 'dark' : 'light', false);
  });
}

function isMobileSidebar(): boolean {
  return window.matchMedia('(max-width: 900px)').matches;
}

function readSidebarCollapsed(): boolean {
  try {
    const stored = localStorage.getItem(SIDEBAR_KEY);
    if (stored === 'collapsed' || stored === 'expanded') {
      return stored === 'collapsed';
    }
  } catch {
    // Ignore.
  }

  return isMobileSidebar();
}

function writeSidebarCollapsed(collapsed: boolean): void {
  try {
    localStorage.setItem(SIDEBAR_KEY, collapsed ? 'collapsed' : 'expanded');
  } catch {
    // Ignore.
  }
}

function applySidebar(collapsed: boolean): void {
  const shell = document.querySelector<HTMLElement>('[data-app-shell]');
  const backdrop = document.querySelector<HTMLElement>('[data-sidebar-backdrop]');
  if (!shell) {
    return;
  }

  const mobile = isMobileSidebar();
  shell.classList.toggle('is-sidebar-collapsed', !mobile && collapsed);
  shell.classList.toggle('is-sidebar-open', mobile && !collapsed);

  if (backdrop) {
    backdrop.hidden = !(mobile && !collapsed);
  }

  document.querySelectorAll<HTMLElement>('[data-sidebar-toggle]').forEach((button) => {
    button.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
  });
}

function initSidebar(): void {
  const shell = document.querySelector('[data-app-shell]');
  if (!shell) {
    return;
  }

  let collapsed = readSidebarCollapsed();
  applySidebar(collapsed);

  document.querySelectorAll('[data-sidebar-toggle]').forEach((button) => {
    if (button instanceof HTMLElement && button.dataset.sidebarBound === '1') {
      return;
    }
    if (button instanceof HTMLElement) {
      button.dataset.sidebarBound = '1';
    }
    button.addEventListener('click', () => {
      collapsed = !collapsed;
      writeSidebarCollapsed(collapsed);
      applySidebar(collapsed);
    });
  });

  document.querySelectorAll('[data-sidebar-backdrop]').forEach((backdrop) => {
    if (backdrop instanceof HTMLElement && backdrop.dataset.sidebarBound === '1') {
      return;
    }
    if (backdrop instanceof HTMLElement) {
      backdrop.dataset.sidebarBound = '1';
    }
    backdrop.addEventListener('click', () => {
      collapsed = true;
      writeSidebarCollapsed(collapsed);
      applySidebar(collapsed);
    });
  });

  if (document.documentElement.dataset.sidebarMediaBound === '1') {
    return;
  }
  document.documentElement.dataset.sidebarMediaBound = '1';

  window.matchMedia('(max-width: 900px)').addEventListener('change', () => {
    collapsed = readSidebarCollapsed();
    applySidebar(collapsed);
  });
}

// Close locale / user dropdowns when clicking outside (details/summary).
document.addEventListener('click', (event) => {
  const target = event.target;
  if (!(target instanceof Node)) {
    return;
  }

  document
    .querySelectorAll('details.locale-switcher__details[open], details.user-menu__details[open]')
    .forEach((details) => {
      if (!details.contains(target)) {
        details.removeAttribute('open');
      }
    });
});

document.addEventListener('turbo:load', () => {
  initTheme();
  initSidebar();
  initColorHexLabels();
});

// Fallback when Turbo is unavailable (should be rare).
if (!window.Turbo) {
  initTheme();
  initSidebar();
  initColorHexLabels();
}

function initColorHexLabels(): void {
  document.querySelectorAll<HTMLInputElement>('input[type="color"]').forEach((input) => {
    const label = document.querySelector<HTMLElement>(`[data-color-hex-for="${input.id}"]`);
    if (!label) {
      return;
    }
    const sync = (): void => {
      label.textContent = input.value;
    };
    sync();
    input.addEventListener('input', sync);
  });
}

export {};
