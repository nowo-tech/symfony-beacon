import { Controller } from "@hotwired/stimulus";

/**
 * Copies a string to the clipboard and briefly confirms success on the button.
 */
export default class extends Controller {
  static values = {
    text: String,
    label: { type: String, default: "Copy" },
    doneLabel: { type: String, default: "Copied" },
  };

  declare readonly textValue: string;
  declare readonly labelValue: string;
  declare readonly doneLabelValue: string;

  private resetTimer: number | null = null;

  async copy(event: Event): Promise<void> {
    event.preventDefault();
    event.stopPropagation();

    const value = this.textValue.trim();
    if (value === "") {
      return;
    }

    try {
      if (navigator.clipboard?.writeText) {
        await navigator.clipboard.writeText(value);
      } else {
        this.fallbackCopy(value);
      }
      this.flashDone(event.currentTarget);
    } catch {
      try {
        this.fallbackCopy(value);
        this.flashDone(event.currentTarget);
      } catch {
        // Ignore clipboard failures (permissions / insecure context).
      }
    }
  }

  private flashDone(target: EventTarget | null): void {
    if (!(target instanceof HTMLElement)) {
      return;
    }

    target.textContent = this.doneLabelValue;
    target.setAttribute("aria-label", this.doneLabelValue);

    if (this.resetTimer !== null) {
      window.clearTimeout(this.resetTimer);
    }

    this.resetTimer = window.setTimeout(() => {
      target.textContent = this.labelValue;
      target.setAttribute("aria-label", this.labelValue);
      this.resetTimer = null;
    }, 1600);
  }

  private fallbackCopy(value: string): void {
    const area = document.createElement("textarea");
    area.value = value;
    area.setAttribute("readonly", "");
    area.style.position = "fixed";
    area.style.left = "-9999px";
    document.body.appendChild(area);
    area.select();
    document.execCommand("copy");
    area.remove();
  }

  disconnect(): void {
    if (this.resetTimer !== null) {
      window.clearTimeout(this.resetTimer);
      this.resetTimer = null;
    }
  }
}
