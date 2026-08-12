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

type RealtimeAlertPayload = {
  event?: string;
  summary?: string;
  url?: string;
  project?: { name?: string };
  issue?: { id?: number; title?: string; culprit?: string; level?: string };
};

type ToastLabels = {
  viewIssue: string;
  fallbackTitle: string;
  events: Record<string, string>;
};

const DEFAULT_EVENT_LABELS: Record<string, string> = {
  "issue.new": "New issue",
  "issue.regression": "Issue regression",
  "issue.resolved": "Issue resolved",
  "issue.reopened": "Issue reopened",
  "issue.assigned": "Issue assigned",
  "issue.commented": "New comment",
};

const ISSUE_PREVIEW_MAX = 110;

/**
 * Subscribes to Mercure member-alert topics (foreground toasts) and manages Web Push
 * when the user opted in under Account → Display → Notifications.
 */
export default class extends Controller {
  static values = {
    configUrl: String,
    subscribeUrl: String,
    unsubscribeUrl: String,
    csrfToken: String,
    enabled: Boolean,
    labels: Object,
  };

  declare labelsValue: Partial<ToastLabels>;

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
    // Absolute http(s) only — relative/ciphertext hubs would hit the Symfony app as a document/event-stream.
    if (!/^https?:\/\//i.test(hubUrl)) {
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
        const data = JSON.parse(event.data) as RealtimeAlertPayload;
        this.showToast(data);
      } catch {
        this.showToast({});
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

  private showToast(payload: RealtimeAlertPayload): void {
    let stack = document.querySelector<HTMLElement>(
      ".nowo-ui-toast-stack[data-controller~='toast-stack'], .toast-stack[data-controller~='toast-stack']",
    );
    if (!stack) {
      stack = document.createElement("div");
      stack.className = "nowo-ui-toast-stack toast-stack";
      stack.setAttribute("data-nowo-ui-toast-stack", "");
      stack.setAttribute("data-controller", "toast-stack");
      stack.setAttribute("aria-live", "polite");
      document.body.appendChild(stack);
    }

    const { title, message, url, linkLabel } = this.formatToast(payload);

    const toast = document.createElement("div");
    toast.className = "nowo-ui-toast flash flash-toast flash-info";
    toast.setAttribute("data-nowo-ui-toast", "");
    toast.setAttribute("data-toast-stack-target", "toast");
    toast.dataset.timeout = "10000";
    toast.setAttribute("role", "status");

    const content = document.createElement("div");
    content.className = "nowo-ui-toast__content flash__content";

    const titleEl = document.createElement("p");
    titleEl.className = "nowo-ui-toast__title flash__title";
    titleEl.textContent = title;
    content.appendChild(titleEl);

    if (message) {
      const messageEl = document.createElement("p");
      messageEl.className = "nowo-ui-toast__message flash__message";
      messageEl.textContent = message;
      content.appendChild(messageEl);
    }

    if (url) {
      const link = document.createElement("a");
      link.className = "nowo-ui-toast__action flash-toast__link";
      link.href = url;
      link.textContent = linkLabel;
      content.appendChild(link);
    }

    toast.appendChild(content);

    const dismiss = document.createElement("button");
    dismiss.type = "button";
    dismiss.className = "nowo-ui-toast__dismiss flash__dismiss";
    dismiss.setAttribute("data-nowo-ui-toast-dismiss", "");
    dismiss.setAttribute("data-action", "toast-stack#dismiss");
    dismiss.setAttribute("aria-label", "Dismiss");
    dismiss.innerHTML = '<span aria-hidden="true">&times;</span>';
    toast.appendChild(dismiss);

    stack.appendChild(toast);
  }

  private formatToast(payload: RealtimeAlertPayload): {
    title: string;
    message: string;
    url?: string;
    linkLabel: string;
  } {
    const labels = this.resolveLabels();
    const eventKey = typeof payload.event === "string" ? payload.event : "";
    const title =
      (eventKey && (labels.events[eventKey] || DEFAULT_EVENT_LABELS[eventKey])) ||
      labels.fallbackTitle;

    const preview = this.formatIssuePreview(payload.issue?.title, payload.issue?.culprit);
    const projectName = payload.project?.name?.trim() ?? "";
    let message = "";
    if (projectName && preview) {
      message = `${projectName} · ${preview}`;
    } else if (preview) {
      message = preview;
    } else if (projectName) {
      message = projectName;
    } else if (payload.summary && !eventKey) {
      // Legacy / unknown payloads: avoid dumping a raw exception as the title alone.
      message = this.truncate(payload.summary, ISSUE_PREVIEW_MAX);
    }

    return {
      title,
      message,
      url: this.safeToastUrl(payload.url),
      linkLabel: labels.viewIssue,
    };
  }

  /**
   * Only same-origin absolute URLs or root-relative paths (defense in depth vs
   * javascript:/external links if a publisher JWT were compromised).
   */
  private safeToastUrl(raw: unknown): string | undefined {
    if (typeof raw !== "string" || raw === "") {
      return undefined;
    }
    const value = raw.trim();
    if (value.startsWith("/") && !value.startsWith("//")) {
      return value;
    }
    try {
      const parsed = new URL(value, window.location.origin);
      if (parsed.origin === window.location.origin && /^https?:$/i.test(parsed.protocol)) {
        return parsed.pathname + parsed.search + parsed.hash;
      }
    } catch {
      // ignore invalid URLs
    }
    return undefined;
  }

  private resolveLabels(): ToastLabels {
    const raw = this.labelsValue ?? {};
    return {
      viewIssue: typeof raw.viewIssue === "string" && raw.viewIssue !== "" ? raw.viewIssue : "View issue",
      fallbackTitle:
        typeof raw.fallbackTitle === "string" && raw.fallbackTitle !== ""
          ? raw.fallbackTitle
          : "New alert",
      events: raw.events && typeof raw.events === "object" ? { ...DEFAULT_EVENT_LABELS, ...raw.events } : { ...DEFAULT_EVENT_LABELS },
    };
  }

  /** Short, human-readable issue blurb — strip FQCN noise and truncate. */
  private formatIssuePreview(title?: string, culprit?: string): string {
    let text = (title ?? "").trim() || (culprit ?? "").trim();
    if (!text) {
      return "";
    }
    text = (text.split(/\r?\n/)[0] ?? text).trim();
    // Symfony\Component\Foo\BarException: msg → BarException: msg
    text = text.replace(/^((?:[A-Za-z_][\w$]*\\)+)([A-Za-z_][\w$]*)\b/, "$2");
    return this.truncate(text, ISSUE_PREVIEW_MAX);
  }

  private truncate(value: string, max: number): string {
    if (value.length <= max) {
      return value;
    }
    return `${value.slice(0, max - 1).trimEnd()}…`;
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
