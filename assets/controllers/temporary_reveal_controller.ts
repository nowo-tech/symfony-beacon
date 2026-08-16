import { Controller } from '@hotwired/stimulus';

/**
 * Show a secret value temporarily (masked by default), then auto-hide.
 *
 * With {@code clearOnHide}, the plaintext is stripped from the DOM after hide so a
 * shoulder-surfer / later XSS cannot re-read {@code data-*-secret-value}.
 *
 * Usage:
 *   data-controller="temporary-reveal"
 *   data-temporary-reveal-secret-value="full-secret"
 *   data-temporary-reveal-duration-value="30000"
 *   data-temporary-reveal-start-revealed-value="true"
 *   data-temporary-reveal-clear-on-hide-value="true"
 *   data-temporary-reveal-cleared-label-value="Hidden — rotate to mint a new DSN"
 */
export default class extends Controller {
  static targets = ['display', 'toggle'];

  static values = {
    secret: { type: String, default: '' },
    duration: { type: Number, default: 30_000 },
    showLabel: { type: String, default: 'Show' },
    hideLabel: { type: String, default: 'Hide' },
    startRevealed: { type: Boolean, default: false },
    clearOnHide: { type: Boolean, default: false },
    clearedLabel: { type: String, default: 'Hidden' },
  };

  declare readonly displayTarget: HTMLElement;
  declare readonly hasToggleTarget: boolean;
  declare readonly toggleTarget: HTMLElement;
  declare readonly secretValue: string;
  declare readonly durationValue: number;
  declare readonly showLabelValue: string;
  declare readonly hideLabelValue: string;
  declare readonly startRevealedValue: boolean;
  declare readonly clearOnHideValue: boolean;
  declare readonly clearedLabelValue: string;

  private hideTimer: number | null = null;
  private revealed = false;
  private cleared = false;

  connect(): void {
    if (this.startRevealedValue) {
      this.reveal();
      return;
    }
    this.applyMasked();
  }

  disconnect(): void {
    this.clearTimer();
  }

  toggle(): void {
    if (this.cleared) {
      return;
    }
    if (this.revealed) {
      this.hide();
      return;
    }
    this.reveal();
  }

  reveal(): void {
    if (this.cleared) {
      return;
    }
    const secret = this.secretValue.trim();
    if ('' === secret) {
      return;
    }

    this.revealed = true;
    this.displayTarget.textContent = secret;
    this.displayTarget.dataset.revealed = 'true';
    this.syncToggleLabel();
    this.clearTimer();
    if (this.durationValue > 0) {
      this.hideTimer = window.setTimeout(() => this.hide(), this.durationValue);
    }
  }

  hide(): void {
    this.clearTimer();
    this.revealed = false;
    if (this.clearOnHideValue) {
      this.purgeSecretFromDom();
      return;
    }
    this.applyMasked();
    this.syncToggleLabel();
  }

  private applyMasked(): void {
    const secret = this.secretValue.trim();
    this.displayTarget.textContent = '' === secret ? '' : this.mask(secret);
    delete this.displayTarget.dataset.revealed;
  }

  private purgeSecretFromDom(): void {
    this.cleared = true;
    this.secretValue = '';
    this.element.removeAttribute('data-temporary-reveal-secret-value');
    this.displayTarget.textContent = this.clearedLabelValue;
    delete this.displayTarget.dataset.revealed;
    this.displayTarget.dataset.cleared = 'true';

    this.element.querySelectorAll<HTMLElement>('[data-clipboard-copy-text-value]').forEach((el) => {
      el.setAttribute('data-clipboard-copy-text-value', '');
      if (el instanceof HTMLButtonElement) {
        el.disabled = true;
      }
    });

    if (this.hasToggleTarget) {
      this.toggleTarget.setAttribute('disabled', 'true');
      this.toggleTarget.setAttribute('aria-disabled', 'true');
      this.toggleTarget.setAttribute('aria-pressed', 'false');
      this.toggleTarget.textContent = this.clearedLabelValue;
      this.toggleTarget.setAttribute('aria-label', this.clearedLabelValue);
    }
  }

  private syncToggleLabel(): void {
    if (!this.hasToggleTarget || this.cleared) {
      return;
    }
    const label = this.revealed ? this.hideLabelValue : this.showLabelValue;
    this.toggleTarget.textContent = label;
    this.toggleTarget.setAttribute('aria-label', label);
    this.toggleTarget.setAttribute('aria-pressed', this.revealed ? 'true' : 'false');
  }

  private mask(value: string): string {
    return value.replace(/(:\/\/[^:/@]+:)[^@]+(@)/, '$1••••••••$2');
  }

  private clearTimer(): void {
    if (null === this.hideTimer) {
      return;
    }
    window.clearTimeout(this.hideTimer);
    this.hideTimer = null;
  }
}
