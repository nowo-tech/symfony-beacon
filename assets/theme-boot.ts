/**
 * Apply theme / density / motion / font / contrast before paint to avoid flash.
 * Prefs come from data-* on <html> (set by Twig); also mirrored to window.__BEACON_* for app.ts.
 */

declare global {
  interface Window {
    __BEACON_USER_THEME__?: string;
    __BEACON_USER_DENSITY__?: string;
    __BEACON_USER_MOTION__?: string;
    __BEACON_USER_FONT_SCALE__?: string;
    __BEACON_USER_CONTRAST__?: string;
    __BEACON_USER_SIDEBAR__?: string;
    __BEACON_ISSUE_PANEL_DEFAULTS__?: string[];
  }
}

function applyThemeBoot(): void {
  try {
    const root = document.documentElement;
    const themeKey = 'beacon-theme';
    const userTheme = root.dataset.userTheme || '';
    const stored = localStorage.getItem(themeKey);
    const theme =
      userTheme === 'light' || userTheme === 'dark'
        ? userTheme
        : stored === 'light' || stored === 'dark'
          ? stored
          : window.matchMedia('(prefers-color-scheme: dark)').matches
            ? 'dark'
            : 'light';
    root.dataset.theme = theme;
    if (userTheme === 'light' || userTheme === 'dark') {
      localStorage.setItem(themeKey, userTheme);
    }

    const density = root.dataset.userDensity || '';
    root.dataset.uiDensity = density === 'compact' ? 'compact' : 'comfortable';

    const motion = root.dataset.userMotion || '';
    if (motion === 'reduce' || motion === 'full') {
      root.dataset.motion = motion;
    } else {
      root.dataset.motion = 'system';
    }

    const fontScale = root.dataset.userFontScale || '';
    root.dataset.fontScale = fontScale === 'sm' || fontScale === 'lg' ? fontScale : 'md';

    const contrast = root.dataset.userContrast || '';
    if (contrast === 'more') {
      root.dataset.contrast = 'more';
    } else if (window.matchMedia('(prefers-contrast: more)').matches) {
      root.dataset.contrast = 'more';
    } else {
      root.dataset.contrast = 'system';
    }

    const sidebarKey = 'beacon-sidebar';
    const userSidebar = root.dataset.userSidebar || '';
    if (
      (userSidebar === 'collapsed' || userSidebar === 'expanded') &&
      !localStorage.getItem(sidebarKey)
    ) {
      localStorage.setItem(sidebarKey, userSidebar);
    }

    // Mirror for Stimulus / app.ts consumers (no inline scripts required).
    window.__BEACON_USER_THEME__ = userTheme || undefined;
    window.__BEACON_USER_DENSITY__ = density || undefined;
    window.__BEACON_USER_MOTION__ = motion || undefined;
    window.__BEACON_USER_FONT_SCALE__ = fontScale || undefined;
    window.__BEACON_USER_CONTRAST__ = contrast || undefined;
    window.__BEACON_USER_SIDEBAR__ = userSidebar || undefined;
    try {
      const raw = root.dataset.issuePanelDefaults || '[]';
      const parsed: unknown = JSON.parse(raw);
      window.__BEACON_ISSUE_PANEL_DEFAULTS__ = Array.isArray(parsed)
        ? (parsed as string[])
        : ['raw', 'extra'];
    } catch {
      window.__BEACON_ISSUE_PANEL_DEFAULTS__ = ['raw', 'extra'];
    }
  } catch {
    document.documentElement.dataset.theme = 'light';
    document.documentElement.dataset.uiDensity = 'comfortable';
    document.documentElement.dataset.motion = 'system';
    document.documentElement.dataset.fontScale = 'md';
    document.documentElement.dataset.contrast = 'system';
  }
}

applyThemeBoot();
