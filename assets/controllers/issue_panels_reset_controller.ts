import { Controller } from '@hotwired/stimulus';

/**
 * Clear beacon.issuePanelState from localStorage (Account → Display → Panels).
 */
export default class extends Controller {
  static values = {
    doneLabel: String,
  };

  declare readonly doneLabelValue: string;

  reset(): void {
    try {
      localStorage.removeItem('beacon.issuePanelState');
    } catch {
      // Ignore quota / private mode.
    }
    if (this.element instanceof HTMLElement && '' !== this.doneLabelValue) {
      this.element.textContent = this.doneLabelValue;
    }
  }
}
