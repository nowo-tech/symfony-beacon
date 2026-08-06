import { afterEach, describe, expect, it, vi } from 'vitest';
import { prefersReducedMotion, resolveDark, watchThemeAndMotion } from './theme';

describe('resolveDark', () => {
  it('honors explicit theme', () => {
    expect(resolveDark('dark', null)).toBe(true);
    expect(resolveDark('light', null)).toBe(false);
  });

  it('reads ancestor data-theme', () => {
    const host = document.createElement('div');
    const parent = document.createElement('div');
    parent.setAttribute('data-theme', 'dark');
    parent.appendChild(host);
    document.body.appendChild(parent);

    expect(resolveDark('auto', host)).toBe(true);
  });

  it('reads ancestor dark class', () => {
    const host = document.createElement('div');
    const parent = document.createElement('div');
    parent.classList.add('dark');
    parent.appendChild(host);
    document.body.appendChild(parent);

    expect(resolveDark('auto', host)).toBe(true);
  });
});

describe('prefersReducedMotion', () => {
  afterEach(() => {
    delete document.documentElement.dataset.motion;
  });

  it('honors data-motion on html', () => {
    document.documentElement.dataset.motion = 'reduce';
    expect(prefersReducedMotion()).toBe(true);
    document.documentElement.dataset.motion = 'full';
    expect(prefersReducedMotion()).toBe(false);
  });
});

describe('watchThemeAndMotion', () => {
  it('emits immediately and unsubscribes', () => {
    const onChange = vi.fn();
    const unsub = watchThemeAndMotion('light', null, onChange);
    expect(onChange).toHaveBeenCalledWith(false, expect.any(Boolean));
    unsub();
  });
});
