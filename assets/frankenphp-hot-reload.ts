/**
 * FrankenPHP hot reload client (dev only).
 *
 * Loaded from Twig only when $_SERVER['FRANKENPHP_HOT_RELOAD'] is set
 * (Caddy php_server { hot_reload } in the local Caddyfile).
 *
 * Symfony Web Debug Toolbar already sets data-frankenphp-hot-reload-preserve;
 * the observer covers late-injected nodes.
 *
 * @see https://frankenphp.dev/docs/hot-reload/
 */
import { Idiomorph } from 'idiomorph';

declare global {
  interface Window {
    Idiomorph?: typeof Idiomorph;
  }
}

window.Idiomorph = Idiomorph;

const markPreserve = (): void => {
  document.querySelectorAll<HTMLElement>('[id^="sfwdt"], .sf-toolbar, .sf-minitoolbar').forEach((el) => {
    if (!el.hasAttribute('data-frankenphp-hot-reload-preserve')) {
      el.setAttribute('data-frankenphp-hot-reload-preserve', '');
    }
  });
};

markPreserve();
new MutationObserver(markPreserve).observe(document.documentElement, { childList: true, subtree: true });

await import('frankenphp-hot-reload');
