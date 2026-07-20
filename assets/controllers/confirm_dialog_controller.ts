import { Controller } from "@hotwired/stimulus";

/**
 * Accessible <dialog> helpers for destructive confirmations.
 *
 * Optional typed confirmation: enable the submit button only when the input
 * matches `expectedValue` (exact string).
 */
export default class extends Controller {
  static targets = ["dialog", "confirmInput", "submit"];

  static values = {
    expected: String,
  };

  declare readonly dialogTarget: HTMLDialogElement;
  declare readonly hasConfirmInputTarget: boolean;
  declare readonly confirmInputTarget: HTMLInputElement;
  declare readonly hasSubmitTarget: boolean;
  declare readonly submitTarget: HTMLButtonElement;
  declare readonly hasExpectedValue: boolean;
  declare readonly expectedValue: string;

  open(event?: Event): void {
    event?.preventDefault();
    if (this.hasConfirmInputTarget) {
      this.confirmInputTarget.value = "";
    }
    this.syncSubmit();
    this.dialogTarget.showModal();
    if (this.hasConfirmInputTarget) {
      this.confirmInputTarget.focus();
    }
  }

  close(event?: Event): void {
    event?.preventDefault();
    this.dialogTarget.close();
  }

  backdropClose(event: MouseEvent): void {
    if (event.target === this.dialogTarget) {
      this.dialogTarget.close();
    }
  }

  syncSubmit(): void {
    if (!this.hasSubmitTarget) {
      return;
    }
    if (!this.hasExpectedValue || this.expectedValue === "") {
      this.submitTarget.disabled = false;
      return;
    }
    if (!this.hasConfirmInputTarget) {
      this.submitTarget.disabled = true;
      return;
    }
    this.submitTarget.disabled = this.confirmInputTarget.value !== this.expectedValue;
  }
}
