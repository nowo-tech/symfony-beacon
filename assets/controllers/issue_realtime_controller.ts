import { Controller } from "@hotwired/stimulus";

type RealtimeConfig = {
  mercure: {
    enabled: boolean;
    hubUrl: string | null;
    token: string | null;
    topics: string[];
  };
  push: {
    preferenceEnabled: boolean;
    vapidPublicKey: string | null;
    configured: boolean;
  };
};

/**
 * Subscribes to Mercure issue topics (foreground toasts) and manages Web Push
 * when the user opted in under Account → Display.
 */
export default class extends Controller {
  static values = {
    configUrl: String,
    subscribeUrl: String,
    unsubscribeUrl: String,
    csrfToken: String,
    enabled: Boolean,
  };

  private eventSource: EventSource | null = null;
  private refreshTimer: number | null = null;

  connect(): void {
    if (!this.enabledValue) {
      return;
    }
    void this.bootstrap();
    this.refreshTimer = window.setInterval(() => void this.bootstrap(), 50 * 60 * 1000);
  }

  disconnect(): void {
    this.closeEventSource();
    if (this.refreshTimer !== null) {
      window.clearInterval(this.refreshTimer);
      this.refreshTimer = null;
    }
  }

  private bootstrap = async (): Promise<void> => {
    try {
      const response = await fetch(this.configUrlValue, {
        headers: { Accept: "application/json" },
        credentials: "same-origin",
      });
      if (!response.ok) {
        return;
      }
      const config = (await response.json()) as RealtimeConfig;
      this.connectMercure(config);
      await this.syncPushSubscription(config);
    } catch {
      // Network blips are ignored; interval refresh will retry.
    }
  };

  private connectMercure(config: RealtimeConfig): void {
    if (!config.mercure.enabled) {
      this.closeEventSource();
      return;
    }

    const { hubUrl, token, topics } = config.mercure;
    if (!hubUrl || !token || topics.length === 0) {
      this.closeEventSource();
      return;
    }

    const url = new URL(hubUrl, window.location.origin);
    for (const topic of topics) {
      url.searchParams.append("topic", topic);
    }
    url.searchParams.set("authorization", token);

    this.closeEventSource();
    this.eventSource = new EventSource(url.toString(), { withCredentials: true });
    this.eventSource.onmessage = (event) => {
      try {
        const data = JSON.parse(event.data) as {
          summary?: string;
          url?: string;
          project?: { name?: string };
        };
        this.showToast(data.summary ?? "New issue", data.url);
      } catch {
        this.showToast("New issue");
      }
    };
  }

  private async syncPushSubscription(config: RealtimeConfig): Promise<void> {
    if (!("serviceWorker" in navigator) || !("PushManager" in window) || !("Notification" in window)) {
      return;
    }
    if (!config.push.preferenceEnabled || !config.push.configured || !config.push.vapidPublicKey) {
      return;
    }

    if (Notification.permission === "denied") {
      return;
    }
    if (Notification.permission === "default") {
      const permission = await Notification.requestPermission();
      if (permission !== "granted") {
        return;
      }
    }

    const registration = await navigator.serviceWorker.ready;
    let subscription = await registration.pushManager.getSubscription();
    if (!subscription) {
      subscription = await registration.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: this.urlBase64ToUint8Array(config.push.vapidPublicKey),
      });
    }

    await fetch(this.subscribeUrlValue, {
      method: "POST",
      credentials: "same-origin",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
        "X-CSRF-TOKEN": this.csrfTokenValue,
      },
      body: JSON.stringify(subscription.toJSON()),
    });
  }

  /** Enable push from Account → Display (button / after save). */
  async enablePush(): Promise<void> {
    await this.bootstrap();
  }

  /** Drop browser + server subscription when the user opts out. */
  async disablePush(): Promise<void> {
    if (!("serviceWorker" in navigator) || !("PushManager" in window)) {
      return;
    }
    try {
      const registration = await navigator.serviceWorker.ready;
      const subscription = await registration.pushManager.getSubscription();
      const endpoint = subscription?.endpoint ?? null;
      if (subscription) {
        await subscription.unsubscribe();
      }
      await fetch(this.unsubscribeUrlValue, {
        method: "POST",
        credentials: "same-origin",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
          "X-CSRF-TOKEN": this.csrfTokenValue,
        },
        body: JSON.stringify(endpoint ? { endpoint } : {}),
      });
    } catch {
      // Ignore unsubscribe errors.
    }
  }

  private closeEventSource(): void {
    if (this.eventSource) {
      this.eventSource.close();
      this.eventSource = null;
    }
  }

  private showToast(message: string, url?: string): void {
    let stack = document.querySelector<HTMLElement>(".toast-stack[data-controller~='toast-stack']");
    if (!stack) {
      stack = document.createElement("div");
      stack.className = "toast-stack";
      stack.setAttribute("data-controller", "toast-stack");
      stack.setAttribute("aria-live", "polite");
      document.body.appendChild(stack);
    }

    const toast = document.createElement("div");
    toast.className = "flash flash-toast flash-info";
    toast.setAttribute("data-toast-stack-target", "toast");
    toast.dataset.timeout = "8000";
    toast.setAttribute("role", "status");

    const text = document.createElement(url ? "a" : "span");
    text.textContent = message;
    if (url && text instanceof HTMLAnchorElement) {
      text.href = url;
      text.className = "flash-toast__link";
    }
    toast.appendChild(text);

    const dismiss = document.createElement("button");
    dismiss.type = "button";
    dismiss.className = "flash-toast__dismiss";
    dismiss.setAttribute("data-action", "toast-stack#dismiss");
    dismiss.setAttribute("aria-label", "Dismiss");
    dismiss.textContent = "×";
    toast.appendChild(dismiss);

    stack.appendChild(toast);
  }

  private urlBase64ToUint8Array(base64String: string): Uint8Array {
    const padding = "=".repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, "+").replace(/_/g, "/");
    const raw = window.atob(base64);
    const output = new Uint8Array(raw.length);
    for (let i = 0; i < raw.length; i += 1) {
      output[i] = raw.charCodeAt(i);
    }
    return output;
  }
}
