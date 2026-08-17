/**
 * Morphicons bootstrap for Beacon (Vite + Twig).
 *
 * Defines the `<morph-icon>` custom element once and exposes Lucide icon data
 * for theme / password / content-width / sidebar burger toggles. Icons are Lucide *data*
 * (`IconNode`), not React components.
 */
import { defineMorphIcon, type IconInput, type MorphIconElement } from 'morphicons/element';
import { Expand, Eye, EyeOff, Menu, Moon, Shrink, Sun, X } from 'lucide';

defineMorphIcon();

export type MorphableIcon = IconInput;

export const themeIcons = {
  /** Shown in light mode → click switches to night. */
  toDark: Moon,
  /** Shown in dark mode → click switches to day. */
  toLight: Sun,
} as const;

export const passwordIcons = {
  /** Password hidden → affordance to reveal. */
  hidden: EyeOff,
  /** Password visible → affordance to conceal. */
  visible: Eye,
} as const;

export const contentWidthIcons = {
  /** Content width → click expands to full. */
  toFull: Expand,
  /** Full width → click returns to content. */
  toContent: Shrink,
} as const;

export const sidebarIcons = {
  /** Sidebar closed → open affordance. */
  closed: Menu,
  /** Sidebar open → close affordance. */
  open: X,
} as const;

export type Theme = 'light' | 'dark';
export type ContentWidth = 'content' | 'full';

function resolveReducedMotion(): 'never' | 'user' | 'always' {
  // Only honor Beacon's explicit reduce preference. OS prefers-reduced-motion
  // would make every morph an instant swap (reads as "morph not working").
  if (document.documentElement.dataset.motion === 'reduce') {
    return 'always';
  }
  return 'never';
}

/** Morphicons defaults to display:contents; force a real box so the SVG paints. */
function prepareMorphHost(el: MorphIconElement, sizePx: number): void {
  // Inline styles beat morphicons' display:contents (CSS also forces inline-flex).
  el.style.setProperty('display', 'inline-flex', 'important');
  el.style.alignItems = 'center';
  el.style.justifyContent = 'center';
  el.style.width = `${sizePx}px`;
  el.style.height = `${sizePx}px`;
  el.style.lineHeight = '0';
  el.style.color = 'inherit';
  el.reducedMotion = resolveReducedMotion();
  el.spring = 'bouncy';
}

function asMorphIcon(el: Element | null): MorphIconElement | null {
  if (!el || el.tagName.toLowerCase() !== 'morph-icon') {
    return null;
  }
  return el as MorphIconElement;
}

/** Paint the correct theme target icon (no animation). Marks the button ready. */
export function syncThemeMorphIcon(button: HTMLElement, theme: Theme, animate: boolean): void {
  const el = asMorphIcon(button.querySelector('[data-theme-morph]'));
  if (!el) {
    return;
  }

  prepareMorphHost(el, 14);
  const icon = theme === 'dark' ? themeIcons.toLight : themeIcons.toDark;
  if (animate) {
    el.morphTo(icon, 'bouncy');
  } else {
    el.set(icon);
  }
  button.classList.add('is-morph-ready');
}

/** Paint the correct content-width target icon (no animation on first sync). */
export function syncContentWidthMorphIcon(
  button: HTMLElement,
  width: ContentWidth,
  animate: boolean,
): void {
  const el = asMorphIcon(button.querySelector('[data-content-width-morph]'));
  if (!el) {
    return;
  }

  prepareMorphHost(el, 14);
  const icon = width === 'full' ? contentWidthIcons.toContent : contentWidthIcons.toFull;
  if (animate) {
    el.morphTo(icon, 'bouncy');
  } else {
    el.set(icon);
  }
  button.classList.add('is-morph-ready');
}


/** Menu ↔ X for the header sidebar burger. */
export function syncSidebarMorphIcon(
  button: HTMLElement,
  open: boolean,
  animate: boolean,
): void {
  const el = asMorphIcon(button.querySelector('[data-sidebar-morph]'));
  if (!el) {
    return;
  }

  prepareMorphHost(el, 18);
  const icon = open ? sidebarIcons.open : sidebarIcons.closed;
  if (animate) {
    el.morphTo(icon, 'bouncy');
  } else {
    el.set(icon);
  }
  button.classList.add('is-morph-ready');
}

/** Ensure a password toggle button has a hydrated `<morph-icon>` and set state. */
export function syncPasswordMorphIcon(
  button: HTMLElement,
  visible: boolean,
  animate: boolean,
): void {
  let el = asMorphIcon(button.querySelector('[data-password-morph]'));
  if (!el) {
    el = document.createElement('morph-icon') as MorphIconElement;
    el.setAttribute('data-password-morph', '');
    el.setAttribute('aria-hidden', 'true');
    el.setAttribute('size', '18');
    el.setAttribute('stroke-width', '1.75');
    el.className = 'password-toggle__morph';
    button.replaceChildren(el);
  }

  prepareMorphHost(el, 18);
  const icon = visible ? passwordIcons.visible : passwordIcons.hidden;
  if (animate) {
    el.morphTo(icon, 'bouncy');
  } else {
    el.set(icon);
  }
  button.classList.add('is-morph-ready');
}

export type { MorphIconElement };
