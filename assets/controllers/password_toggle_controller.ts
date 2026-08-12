import { Controller } from '@hotwired/stimulus';

/**
 * Show/hide password without inline onclick= (CSP script-src 'self').
 *
 * Replaces nowo-tech/password-toggle-bundle native handlers on the host form theme.
 * Icon visibility: toggles {@code is-password-visible} (PasswordToggleBundle ≥2.1.1 CSS
 * and host `_components.scss`); do not set element.style (CSP style-src with a nonce
 * ignores 'unsafe-inline').
 *
 * Usage (on the toggle button next to the password input):
 *   data-controller="password-toggle"
 *   data-action="click->password-toggle#toggle keydown->password-toggle#keydown"
 *   data-password-toggle-show-label-value="Show password"
 *   data-password-toggle-hide-label-value="Hide password"
 */
export default class extends Controller {
  static values = {
    showLabel: { type: String, default: 'Show password' },
    hideLabel: { type: String, default: 'Hide password' },
  };

  declare readonly showLabelValue: string;
  declare readonly hideLabelValue: string;

  toggle(): void {
    const input = this.element.previousElementSibling;
    if (!(input instanceof HTMLInputElement)) {
      return;
    }

    if (input.type === 'password') {
      input.type = 'text';
      this.element.classList.add('is-password-visible');
      this.element.setAttribute('aria-label', this.hideLabelValue);
      return;
    }

    input.type = 'password';
    this.element.classList.remove('is-password-visible');
    this.element.setAttribute('aria-label', this.showLabelValue);
  }

  keydown(event: KeyboardEvent): void {
    if (event.key !== 'Enter' && event.key !== ' ') {
      return;
    }
    event.preventDefault();
    this.toggle();
  }
}
