import { Controller } from "@hotwired/stimulus";

type PanelStateMap = Record<string, boolean>;

const STORAGE_KEY = "beacon.issuePanelState";

/**
 * Collapsible issue/event panel with browser persistence.
 *
 * Open state is stored in localStorage so it survives navigation between
 * issue and event views. When no saved state exists, preference defaults
 * from `window.__BEACON_ISSUE_PANEL_DEFAULTS__` (collapsed id list) apply.
 */
export default class extends Controller {
  static targets = ["body", "button", "icon"];

  static values = {
    id: String,
  };

  declare readonly bodyTarget: HTMLElement;
  declare readonly buttonTarget: HTMLButtonElement;
  declare readonly hasIconTarget: boolean;
  declare readonly iconTarget: HTMLElement;
  declare readonly idValue: string;

  connect(): void {
    this.apply(this.resolveOpen());
  }

  toggle(event?: Event): void {
    event?.preventDefault();
    this.apply(!this.isOpen());
  }

  private resolveOpen(): boolean {
    const saved = this.readState()[this.idValue];
    if (typeof saved === "boolean") {
      return saved;
    }

    const defaults = window.__BEACON_ISSUE_PANEL_DEFAULTS__;
    if (Array.isArray(defaults) && defaults.includes(this.idValue)) {
      return false;
    }

    return true;
  }

  private isOpen(): boolean {
    return !this.element.classList.contains("is-collapsed");
  }

  private apply(open: boolean): void {
    this.element.classList.toggle("is-collapsed", !open);
    this.buttonTarget.setAttribute("aria-expanded", open ? "true" : "false");
    this.bodyTarget.hidden = !open;
    if (this.hasIconTarget) {
      this.iconTarget.textContent = open ? "▾" : "▸";
    }

    const state = this.readState();
    state[this.idValue] = open;
    this.writeState(state);
  }

  private readState(): PanelStateMap {
    try {
      const raw = window.localStorage.getItem(STORAGE_KEY);
      if (!raw) {
        return {};
      }
      const parsed = JSON.parse(raw) as unknown;
      if (!parsed || typeof parsed !== "object" || Array.isArray(parsed)) {
        return {};
      }

      return parsed as PanelStateMap;
    } catch {
      return {};
    }
  }

  private writeState(state: PanelStateMap): void {
    try {
      window.localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
    } catch {
      // Ignore quota / private mode failures.
    }
  }
}

declare global {
  interface Window {
    __BEACON_ISSUE_PANEL_DEFAULTS__?: string[];
  }
}

/* stimulusFetch: 'lazy' */
