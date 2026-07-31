/**
 * Theme / reduced-motion helpers for Thinking Orbs (no React).
 *
 * Upstream: https://github.com/Jakubantalik/thinking-orbs (MIT)
 */

import type { OrbTheme } from './types';

function ancestorTheme(el: Element | null): boolean | null {
  let node: Element | null = el;
  while (node) {
    const attr = node.getAttribute('data-theme');
    if (attr === 'dark') {
      return true;
    }
    if (attr === 'light') {
      return false;
    }
    if (node.classList.contains('dark')) {
      return true;
    }
    if (node.classList.contains('light')) {
      return false;
    }
    node = node.parentElement;
  }
  return null;
}

function systemDark(): boolean {
  return typeof matchMedia === 'undefined' || matchMedia('(prefers-color-scheme: dark)').matches;
}

/** Resolve whether the orb should paint light ink (dark substrate). */
export function resolveDark(theme: OrbTheme, host: Element | null): boolean {
  if (theme === 'dark') {
    return true;
  }
  if (theme === 'light') {
    return false;
  }
  return ancestorTheme(host) ?? systemDark();
}

/** Whether the user prefers reduced motion (static frame). */
export function prefersReducedMotion(): boolean {
  const rootMotion = document.documentElement.dataset.motion;
  if (rootMotion === 'reduce') {
    return true;
  }
  if (rootMotion === 'full') {
    return false;
  }
  if (typeof matchMedia === 'undefined') {
    return false;
  }
  return matchMedia('(prefers-reduced-motion: reduce)').matches;
}

/**
 * Watch live theme / reduced-motion changes. Returns an unsubscribe function.
 */
export function watchThemeAndMotion(
  theme: OrbTheme,
  host: Element | null,
  onChange: (dark: boolean, reduced: boolean) => void,
): () => void {
  const emit = (): void => {
    onChange(resolveDark(theme, host), prefersReducedMotion());
  };

  emit();

  if (theme !== 'auto') {
    const mqMotion =
      typeof matchMedia !== 'undefined' ? matchMedia('(prefers-reduced-motion: reduce)') : null;
    const onMotion = (): void => emit();
    mqMotion?.addEventListener('change', onMotion);
    return () => mqMotion?.removeEventListener('change', onMotion);
  }

  const mqTheme =
    typeof matchMedia !== 'undefined' ? matchMedia('(prefers-color-scheme: dark)') : null;
  const mqMotion =
    typeof matchMedia !== 'undefined' ? matchMedia('(prefers-reduced-motion: reduce)') : null;
  const onMq = (): void => emit();
  mqTheme?.addEventListener('change', onMq);
  mqMotion?.addEventListener('change', onMq);

  let mo: MutationObserver | null = null;
  if (typeof MutationObserver !== 'undefined') {
    mo = new MutationObserver(emit);
    mo.observe(document.documentElement, {
      attributes: true,
      attributeFilter: ['class', 'data-theme', 'data-motion'],
      subtree: true,
    });
  }

  return () => {
    mqTheme?.removeEventListener('change', onMq);
    mqMotion?.removeEventListener('change', onMq);
    mo?.disconnect();
  };
}
