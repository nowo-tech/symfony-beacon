import { Controller } from "@hotwired/stimulus";

/**
 * Accessible <dialog> helpers for destructive confirmations.
 *
 * Dialogs are portaled to document.body so panel isolation / overflow / blur
 * cannot trap or hide showModal() top-layer UI.
 *
 * Optional typed confirmation: enable the submit button only when the input
 * matches `expectedValue` (exact string).
 */
export default class extends Controller {
  static targets = ["dialog", "confirmInput", "submit"];

  static values = {
    expected: String,
    openOnConnect: Boolean,
  };

  declare readonly dialogTarget: HTMLDialogElement;
  declare readonly hasDialogTarget: boolean;
  declare readonly hasConfirmInputTarget: boolean;
  declare readonly confirmInputTarget: HTMLInputElement;
  declare readonly hasSubmitTarget: boolean;
  declare readonly submitTarget: HTMLButtonElement;
  declare readonly hasExpectedValue: boolean;
  declare readonly expectedValue: string;
  declare readonly openOnConnectValue: boolean;

  /** Ignore backdrop clicks that belong to the same gesture that opened the dialog. */
  private ignoreBackdropUntil = 0;

  private dialogEl: HTMLDialogElement | null = null;
  private confirmInputEl: HTMLInputElement | null = null;
  private submitEl: HTMLButtonElement | null = null;
  private readonly abort = new AbortController();

  connect(): void {
    if (!this.hasDialogTarget) {
      return;
    }

    this.dialogEl = this.dialogTarget;
    this.confirmInputEl = this.hasConfirmInputTarget ? this.confirmInputTarget : null;
    this.submitEl = this.hasSubmitTarget ? this.submitTarget : null;

    // Portal once so stacking contexts (.panel isolation, overflow clip) cannot hide the modal.
    if (this.dialogEl.parentElement !== document.body) {
      document.body.appendChild(this.dialogEl);
    }

    const { signal } = this.abort;
    this.dialogEl.addEventListener("click", (event) => this.onDialogClick(event), { signal });
    this.confirmInputEl?.addEventListener("input", () => this.syncSubmit(), { signal });

    if (this.openOnConnectValue) {
      this.open();
    }
  }

  disconnect(): void {
    this.abort.abort();
    const dialog = this.dialogEl;
    if (dialog?.open) {
      dialog.close();
    }
    // Keep portaled node if the host is gone; remove orphan dialogs on full teardown.
    if (dialog && !this.element.isConnected) {
      dialog.remove();
    }
    this.dialogEl = null;
    this.confirmInputEl = null;
    this.submitEl = null;
  }

  open(event?: Event): void {
    event?.preventDefault();
    event?.stopPropagation();

    const dialog = this.dialogEl ?? (this.hasDialogTarget ? this.dialogTarget : null);
    if (!(dialog instanceof HTMLDialogElement)) {
      return;
    }
    this.dialogEl = dialog;

    if (dialog.parentElement !== document.body) {
      document.body.appendChild(dialog);
    }

    if (this.confirmInputEl) {
      this.confirmInputEl.value = "";
    }
    this.syncSubmit();

    // Opening synchronously from a click can deliver that same click to the
    // newly shown modal backdrop (pointer under the overlay), which would
    // close the dialog immediately via backdropClose.
    this.ignoreBackdropUntil = Date.now() + 400;
    requestAnimationFrame(() => {
      if (!dialog.open) {
        dialog.showModal();
      }
      this.confirmInputEl?.focus();
    });
  }

  close(event?: Event): void {
    event?.preventDefault();
    this.dialogEl?.close();
  }

  backdropClose(event: MouseEvent): void {
    if (Date.now() < this.ignoreBackdropUntil) {
      return;
    }
    if (event.target === this.dialogEl) {
      this.dialogEl?.close();
    }
  }

  syncSubmit(): void {
    if (!this.submitEl) {
      return;
    }
    if (!this.hasExpectedValue || this.expectedValue === "") {
      this.submitEl.disabled = false;
      return;
    }
    if (!this.confirmInputEl) {
      this.submitEl.disabled = true;
      return;
    }
    this.submitEl.disabled = this.confirmInputEl.value !== this.expectedValue;
  }

  /** Delegated clicks: backdrop dismiss + Cancel buttons after portal to body. */
  private onDialogClick(event: MouseEvent): void {
    const target = event.target;
    if (!(target instanceof Element)) {
      return;
    }

    if (target.closest("[data-confirm-dialog-close]")) {
      this.close(event);
      return;
    }

    this.backdropClose(event);
  }
}
