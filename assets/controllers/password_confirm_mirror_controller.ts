import { Controller } from '@hotwired/stimulus';

/**
 * Mirror PasswordStrength “Use this password” into the confirm field.
 * Kit only fills the strength input.
 */
export default class extends Controller {
  connect(): void {
    document.addEventListener('click', this.onClick);
  }

  disconnect(): void {
    document.removeEventListener('click', this.onClick);
  }

  private readonly onClick = (event: Event): void => {
    const target = event.target;
    if (!(target instanceof Element)) {
      return;
    }
    const item = target.closest('.password-strength-modal-item');
    if (!item) {
      return;
    }
    const useBtn = item.querySelector('button:last-of-type');
    if (!(useBtn instanceof HTMLElement) || target.closest('button') !== useBtn) {
      return;
    }
    window.requestAnimationFrame(() => {
      const form = this.element instanceof HTMLFormElement ? this.element : this.element.querySelector('form');
      const scope = form ?? document;
      const input = scope.querySelector('input.password-strength-input');
      const confirm = scope.querySelector('input[name="user_preferences[plainPassword_confirm]"]');
      if (input instanceof HTMLInputElement && confirm instanceof HTMLInputElement && '' !== input.value) {
        confirm.value = input.value;
        confirm.dispatchEvent(new Event('input', { bubbles: true }));
        confirm.dispatchEvent(new Event('change', { bubbles: true }));
      }
    });
  };
}
