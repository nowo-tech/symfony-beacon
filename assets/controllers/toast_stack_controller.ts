import { Controller } from "@hotwired/stimulus";

/**
 * Auto-dismisses Symfony flash toasts rendered by `_toasts.html.twig`.
 *
 * Each toast target may set `data-timeout` (ms). Hover or focus pauses the timer;
 * the dismiss button removes the toast with a leave animation.
 */
export default class extends Controller {
  static targets = ["toast"];

  /** Active auto-dismiss timers keyed by toast element. */
  private timers = new Map<HTMLElement, number>();

  /** Schedule dismiss when a toast enters the DOM. */
  toastTargetConnected(toast: HTMLElement): void {
    this.schedule(toast);

    toast.addEventListener("mouseenter", this.pause);
    toast.addEventListener("mouseleave", this.resume);
    toast.addEventListener("focusin", this.pause);
    toast.addEventListener("focusout", this.resume);
  }

  /** Clear timers and listeners when a toast is removed. */
  toastTargetDisconnected(toast: HTMLElement): void {
    this.clearTimer(toast);
    toast.removeEventListener("mouseenter", this.pause);
    toast.removeEventListener("mouseleave", this.resume);
    toast.removeEventListener("focusin", this.pause);
    toast.removeEventListener("focusout", this.resume);
  }

  /** Close button handler — finds the parent toast and animates it out. */
  dismiss(event: Event): void {
    const button = event.currentTarget;
    if (!(button instanceof HTMLElement)) {
      return;
    }
    const toast = button.closest<HTMLElement>("[data-toast-stack-target='toast']");
    if (toast) {
      this.leave(toast);
    }
  }

  private schedule = (toast: HTMLElement): void => {
    this.clearTimer(toast);
    const raw = toast.dataset.timeout;
    const timeout = raw ? Number.parseInt(raw, 10) : 5000;
    if (!Number.isFinite(timeout) || timeout <= 0) {
      return;
    }
    const id = window.setTimeout(() => this.leave(toast), timeout);
    this.timers.set(toast, id);
  };

  private pause = (event: Event): void => {
    const toast = event.currentTarget;
    if (toast instanceof HTMLElement) {
      this.clearTimer(toast);
    }
  };

  private resume = (event: Event): void => {
    const toast = event.currentTarget;
    if (toast instanceof HTMLElement && !toast.classList.contains("is-leaving")) {
      this.schedule(toast);
    }
  };

  private clearTimer(toast: HTMLElement): void {
    const id = this.timers.get(toast);
    if (id !== undefined) {
      window.clearTimeout(id);
      this.timers.delete(toast);
    }
  }

  /** Animate out, then remove the toast (and the empty stack container). */
  private leave(toast: HTMLElement): void {
    this.clearTimer(toast);
    if (toast.classList.contains("is-leaving")) {
      return;
    }
    toast.classList.add("is-leaving");

    let finished = false;
    const done = (): void => {
      if (finished) {
        return;
      }
      finished = true;
      if (toast.isConnected) {
        toast.remove();
      }
      if (this.element.isConnected && this.toastTargets.length === 0) {
        this.element.remove();
      }
    };

    toast.addEventListener("animationend", done, { once: true });
    // Fallback if animationend does not fire (reduced motion / no animation).
    window.setTimeout(done, 400);
  }
}
