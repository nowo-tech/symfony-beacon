import { Controller } from '@hotwired/stimulus';
import { syncPasswordMorphIcon } from '../lib/morphicons';

/**
 * Show/hide password without inline onclick= (CSP script-src 'self').
 *
 * Replaces nowo-tech/password-toggle-bundle native handlers on the host form theme.
 * Icons: Morphicons EyeOff ↔ Eye (falls back to dual UX Icons until hydrated).
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

  connect(): void {
    const input = this.element.previousElementSibling;
    const visible = input instanceof HTMLInputElement && input.type === 'text';
    syncPasswordMorphIcon(this.element as HTMLElement, visible, false);
  }

  toggle(): void {
    const input = this.element.previousElementSibling;
    if (!(input instanceof HTMLInputElement)) {
      return;
    }

    if (input.type === 'password') {
      input.type = 'text';
      this.element.classList.add('is-password-visible');
      this.element.setAttribute('aria-label', this.hideLabelValue);
      syncPasswordMorphIcon(this.element as HTMLElement, true, true);
      return;
    }

    input.type = 'password';
    this.element.classList.remove('is-password-visible');
    this.element.setAttribute('aria-label', this.showLabelValue);
    syncPasswordMorphIcon(this.element as HTMLElement, false, true);
  }

  keydown(event: KeyboardEvent): void {
    if (event.key !== 'Enter' && event.key !== ' ') {
      return;
    }
    event.preventDefault();
    this.toggle();
  }
}
