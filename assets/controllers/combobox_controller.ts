import { Controller } from "@hotwired/stimulus";

/**
 * Client-side combobox: filter a list of options and write the selected value
 * into a hidden input (used for issue duplicate canonical picker).
 */
export default class extends Controller {
  static targets = ["query", "value", "list", "option", "empty"];

  static values = {
    open: { type: Boolean, default: false },
  };

  declare readonly queryTarget: HTMLInputElement;
  declare readonly valueTarget: HTMLInputElement;
  declare readonly listTarget: HTMLElement;
  declare readonly optionTargets: HTMLElement[];
  declare readonly hasEmptyTarget: boolean;
  declare readonly emptyTarget: HTMLElement;
  declare openValue: boolean;

  connect(): void {
    this.filter();
    this.syncListVisibility();
  }

  open(): void {
    this.openValue = true;
    this.syncListVisibility();
  }

  close(): void {
    this.openValue = false;
    this.syncListVisibility();
  }

  filter(): void {
    this.queryTarget.setCustomValidity("");
    const needle = this.queryTarget.value.trim().toLowerCase();
    let visible = 0;

    for (const option of this.optionTargets) {
      const haystack = (option.dataset.search ?? option.textContent ?? "").toLowerCase();
      const match = needle === "" || haystack.includes(needle);
      option.hidden = !match;
      if (match) {
        visible += 1;
      }
    }

    if (this.hasEmptyTarget) {
      this.emptyTarget.hidden = visible > 0;
    }

    if (needle !== "" && this.valueTarget.value !== "") {
      const selected = this.optionTargets.find((el) => el.dataset.value === this.valueTarget.value);
      const selectedLabel = selected?.dataset.label ?? "";
      if (selectedLabel.toLowerCase() !== needle) {
        this.valueTarget.value = "";
      }
    }
  }

  select(event: Event): void {
    event.preventDefault();
    const button = event.currentTarget;
    if (!(button instanceof HTMLElement)) {
      return;
    }
    const value = button.dataset.value ?? "";
    const label = button.dataset.label ?? button.textContent?.trim() ?? "";
    if (value === "") {
      return;
    }
    this.valueTarget.value = value;
    this.queryTarget.value = label;
    this.queryTarget.setCustomValidity("");
    for (const option of this.optionTargets) {
      const selected = option === button;
      option.classList.toggle("is-selected", selected);
      option.setAttribute("aria-selected", selected ? "true" : "false");
    }
    this.openValue = false;
    this.syncListVisibility();
    this.queryTarget.focus();
  }

  onQueryFocus(): void {
    this.openValue = true;
    this.filter();
    this.syncListVisibility();
  }

  onQueryKeydown(event: KeyboardEvent): void {
    if (event.key === "Escape") {
      this.close();
      return;
    }
    if (event.key === "Enter") {
      const firstVisible = this.optionTargets.find((el) => !el.hidden);
      if (firstVisible && this.valueTarget.value === "") {
        event.preventDefault();
        firstVisible.click();
      }
    }
  }

  requireValue(event: Event): void {
    if (this.valueTarget.value.trim() !== "") {
      return;
    }
    event.preventDefault();
    this.openValue = true;
    this.filter();
    this.syncListVisibility();
    this.queryTarget.setCustomValidity(
      this.queryTarget.getAttribute("data-required-message") ?? "Select an issue",
    );
    this.queryTarget.reportValidity();
    this.queryTarget.focus();
  }

  private syncListVisibility(): void {
    this.listTarget.hidden = !this.openValue;
  }
}
