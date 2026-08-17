import { afterEach, describe, expect, it } from 'vitest';
import {
  syncContentWidthMorphIcon,
  syncPasswordMorphIcon,
  syncSidebarMorphIcon,
  syncThemeMorphIcon,
} from './index';

describe('morphicons helpers', () => {
  afterEach(() => {
    document.body.innerHTML = '';
    document.documentElement.dataset.motion = '';
  });

  it('hydrates theme morph-icon without animation and morphs on later sync', () => {
    document.body.innerHTML = `
      <button type="button" data-theme-toggle>
        <morph-icon data-theme-morph size="14"></morph-icon>
      </button>
    `;
    const button = document.querySelector('button') as HTMLElement;

    syncThemeMorphIcon(button, 'light', false);
    expect(button.classList.contains('is-morph-ready')).toBe(true);
    const el = button.querySelector('morph-icon') as HTMLElement;
    expect(el.querySelector('svg')).not.toBeNull();
    expect(el.style.getPropertyValue('display')).toBe('inline-flex');
    expect(el.reducedMotion).toBe('never');

    syncThemeMorphIcon(button, 'dark', true);
    expect(button.classList.contains('is-morph-ready')).toBe(true);
  });

  it('hydrates content-width and password morph icons', () => {
    document.documentElement.dataset.motion = 'reduce';
    document.body.innerHTML = `
      <button type="button" data-content-width-toggle>
        <morph-icon data-content-width-morph size="14"></morph-icon>
      </button>
      <input type="password" />
      <button type="button" id="pwd-toggle">
        <span class="icon-hidden"></span>
      </button>
    `;
    const widthBtn = document.querySelector('[data-content-width-toggle]') as HTMLElement;
    const pwdBtn = document.getElementById('pwd-toggle') as HTMLElement;

    syncContentWidthMorphIcon(widthBtn, 'content', false);
    expect(widthBtn.classList.contains('is-morph-ready')).toBe(true);

    syncPasswordMorphIcon(pwdBtn, false, false);
    expect(pwdBtn.classList.contains('is-morph-ready')).toBe(true);
    expect(pwdBtn.querySelector('[data-password-morph]')).not.toBeNull();

    syncPasswordMorphIcon(pwdBtn, true, true);
    expect(pwdBtn.querySelector('morph-icon')).not.toBeNull();
  });


  it('hydrates sidebar burger Menu ↔ X', () => {
    document.body.innerHTML = `
      <button type="button" data-sidebar-toggle>
        <svg class="nowo-ui-burger__icon--static"></svg>
        <morph-icon data-sidebar-morph size="18"></morph-icon>
      </button>
    `;
    const button = document.querySelector('button') as HTMLElement;
    syncSidebarMorphIcon(button, false, false);
    expect(button.classList.contains('is-morph-ready')).toBe(true);
    syncSidebarMorphIcon(button, true, true);
    expect(button.querySelector('morph-icon svg')).not.toBeNull();
  });
  it('no-ops when morph targets are missing', () => {
    const button = document.createElement('button');
    syncThemeMorphIcon(button, 'dark', true);
    syncContentWidthMorphIcon(button, 'full', true);
    syncSidebarMorphIcon(button, true, true);
    expect(button.classList.contains('is-morph-ready')).toBe(false);
  });
});
