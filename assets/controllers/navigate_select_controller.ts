import { Controller } from '@hotwired/stimulus';

/**
 * Navigate to the selected option value (CSP-safe; no inline onchange).
 */
export default class extends Controller {
  change(event: Event): void {
    const select = event.currentTarget;
    if (!(select instanceof HTMLSelectElement)) {
      return;
    }
    const url = select.value.trim();
    if ('' !== url) {
      window.location.assign(url);
    }
  }
}
