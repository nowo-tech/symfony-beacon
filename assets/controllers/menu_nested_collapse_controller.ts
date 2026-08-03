import { Controller } from "@hotwired/stimulus";

type CollapseStateMap = Record<string, boolean>;
type MenuCollapseStore = Record<string, CollapseStateMap>;

const STORAGE_KEY = "beacon.navCollapse";

/**
 * Polyfill Bootstrap-style nested menu collapse used by dashboard-menu-bundle
 * (data-bs-toggle="collapse") without loading Bootstrap JS in the product shell.
 *
 * - Persists expand/collapse per menu code across navigations (localStorage).
 * - Always expands ancestors marked `is-branch-current` (active sub-route).
 * - Height animation is CSS (`grid-template-rows` 0fr→1fr on `.collapse`).
 */
export default class extends Controller {
  static values = {
    menuCode: { type: String, default: "menu" },
  };

  declare readonly menuCodeValue: string;

  connect(): void {
    this.element.classList.remove("is-nav-collapse-ready");
    const saved = this.readMenuState();

    this.element.querySelectorAll<HTMLElement>(".collapse").forEach((panel) => {
      const item = panel.closest("li");
      const branchCurrent = item?.classList.contains("is-branch-current") ?? false;
      const savedOpen = typeof saved[panel.id] === "boolean" ? saved[panel.id] : undefined;
      // Active branch always open; otherwise restore preference (default: expanded as in Twig).
      const open = branchCurrent || (savedOpen ?? panel.classList.contains("show"));
      this.applyOpen(panel, open);
    });

    // Two frames: let the browser paint the initial 0fr/1fr state first.
    window.requestAnimationFrame(() => {
      window.requestAnimationFrame(() => {
        this.element.classList.add("is-nav-collapse-ready");
      });
    });
  }

  toggle(event: Event): void {
    const button = (event.target as HTMLElement | null)?.closest<HTMLButtonElement>(
      ".menu-nested-toggle",
    );
    if (!button || !this.element.contains(button)) {
      return;
    }

    event.preventDefault();
    const targetId = button.getAttribute("data-bs-target") ?? button.getAttribute("aria-controls");
    if (!targetId) {
      return;
    }

    const panel = this.element.querySelector<HTMLElement>(targetId);
    if (!panel) {
      return;
    }

    const nextOpen = !panel.classList.contains("show");
    this.applyOpen(panel, nextOpen);
    this.writePanelState(panel.id, nextOpen);
  }

  private applyOpen(panel: HTMLElement, open: boolean): void {
    panel.classList.toggle("show", open);
    panel.toggleAttribute("inert", !open);
    panel.setAttribute("aria-hidden", open ? "false" : "true");

    const item = panel.closest("li");
    item?.classList.toggle("is-expanded", open);
    item?.classList.toggle("is-collapsed", !open);

    const collapseId = panel.id;
    if (!collapseId) {
      return;
    }

    this.element
      .querySelectorAll<HTMLButtonElement>(
        `.menu-nested-toggle[data-bs-target="#${CSS.escape(collapseId)}"], .menu-nested-toggle[aria-controls="${CSS.escape(collapseId)}"]`,
      )
      .forEach((button) => {
        button.setAttribute("aria-expanded", open ? "true" : "false");
      });
  }

  private readMenuState(): CollapseStateMap {
    const all = this.readStore();
    const menu = all[this.menuCodeValue];
    return menu && typeof menu === "object" && !Array.isArray(menu) ? menu : {};
  }

  private writePanelState(panelId: string, open: boolean): void {
    if (!panelId) {
      return;
    }
    const all = this.readStore();
    const menu = { ...(all[this.menuCodeValue] ?? {}) };
    menu[panelId] = open;
    all[this.menuCodeValue] = menu;
    this.writeStore(all);
  }

  private readStore(): MenuCollapseStore {
    try {
      const raw = window.localStorage.getItem(STORAGE_KEY);
      if (!raw) {
        return {};
      }
      const parsed = JSON.parse(raw) as unknown;
      if (!parsed || typeof parsed !== "object" || Array.isArray(parsed)) {
        return {};
      }

      return parsed as MenuCollapseStore;
    } catch {
      return {};
    }
  }

  private writeStore(store: MenuCollapseStore): void {
    try {
      window.localStorage.setItem(STORAGE_KEY, JSON.stringify(store));
    } catch {
      // Ignore quota / private mode failures.
    }
  }
}

/* stimulusFetch: 'lazy' */
