import { beforeEach, describe, expect, it, vi } from 'vitest';
import { applyThemeBoot } from './theme-boot';

describe('applyThemeBoot', () => {
  beforeEach(() => {
    localStorage.clear();
    const root = document.documentElement;
    for (const key of [...Object.keys(root.dataset)]) {
      delete root.dataset[key];
    }
    delete window.__BEACON_USER_THEME__;
    delete window.__BEACON_ISSUE_PANEL_DEFAULTS__;
  });

  it('applies light theme from user preference and mirrors globals', () => {
    document.documentElement.dataset.userTheme = 'light';
    document.documentElement.dataset.userDensity = 'compact';
    document.documentElement.dataset.userMotion = 'reduce';
    document.documentElement.dataset.userFontScale = 'lg';
    document.documentElement.dataset.userContrast = 'more';
    document.documentElement.dataset.issuePanelDefaults = '["stack","tags"]';

    applyThemeBoot();

    expect(document.documentElement.dataset.theme).toBe('light');
    expect(document.documentElement.dataset.uiDensity).toBe('compact');
    expect(document.documentElement.dataset.motion).toBe('reduce');
    expect(document.documentElement.dataset.fontScale).toBe('lg');
    expect(document.documentElement.dataset.contrast).toBe('more');
    expect(localStorage.getItem('beacon-theme')).toBe('light');
    expect(window.__BEACON_USER_THEME__).toBe('light');
    expect(window.__BEACON_ISSUE_PANEL_DEFAULTS__).toEqual(['stack', 'tags']);
  });

  it('falls back panel defaults on invalid JSON', () => {
    document.documentElement.dataset.issuePanelDefaults = '{bad';
    applyThemeBoot();
    expect(window.__BEACON_ISSUE_PANEL_DEFAULTS__).toEqual(['raw', 'extra']);
  });

  it('uses stored theme when user theme is auto', () => {
    localStorage.setItem('beacon-theme', 'dark');
    document.documentElement.dataset.userTheme = 'auto';
    applyThemeBoot();
    expect(document.documentElement.dataset.theme).toBe('dark');
  });

  it('uses prefers-color-scheme and prefers-contrast when unset', () => {
    const matchMedia = window.matchMedia as unknown as ReturnType<typeof vi.fn>;
    matchMedia.mockImplementation((query: string) => ({
      matches: query.includes('prefers-color-scheme: dark') || query.includes('prefers-contrast: more'),
      media: query,
      onchange: null,
      addListener: vi.fn(),
      removeListener: vi.fn(),
      addEventListener: vi.fn(),
      removeEventListener: vi.fn(),
      dispatchEvent: vi.fn(),
    }));

    applyThemeBoot();
    expect(document.documentElement.dataset.theme).toBe('dark');
    expect(document.documentElement.dataset.contrast).toBe('more');
  });

  it('seeds sidebar preference into localStorage when unset', () => {
    document.documentElement.dataset.userSidebar = 'collapsed';
    applyThemeBoot();
    expect(localStorage.getItem('beacon-sidebar')).toBe('collapsed');
    expect(window.__BEACON_USER_SIDEBAR__).toBe('collapsed');
  });

  it('falls back panel defaults when JSON is a non-array', () => {
    document.documentElement.dataset.issuePanelDefaults = '{"a":1}';
    applyThemeBoot();
    expect(window.__BEACON_ISSUE_PANEL_DEFAULTS__).toEqual(['raw', 'extra']);
  });
});
