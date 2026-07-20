import { Controller } from "@hotwired/stimulus";

/**
 * Fills an API key label input with a human-friendly adjective-noun name.
 */
export default class extends Controller {
  static targets = ["label"];

  static values = {
    adjectives: Array,
    nouns: Array,
  };

  declare readonly labelTarget: HTMLInputElement;
  declare readonly adjectivesValue: string[];
  declare readonly nounsValue: string[];

  generate(event: Event): void {
    event.preventDefault();
    const adjectives = this.adjectivesValue;
    const nouns = this.nounsValue;
    if (adjectives.length === 0 || nouns.length === 0) {
      return;
    }

    const adjective = adjectives[Math.floor(Math.random() * adjectives.length)] ?? "calm";
    const noun = nouns[Math.floor(Math.random() * nouns.length)] ?? "beacon";
    this.labelTarget.value = `${adjective}-${noun}`;
    this.labelTarget.dispatchEvent(new Event("input", { bubbles: true }));
    this.labelTarget.focus();
  }
}
