import { Controller } from "@hotwired/stimulus";

type ChallengeStatus = "pending" | "approved" | "denied" | "expired" | "consumed" | string;

/**
 * Desktop QR login: countdown + status poll until approved (then complete) or terminal state.
 */
export default class extends Controller {
  static targets = ["status", "countdown", "expiresRow", "actions", "retry"];

  static values = {
    statusUrl: String,
    completeUrl: String,
    startUrl: String,
    pollInterval: { type: Number, default: 1500 },
    expiresAt: Number,
    waiting: String,
    approved: String,
    denied: String,
    expired: String,
    retry: String,
  };

  declare readonly statusTarget: HTMLElement;
  declare readonly countdownTarget: HTMLElement;
  declare readonly expiresRowTarget: HTMLElement;
  declare readonly actionsTarget: HTMLElement;
  declare readonly retryTarget: HTMLAnchorElement;

  declare readonly statusUrlValue: string;
  declare readonly completeUrlValue: string;
  declare readonly startUrlValue: string;
  declare readonly pollIntervalValue: number;
  declare readonly expiresAtValue: number;
  declare readonly waitingValue: string;
  declare readonly approvedValue: string;
  declare readonly deniedValue: string;
  declare readonly expiredValue: string;
  declare readonly retryValue: string;

  private pollTimer: number | null = null;
  private countdownTimer: number | null = null;
  private stopped = false;
  private expiresAtUnix = 0;

  connect(): void {
    this.expiresAtUnix = this.expiresAtValue;
    this.tickCountdown();
    this.countdownTimer = window.setInterval(() => this.tickCountdown(), 1000);
    void this.poll();
  }

  disconnect(): void {
    this.stop();
  }

  private stop(): void {
    this.stopped = true;
    if (this.pollTimer !== null) {
      window.clearTimeout(this.pollTimer);
      this.pollTimer = null;
    }
    if (this.countdownTimer !== null) {
      window.clearInterval(this.countdownTimer);
      this.countdownTimer = null;
    }
  }

  private tickCountdown(): void {
    const remaining = Math.max(0, Math.floor(this.expiresAtUnix - Date.now() / 1000));
    this.countdownTarget.textContent = this.formatRemaining(remaining);
    if (remaining <= 0 && !this.stopped) {
      this.onTerminal("expired");
    }
  }

  private formatRemaining(totalSeconds: number): string {
    const mins = Math.floor(totalSeconds / 60);
    const secs = totalSeconds % 60;
    return `${mins}:${secs.toString().padStart(2, "0")}`;
  }

  private schedulePoll(): void {
    if (this.stopped) {
      return;
    }
    const interval = Math.max(500, this.pollIntervalValue || 1500);
    this.pollTimer = window.setTimeout(() => {
      void this.poll();
    }, interval);
  }

  private async poll(): Promise<void> {
    if (this.stopped) {
      return;
    }

    try {
      const response = await fetch(this.statusUrlValue, {
        headers: { Accept: "application/json" },
        credentials: "same-origin",
      });

      if (!response.ok) {
        this.schedulePoll();
        return;
      }

      const data = (await response.json()) as {
        status?: ChallengeStatus;
        expires_in?: number;
      };

      if (typeof data.expires_in === "number" && Number.isFinite(data.expires_in)) {
        this.expiresAtUnix = Math.floor(Date.now() / 1000) + Math.max(0, data.expires_in);
        this.tickCountdown();
      }

      const status = data.status ?? "pending";
      if (status === "pending") {
        this.statusTarget.textContent = this.waitingValue;
        this.schedulePoll();
        return;
      }

      if (status === "approved" || status === "consumed") {
        this.onApproved();
        return;
      }

      this.onTerminal(status);
    } catch {
      this.schedulePoll();
    }
  }

  private onApproved(): void {
    this.stop();
    this.statusTarget.textContent = this.approvedValue;
    this.expiresRowTarget.hidden = true;
    this.actionsTarget.hidden = true;
    window.location.assign(this.completeUrlValue);
  }

  private onTerminal(status: ChallengeStatus): void {
    this.stop();
    this.expiresRowTarget.hidden = true;
    this.actionsTarget.hidden = false;
    this.retryTarget.href = this.startUrlValue;
    this.retryTarget.textContent = this.retryValue;

    if (status === "denied") {
      this.statusTarget.textContent = this.deniedValue;
      return;
    }

    this.statusTarget.textContent = this.expiredValue;
    this.countdownTarget.textContent = "0:00";
  }
}
