import { Controller } from '@hotwired/stimulus';

/**
 * Show/hide password without inline onclick= (CSP script-src 'self').
 *
 * Replaces nowo-tech/password-toggle-bundle native handlers on the host form theme.
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

    const iconHidden = this.element.querySelector<HTMLElement>('.icon-hidden');
    const iconVisible = this.element.querySelector<HTMLElement>('.icon-visible');

    if (input.type === 'password') {
      input.type = 'text';
      if (iconHidden) {
        iconHidden.style.display = 'none';
      }
      if (iconVisible) {
        iconVisible.style.display = '';
      }
      this.element.setAttribute('aria-label', this.hideLabelValue);
      return;
    }

    input.type = 'password';
    if (iconHidden) {
      iconHidden.style.display = '';
    }
    if (iconVisible) {
      iconVisible.style.display = 'none';
    }
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
