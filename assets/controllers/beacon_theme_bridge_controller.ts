import { BridgeComponent } from "@hotwired/hotwire-native-bridge";

/**
 * Syncs Beacon theme (light/dark) toward the Hotwire Native shell when available.
 */
export default class extends BridgeComponent {
  static component = "beacon-theme";

  private observer: MutationObserver | null = null;

  connect(): void {
    super.connect();
    this.sendTheme(document.documentElement.dataset.theme || "light");

    this.observer = new MutationObserver(() => {
      this.sendTheme(document.documentElement.dataset.theme || "light");
    });
    this.observer.observe(document.documentElement, {
      attributes: true,
      attributeFilter: ["data-theme"],
    });
  }

  disconnect(): void {
    this.observer?.disconnect();
    this.observer = null;
    super.disconnect();
  }

  sendTheme(theme: string): void {
    this.send("theme", { theme });
  }
}
