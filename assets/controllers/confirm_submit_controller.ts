import { Controller } from '@hotwired/stimulus';

/**
 * window.confirm() on form submit without inline onsubmit= (CSP script-src).
 *
 * Usage:
 *   <form data-controller="confirm-submit"
 *         data-confirm-submit-message-value="Delete?"
 *         data-action="submit->confirm-submit#confirm">
 */
export default class extends Controller {
  static values = {
    message: String,
    /** When true, always prevent submit (e.g. last owner cannot be removed). */
    blocked: { type: Boolean, default: false },
  };

  declare readonly messageValue: string;
  declare readonly blockedValue: boolean;

  confirm(event: Event): void {
    if (this.blockedValue) {
      event.preventDefault();
      return;
    }
    const message = this.messageValue.trim();
    if ('' === message) {
      return;
    }
    if (!window.confirm(message)) {
      event.preventDefault();
    }
  }
}
