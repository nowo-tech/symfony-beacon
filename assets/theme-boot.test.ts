import { beforeEach, describe, expect, it } from 'vitest';
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
});
