import { Controller } from "@hotwired/stimulus";
import { driver, type Config, type DriveStep, type Driver } from "driver.js";
import "driver.js/dist/driver.css";

type TourPopover = {
  title: string;
  description: string;
  side?: string;
  align?: string;
};

type TourStep = {
  element?: string;
  popover: TourPopover;
};

type TourLabels = {
  next?: string;
  previous?: string;
  done?: string;
  close?: string;
  progress?: string;
};

/**
 * Contextual product tour (driver.js). Auto-starts once per page after setup;
 * finishing or closing persists “seen” so it never returns unless Preferences → Replay.
 */
export default class extends Controller {
  static values = {
    autoStart: Boolean,
    force: Boolean,
    page: String,
    markUrl: String,
    markToken: String,
    steps: Array,
    labels: Object,
  };

  declare readonly autoStartValue: boolean;
  declare readonly forceValue: boolean;
  declare readonly pageValue: string;
  declare readonly markUrlValue: string;
  declare readonly markTokenValue: string;
  declare readonly stepsValue: TourStep[];
  declare readonly labelsValue: TourLabels;

  private activeDriver: Driver | null = null;
  private marked = false;
  private started = false;

  connect(): void {
    if (!this.autoStartValue && !this.forceValue) {
      return;
    }

    window.requestAnimationFrame(() => this.start());
  }

  disconnect(): void {
    // Destroy without relying on markSeen here — onDestroyed already persisted if the user finished/closed.
    const active = this.activeDriver;
    this.activeDriver = null;
    if (active) {
      active.destroy();
    }
  }

  private start(): void {
    const steps = this.resolveSteps();
    if (0 === steps.length) {
      // Nothing to show (anchors missing) — still mark seen so we do not loop forever.
      void this.persistSeen();
      this.clearForceQuery();

      return;
    }

    const labels = this.labelsValue ?? {};
    this.started = true;

    const config: Config = {
      showProgress: true,
      animate: true,
      allowClose: true,
      overlayColor: "rgb(28 25 23)",
      overlayOpacity: 0.55,
      stagePadding: 8,
      stageRadius: 8,
      popoverClass: "beacon-driver-popover",
      nextBtnText: labels.next ?? "Next",
      prevBtnText: labels.previous ?? "Previous",
      doneBtnText: labels.done ?? "Done",
      progressText: labels.progress ?? "{{current}} of {{total}}",
      steps,
      onHighlightStarted: (element) => {
        this.ensureUserMenuOpen(element ?? undefined);
      },
      onDestroyStarted: (_element, _step, { driver: active }) => {
        // Persist before tear-down so a reload cannot re-open the tour.
        void this.persistSeen();
        this.clearForceQuery();
        if (active.isActive()) {
          active.destroy();
        }
      },
      onDestroyed: () => {
        void this.persistSeen();
        this.clearForceQuery();
        this.activeDriver = null;
      },
    };

    this.activeDriver = driver(config);
    this.activeDriver.drive();
  }

  private resolveSteps(): DriveStep[] {
    const raw = Array.isArray(this.stepsValue) ? this.stepsValue : [];
    const resolved: DriveStep[] = [];

    for (const step of raw) {
      if (!step?.popover) {
        continue;
      }

      const popover: DriveStep["popover"] = {
        title: step.popover.title,
        description: step.popover.description,
        side: this.asSide(step.popover.side),
        align: this.asAlign(step.popover.align),
      };

      if (step.element) {
        if (!(document.querySelector(step.element) instanceof Element)) {
          continue;
        }
        resolved.push({ element: step.element, popover });
        continue;
      }

      resolved.push({ popover });
    }

    return resolved;
  }

  private asSide(value: string | undefined): "top" | "right" | "bottom" | "left" {
    if (value === "top" || value === "right" || value === "bottom" || value === "left") {
      return value;
    }

    return "bottom";
  }

  private asAlign(value: string | undefined): "start" | "center" | "end" {
    if (value === "start" || value === "center" || value === "end") {
      return value;
    }

    return "start";
  }

  private ensureUserMenuOpen(element?: Element): void {
    const menuRoot = document.querySelector<HTMLElement>('[data-tour="user-menu"]');
    if (!menuRoot) {
      return;
    }

    const needsOpen =
      element?.closest('[data-tour="user-menu"]') !== null ||
      element?.matches('[data-tour="admin-link"]') === true ||
      element?.matches('[data-tour="user-menu"]') === true;

    if (!needsOpen) {
      return;
    }

    const details = menuRoot.querySelector("details");
    if (details) {
      details.open = true;
    }
  }

  /** Drop ?tour=1 so a refresh cannot force the tour again after completion. */
  private clearForceQuery(): void {
    if (!this.forceValue) {
      return;
    }

    try {
      const url = new URL(window.location.href);
      if (!url.searchParams.has("tour")) {
        return;
      }
      url.searchParams.delete("tour");
      const next = `${url.pathname}${url.search}${url.hash}`;
      window.history.replaceState({}, "", next);
    } catch {
      // ignore
    }
  }

  private async persistSeen(): Promise<void> {
    if (this.marked || !this.markUrlValue || !this.markTokenValue) {
      return;
    }
    this.marked = true;

    try {
      await fetch(this.markUrlValue, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
          "X-CSRF-TOKEN": this.markTokenValue,
        },
        body: JSON.stringify({
          seen: true,
          page: this.pageValue || undefined,
        }),
        credentials: "same-origin",
        keepalive: true,
      });
    } catch {
      // Allow a single retry on the next destroy hook if the request failed.
      this.marked = false;
    }
  }
}
