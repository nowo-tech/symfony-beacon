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
    parent.setAttribute('data-theme', 'light');
    expect(resolveDark('auto', host)).toBe(false);
    parent.remove();
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

  it('returns false when matchMedia is unavailable', () => {
    delete document.documentElement.dataset.motion;
    const original = window.matchMedia;
    // @ts-expect-error intentional coverage of missing matchMedia
    window.matchMedia = undefined;
    expect(prefersReducedMotion()).toBe(false);
    window.matchMedia = original;
  });
});

describe('watchThemeAndMotion', () => {
  it('emits immediately and unsubscribes', () => {
    const onChange = vi.fn();
    const unsub = watchThemeAndMotion('light', null, onChange);
    expect(onChange).toHaveBeenCalledWith(false, expect.any(Boolean));
    unsub();
  });

  it('watches auto theme via matchMedia and MutationObserver', () => {
    const listeners: Array<() => void> = [];
    const matchMedia = window.matchMedia as unknown as ReturnType<typeof vi.fn>;
    matchMedia.mockImplementation((query: string) => ({
      matches: false,
      media: query,
      onchange: null,
      addListener: vi.fn(),
      removeListener: vi.fn(),
      addEventListener: (_: string, cb: () => void) => listeners.push(cb),
      removeEventListener: vi.fn(),
      dispatchEvent: vi.fn(),
    }));

    const onChange = vi.fn();
    const host = document.createElement('div');
    document.body.appendChild(host);
    const unsub = watchThemeAndMotion('auto', host, onChange);
    expect(onChange).toHaveBeenCalled();
    document.documentElement.setAttribute('data-theme', 'dark');
    listeners.forEach((cb) => cb());
    unsub();
    host.remove();
  });

  it('reads light ancestor class and system fallback', () => {
    const host = document.createElement('div');
    const parent = document.createElement('div');
    parent.classList.add('light');
    parent.appendChild(host);
    document.body.appendChild(parent);
    expect(resolveDark('auto', host)).toBe(false);
    parent.remove();
    expect(resolveDark('auto', null)).toEqual(expect.any(Boolean));
  });
});
