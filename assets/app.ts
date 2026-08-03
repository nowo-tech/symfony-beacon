import './styles/fonts.css';
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

function syncCookieConsentTheme(theme: Theme): void {
  const modal = document.getElementById('cookieconsent');
  if (!(modal instanceof HTMLElement)) {
    return;
  }

  const dark = theme === 'dark';
  modal.classList.toggle('nowo-cookie-consent--dark-mode', dark);
  modal.dataset.nowoDarkMode = dark ? 'true' : 'false';
}

function applyTheme(theme: Theme, persist: boolean): void {
  document.documentElement.dataset.theme = theme;
  if (persist) {
    try {
      localStorage.setItem(THEME_KEY, theme);
    } catch {
      // Ignore storage errors.
    }
    syncThemeToAccount(theme);
  }
  syncThemeControls(theme);
  syncCookieConsentTheme(theme);
}

function syncThemeToAccount(theme: Theme): void {
  const shell = document.querySelector<HTMLElement>('[data-app-shell][data-theme-sync-url]');
  if (!(shell instanceof HTMLElement)) {
    return;
  }
  const url = shell.dataset.themeSyncUrl;
  const token = shell.dataset.themeSyncToken;
  if (!url || !token) {
    return;
  }

  void fetch(url, {
    method: 'POST',
    credentials: 'same-origin',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      'X-CSRF-TOKEN': token,
    },
    body: JSON.stringify({ theme }),
  }).catch(() => {
    // Preference remains in localStorage if the network call fails.
  });
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

/** True only when the account explicitly chose reduce (not OS system prefs). */
function shellMotionBlocked(): boolean {
  return document.documentElement.dataset.motion === 'reduce';
}

const SHELL_EASE = 'cubic-bezier(0.4, 0, 0.2, 1)';
const SIDEBAR_MS = 420;
const CONTENT_WIDTH_MS = 480;

function readSidebarCollapsed(): boolean {
  if (isMobileSidebar()) {
    // Drawer should start closed on phones; ignore desktop expanded preference.
    return true;
  }

  try {
    const stored = localStorage.getItem(SIDEBAR_KEY);
    if (stored === 'collapsed' || stored === 'expanded') {
      return stored === 'collapsed';
    }
  } catch {
    // Ignore.
  }

  const userSidebar = (window as Window & { __BEACON_USER_SIDEBAR__?: string }).__BEACON_USER_SIDEBAR__;
  if (userSidebar === 'collapsed' || userSidebar === 'expanded') {
    return userSidebar === 'collapsed';
  }

  return false;
}

function writeSidebarCollapsed(collapsed: boolean): void {
  // Do not persist drawer open/closed across mobile navigations.
  if (isMobileSidebar()) {
    return;
  }

  try {
    localStorage.setItem(SIDEBAR_KEY, collapsed ? 'collapsed' : 'expanded');
  } catch {
    // Ignore.
  }
}

/**
 * Animate a CSS property with the Web Animations API so motion still runs when
 * a global `transition-duration: 0.01ms !important` rule would kill CSS transitions.
 */
function animateStyle(
  el: HTMLElement,
  keyframes: Keyframe[],
  duration: number,
  onDone?: () => void,
): void {
  if (duration <= 0 || typeof el.animate !== 'function') {
    onDone?.();
    return;
  }

  const animation = el.animate(keyframes, {
    duration,
    easing: SHELL_EASE,
    fill: 'forwards',
  });

  let settled = false;
  const finish = (): void => {
    if (settled) {
      return;
    }
    settled = true;
    animation.cancel();
    onDone?.();
  };

  animation.addEventListener('finish', finish, { once: true });
  window.setTimeout(finish, duration + 80);
}

function applySidebar(collapsed: boolean, animate = true): void {
  const shell = document.querySelector<HTMLElement>('[data-app-shell]');
  const backdrop = document.querySelector<HTMLElement>('[data-sidebar-backdrop]');
  if (!shell) {
    return;
  }

  const mobile = isMobileSidebar();
  const nextCollapsed = !mobile && collapsed;
  const nextOpen = mobile && !collapsed;
  const sidebar = shell.querySelector<HTMLElement>('.app-sidebar');
  const main = shell.querySelector<HTMLElement>('.app-main');

  const currentlyOpen = mobile
    ? shell.classList.contains('is-sidebar-open')
    : !shell.classList.contains('is-sidebar-collapsed');
  const willOpen = mobile ? nextOpen : !nextCollapsed;

  const commitClasses = (): void => {
    shell.classList.toggle('is-sidebar-collapsed', nextCollapsed);
    shell.classList.toggle('is-sidebar-open', nextOpen);

    if (backdrop) {
      if (mobile && !collapsed) {
        backdrop.hidden = false;
      } else if (!mobile) {
        backdrop.hidden = true;
      } else {
        const hide = (): void => {
          if (!shell.classList.contains('is-sidebar-open')) {
            backdrop.hidden = true;
          }
        };
        backdrop.addEventListener('transitionend', hide, { once: true });
        window.setTimeout(hide, 500);
      }
    }

    document.querySelectorAll<HTMLElement>('[data-sidebar-toggle]').forEach((button) => {
      button.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
    });
  };

  const shouldAnimate =
    animate && !shellMotionBlocked() && sidebar instanceof HTMLElement && currentlyOpen !== willOpen;

  if (!shouldAnimate) {
    commitClasses();
    return;
  }

  const width = sidebar.getBoundingClientRect().width || sidebar.offsetWidth || 248;
  const gutter = mobile ? 12 : 8;
  const fromX = currentlyOpen ? 0 : -(width + gutter);
  const toX = willOpen ? 0 : -(width + gutter);
  const fromPad = !mobile && currentlyOpen ? width : 0;
  const toPad = !mobile && willOpen ? width : 0;

  // Freeze at the current visual position, flip classes, then WAAPI to the end.
  sidebar.style.transition = 'none';
  sidebar.style.transform = `translateX(${fromX}px)`;
  if (main && !mobile) {
    main.style.transition = 'none';
    main.style.paddingInlineStart = `${fromPad}px`;
  }

  commitClasses();
  void sidebar.getBoundingClientRect();

  let pending = mobile || !main ? 1 : 2;
  const clearInline = (): void => {
    pending -= 1;
    if (pending > 0) {
      return;
    }
    sidebar.style.removeProperty('transform');
    sidebar.style.removeProperty('transition');
    if (main) {
      main.style.removeProperty('padding-inline-start');
      main.style.removeProperty('transition');
    }
  };

  animateStyle(
    sidebar,
    [{ transform: `translateX(${fromX}px)` }, { transform: `translateX(${toX}px)` }],
    SIDEBAR_MS,
    clearInline,
  );

  if (main && !mobile) {
    animateStyle(
      main,
      [
        { paddingInlineStart: `${fromPad}px` },
        { paddingInlineStart: `${toPad}px` },
      ],
      SIDEBAR_MS,
      clearInline,
    );
  }

  if (backdrop && mobile) {
    backdrop.style.opacity = willOpen ? '0' : '1';
    void backdrop.offsetWidth;
    animateStyle(
      backdrop,
      [{ opacity: willOpen ? 0 : 1 }, { opacity: willOpen ? 1 : 0 }],
      SIDEBAR_MS,
      () => {
        backdrop.style.removeProperty('opacity');
      },
    );
  }
}

function initSidebar(): void {
  const shell = document.querySelector('[data-app-shell]');
  if (!shell) {
    return;
  }

  let collapsed = readSidebarCollapsed();
  applySidebar(collapsed, false);

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

  // Close the drawer after choosing a destination on mobile.
  shell.querySelectorAll('.app-sidebar a[href]').forEach((link) => {
    link.addEventListener('click', () => {
      if (!isMobileSidebar()) {
        return;
      }
      collapsed = true;
      applySidebar(collapsed);
    });
  });

  if (document.documentElement.dataset.sidebarMediaBound === '1') {
    return;
  }
  document.documentElement.dataset.sidebarMediaBound = '1';

  window.matchMedia('(max-width: 900px)').addEventListener('change', () => {
    collapsed = readSidebarCollapsed();
    applySidebar(collapsed, false);
  });
}

type ContentWidth = 'content' | 'full';

function isContentWidth(value: string | null | undefined): value is ContentWidth {
  return value === 'content' || value === 'full';
}

function resolveContentWidth(): ContentWidth {
  const shell = document.querySelector<HTMLElement>('[data-app-shell]');
  if (shell && isContentWidth(shell.dataset.contentWidth)) {
    return shell.dataset.contentWidth;
  }

  return shell?.classList.contains('is-full-width') ? 'full' : 'content';
}

function syncContentWidthControls(width: ContentWidth): void {
  document.querySelectorAll<HTMLElement>('[data-content-width-toggle]').forEach((button) => {
    const nextLabel = width === 'full' ? button.dataset.labelContent : button.dataset.labelFull;
    const nextAria = width === 'full' ? button.dataset.ariaToContent : button.dataset.ariaToFull;
    const label = button.querySelector<HTMLElement>('[data-content-width-label]');

    button.dataset.contentWidthCurrent = width;
    button.setAttribute('aria-pressed', width === 'full' ? 'true' : 'false');
    if (nextAria) {
      button.setAttribute('aria-label', nextAria);
    }
    if (label && nextLabel) {
      label.textContent = nextLabel;
    }
  });
}

function applyContentWidth(width: ContentWidth, persist: boolean, animate = true): void {
  const shell = document.querySelector<HTMLElement>('[data-app-shell]');
  if (!shell) {
    return;
  }

  const inner = shell.querySelector<HTMLElement>('.app-main__inner');
  const footerNav = shell.querySelector<HTMLElement>('.site-legal-footer__nav');

  const commit = (): void => {
    shell.dataset.contentWidth = width;
    shell.classList.toggle('is-full-width', width === 'full');
    shell.classList.toggle('is-content-width', width === 'content');
    syncContentWidthControls(width);

    if (persist) {
      syncContentWidthToAccount(width);
    }
  };

  const shouldAnimate = animate && !shellMotionBlocked() && inner instanceof HTMLElement;
  if (!shouldAnimate) {
    commit();
    return;
  }

  const fromWidth = inner.getBoundingClientRect().width;
  commit();
  const toWidth = inner.getBoundingClientRect().width;

  if (Math.abs(fromWidth - toWidth) < 0.5) {
    return;
  }

  // FLIP in px — rem↔% max-width often snaps even with @property.
  inner.style.transition = 'none';
  inner.style.maxWidth = `${fromWidth}px`;
  if (footerNav) {
    footerNav.style.transition = 'none';
    footerNav.style.maxWidth = `${fromWidth}px`;
  }
  void inner.getBoundingClientRect();

  let pending = footerNav ? 2 : 1;
  const clearInline = (): void => {
    pending -= 1;
    if (pending > 0) {
      return;
    }
    inner.style.removeProperty('max-width');
    inner.style.removeProperty('transition');
    if (footerNav) {
      footerNav.style.removeProperty('max-width');
      footerNav.style.removeProperty('transition');
    }
  };

  animateStyle(
    inner,
    [{ maxWidth: `${fromWidth}px` }, { maxWidth: `${toWidth}px` }],
    CONTENT_WIDTH_MS,
    clearInline,
  );

  if (footerNav) {
    animateStyle(
      footerNav,
      [{ maxWidth: `${fromWidth}px` }, { maxWidth: `${toWidth}px` }],
      CONTENT_WIDTH_MS,
      clearInline,
    );
  }
}

function syncContentWidthToAccount(width: ContentWidth): void {
  const shell = document.querySelector<HTMLElement>('[data-app-shell][data-content-width-sync-url]');
  if (!(shell instanceof HTMLElement)) {
    return;
  }
  const url = shell.dataset.contentWidthSyncUrl;
  const token = shell.dataset.contentWidthSyncToken;
  if (!url || !token) {
    return;
  }

  void fetch(url, {
    method: 'POST',
    credentials: 'same-origin',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      'X-CSRF-TOKEN': token,
    },
    body: JSON.stringify({ contentWidth: width }),
  }).catch(() => {
    // Preference remains on the shell classes if the network call fails.
  });
}

function initContentWidth(): void {
  const shell = document.querySelector('[data-app-shell]');
  if (!shell) {
    return;
  }

  applyContentWidth(resolveContentWidth(), false, false);

  document.querySelectorAll('[data-content-width-toggle]').forEach((button) => {
    if (button instanceof HTMLElement && button.dataset.contentWidthBound === '1') {
      return;
    }
    if (button instanceof HTMLElement) {
      button.dataset.contentWidthBound = '1';
    }
    button.addEventListener('click', () => {
      const current = resolveContentWidth();
      applyContentWidth(current === 'full' ? 'content' : 'full', true);
    });
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

initTheme();
initContentWidth();
initSidebar();
initColorHexLabels();
initBreadcrumbInlineEdit();

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

/** Breadcrumb Kit inline editor dialog (CSP-safe; vendor used an inline IIFE). */
function initBreadcrumbInlineEdit(): void {
  document.querySelectorAll<HTMLElement>('[data-breadcrumb-kit-inline-wrap="1"]').forEach((wrap) => {
    const openBtn = wrap.querySelector<HTMLElement>('[data-bk-inline-open]');
    const dlg = wrap.querySelector<HTMLDialogElement>('[data-bk-inline-dialog]');
    const closeBtn = wrap.querySelector<HTMLElement>('[data-bk-inline-close]');
    if (!openBtn || !dlg) {
      return;
    }
    openBtn.addEventListener('click', () => {
      if (typeof dlg.showModal === 'function') {
        dlg.showModal();
      }
    });
    closeBtn?.addEventListener('click', () => {
      dlg.close();
    });
  });
}

export {};
