import { Controller } from '@hotwired/stimulus';

/**
 * CSP-safe country prefix picker for nowo-tech/phone-input-bundle.
 *
 * Upstream ships an inline <script> per field (blocked when script-src has a nonce).
 * Host widget adds data-controller="phone-prefix-picker" instead.
 *
 * Dropdown is portaled to document.body with position:fixed so it is not clipped by
 * dialog[data-slot="dialog-body"] { overflow-y: auto }.
 */
export default class extends Controller {
  private highlightedOption: HTMLElement | null = null;
  private placeholder: Comment | null = null;
  private open = false;

  private readonly onDocumentPointerDown = (event: PointerEvent): void => {
    const target = event.target;
    if (!(target instanceof Node)) {
      return;
    }
    if (this.element.contains(target) || this.dropdown?.contains(target)) {
      return;
    }
    this.closeMenu();
  };

  private readonly onReposition = (): void => {
    if (this.open) {
      this.positionDropdown();
    }
  };

  connect(): void {
    const select = this.select;
    const toggle = this.toggle;
    const dropdown = this.dropdown;
    const menu = this.menu;
    if (!select || !toggle || !dropdown || !menu) {
      return;
    }

    select.classList.add('nowo-phone-input__prefix-select--enhanced');
  }

  disconnect(): void {
    this.closeMenu();
  }

  toggleMenu(event: Event): void {
    event.preventDefault();
    event.stopPropagation();
    if (this.open) {
      this.closeMenu();
    } else {
      this.openMenu();
    }
  }

  onSearchInput(): void {
    this.filterOptions(this.searchInput?.value ?? '');
  }

  onSearchKeydown(event: KeyboardEvent): void {
    if (event.key === 'ArrowDown') {
      event.preventDefault();
      this.moveHighlight(1);
    } else if (event.key === 'ArrowUp') {
      event.preventDefault();
      this.moveHighlight(-1);
    } else if (event.key === 'Enter') {
      event.preventDefault();
      this.selectHighlighted();
    } else if (event.key === 'Escape') {
      event.preventDefault();
      this.closeMenu();
      this.toggle?.focus();
    }
  }

  onToggleKeydown(event: KeyboardEvent): void {
    const prefixSearchEnabled = this.element.dataset.prefixSearch !== '0';
    if (event.key === 'ArrowDown' || event.key === 'Enter' || event.key === ' ') {
      event.preventDefault();
      if (!this.open) {
        this.openMenu();
      } else if (!prefixSearchEnabled && event.key === 'ArrowDown') {
        this.moveHighlight(1);
      }
    } else if (event.key === 'ArrowUp' && this.open && !prefixSearchEnabled) {
      event.preventDefault();
      this.moveHighlight(-1);
    } else if (event.key === 'Escape') {
      this.closeMenu();
    }
  }

  onOptionClick(event: Event): void {
    event.preventDefault();
    event.stopPropagation();
    const option = (event.currentTarget as HTMLElement | null) ?? null;
    if (!option) {
      return;
    }
    this.applySelection(option);
    this.closeMenu();
  }

  onOptionMouseEnter(event: Event): void {
    const option = (event.currentTarget as HTMLElement | null) ?? null;
    if (option && !option.hidden) {
      this.setHighlighted(option);
    }
  }

  private get select(): HTMLSelectElement | null {
    return this.element.querySelector('.nowo-phone-input__prefix-select');
  }

  private get toggle(): HTMLButtonElement | null {
    return this.element.querySelector('.nowo-phone-input__prefix-toggle');
  }

  private get dropdown(): HTMLElement | null {
    if (this.portaledDropdown) {
      return this.portaledDropdown;
    }

    return this.element.querySelector('.nowo-phone-input__prefix-dropdown');
  }

  private portaledDropdown: HTMLElement | null = null;

  private get searchInput(): HTMLInputElement | null {
    return this.dropdown?.querySelector('.nowo-phone-input__prefix-search-input') ?? null;
  }

  private get menu(): HTMLElement | null {
    return this.dropdown?.querySelector('.nowo-phone-input__prefix-menu')
      ?? this.element.querySelector('.nowo-phone-input__prefix-menu');
  }

  private get emptyState(): HTMLElement | null {
    return this.dropdown?.querySelector('.nowo-phone-input__prefix-empty')
      ?? this.element.querySelector('.nowo-phone-input__prefix-empty');
  }

  private get options(): NodeListOf<HTMLElement> {
    const root = this.dropdown ?? this.element;
    return root.querySelectorAll('.nowo-phone-input__prefix-option');
  }

  private get flagDisplay(): string {
    return this.element.dataset.flagDisplay || 'CSS_ICON';
  }

  private get prefixDisplay(): string {
    return this.element.dataset.prefixDisplay || 'FLAG_AND_PREFIX';
  }

  private renderFlagHtml(iso: string, emoji: string): string {
    if (this.flagDisplay === 'NONE') {
      return '';
    }
    if (this.flagDisplay === 'CSS_ICON' || this.flagDisplay === 'UX_ICON') {
      return `<span class="nowo-phone-input__flag fi fi-${iso.toLowerCase()}" aria-hidden="true"></span>`;
    }

    return `<span class="nowo-phone-input__flag nowo-phone-input__flag--emoji" aria-hidden="true">${emoji}</span>`;
  }

  private normalizeQuery(query: string): string {
    return query.trim().toLowerCase().replace(/\+/g, '');
  }

  private optionMatches(option: HTMLElement, query: string): boolean {
    if (!query) {
      return true;
    }
    const haystack = (option.dataset.search || '').toLowerCase().replace(/\+/g, '');
    const tokens = this.normalizeQuery(query).split(/\s+/).filter(Boolean);

    return tokens.every((token) => haystack.includes(token));
  }

  private visibleOptions(): HTMLElement[] {
    return Array.from(this.options).filter((option) => !option.hidden);
  }

  private setHighlighted(option: HTMLElement | null): void {
    this.highlightedOption?.classList.remove('is-highlighted');
    this.highlightedOption = option;
    if (this.highlightedOption) {
      this.highlightedOption.classList.add('is-highlighted');
      this.highlightedOption.scrollIntoView({ block: 'nearest' });
    }
  }

  private filterOptions(query: string): void {
    let visibleCount = 0;
    this.options.forEach((option) => {
      const matches = this.optionMatches(option, query);
      option.hidden = !matches;
      if (matches) {
        visibleCount += 1;
      } else {
        option.classList.remove('is-highlighted');
      }
    });

    if (this.emptyState) {
      this.emptyState.hidden = visibleCount > 0;
    }
    if (this.menu) {
      this.menu.hidden = visibleCount === 0;
    }
    this.setHighlighted(this.visibleOptions()[0] ?? null);
  }

  private resetFilter(): void {
    if (this.searchInput) {
      this.searchInput.value = '';
    }
    this.filterOptions('');
  }

  private openMenu(): void {
    const dropdown = this.element.querySelector('.nowo-phone-input__prefix-dropdown');
    const toggle = this.toggle;
    if (!(dropdown instanceof HTMLElement) || !toggle) {
      return;
    }

    if (!this.placeholder) {
      this.placeholder = document.createComment('phone-prefix-picker-dropdown');
      dropdown.before(this.placeholder);
    }

    this.portaledDropdown = dropdown;
    dropdown.classList.add('nowo-phone-input__prefix-dropdown--portaled');
    document.body.appendChild(dropdown);
    dropdown.hidden = false;
    toggle.setAttribute('aria-expanded', 'true');
    this.open = true;
    this.resetFilter();
    this.positionDropdown();

    document.addEventListener('pointerdown', this.onDocumentPointerDown, true);
    window.addEventListener('resize', this.onReposition);
    // Capture scroll in dialog-body / ancestors so the menu stays aligned.
    document.addEventListener('scroll', this.onReposition, true);

    requestAnimationFrame(() => {
      this.searchInput?.focus();
    });
  }

  private closeMenu(): void {
    const dropdown = this.portaledDropdown;
    const toggle = this.toggle;

    document.removeEventListener('pointerdown', this.onDocumentPointerDown, true);
    window.removeEventListener('resize', this.onReposition);
    document.removeEventListener('scroll', this.onReposition, true);

    if (dropdown instanceof HTMLElement) {
      dropdown.hidden = true;
      dropdown.classList.remove('nowo-phone-input__prefix-dropdown--portaled');
      dropdown.style.removeProperty('position');
      dropdown.style.removeProperty('top');
      dropdown.style.removeProperty('left');
      dropdown.style.removeProperty('width');
      dropdown.style.removeProperty('min-width');
      dropdown.style.removeProperty('max-height');
      dropdown.style.removeProperty('overflow');
      dropdown.style.removeProperty('z-index');
      if (this.placeholder?.parentNode) {
        this.placeholder.parentNode.insertBefore(dropdown, this.placeholder);
        this.placeholder.remove();
      }
      this.placeholder = null;
    }

    this.portaledDropdown = null;
    this.open = false;
    toggle?.setAttribute('aria-expanded', 'false');
    this.resetFilter();
    this.setHighlighted(null);
  }

  private positionDropdown(): void {
    const dropdown = this.portaledDropdown;
    const toggle = this.toggle;
    if (!dropdown || !toggle) {
      return;
    }

    const rect = toggle.getBoundingClientRect();
    const viewportPadding = 8;
    const gap = 4;
    const minWidth = Math.max(rect.width, 16 * 16);
    const maxWidth = Math.min(20 * 16, window.innerWidth - viewportPadding * 2);
    const width = Math.min(Math.max(minWidth, rect.width), maxWidth);

    let left = rect.left;
    if (left + width > window.innerWidth - viewportPadding) {
      left = Math.max(viewportPadding, window.innerWidth - viewportPadding - width);
    }
    if (left < viewportPadding) {
      left = viewportPadding;
    }

    const spaceBelow = window.innerHeight - rect.bottom - viewportPadding;
    const spaceAbove = rect.top - viewportPadding;
    const preferBelow = spaceBelow >= 12 * 16 || spaceBelow >= spaceAbove;
    const maxHeight = Math.max(8 * 16, Math.min(18 * 16, preferBelow ? spaceBelow - gap : spaceAbove - gap));

    dropdown.style.position = 'fixed';
    dropdown.style.zIndex = '200';
    dropdown.style.width = `${width}px`;
    dropdown.style.minWidth = `${width}px`;
    dropdown.style.left = `${Math.round(left)}px`;
    dropdown.style.maxHeight = `${Math.round(maxHeight)}px`;
    dropdown.style.overflow = 'auto';

    if (preferBelow) {
      dropdown.style.top = `${Math.round(rect.bottom + gap)}px`;
    } else {
      // Place above: set top after measuring height when possible.
      const height = Math.min(dropdown.offsetHeight || maxHeight, maxHeight);
      dropdown.style.top = `${Math.round(rect.top - gap - height)}px`;
    }
  }

  private applySelection(option: HTMLElement): void {
    const select = this.select;
    if (!select) {
      return;
    }

    const iso = option.dataset.iso ?? '';
    const prefix = option.dataset.prefix ?? '';
    const emoji = option.dataset.flag || '';

    select.value = iso;
    select.dispatchEvent(new Event('change', { bubbles: true }));

    const toggleFlag = this.element.querySelector('.nowo-phone-input__prefix-toggle-flag');
    const toggleCode = this.element.querySelector('.nowo-phone-input__prefix-toggle-code');
    if (toggleFlag && this.flagDisplay !== 'NONE') {
      toggleFlag.innerHTML = this.renderFlagHtml(iso, emoji);
    }
    if (toggleCode && this.prefixDisplay !== 'FLAG_ONLY') {
      toggleCode.textContent = prefix;
    }

    this.options.forEach((item) => {
      const selected = item.dataset.iso === iso;
      item.classList.toggle('is-selected', selected);
      item.setAttribute('aria-selected', selected ? 'true' : 'false');
    });
  }

  private selectHighlighted(): void {
    if (this.highlightedOption && !this.highlightedOption.hidden) {
      this.applySelection(this.highlightedOption);
      this.closeMenu();
    }
  }

  private moveHighlight(direction: number): void {
    const visible = this.visibleOptions();
    if (visible.length === 0) {
      this.setHighlighted(null);
      return;
    }
    let index = this.highlightedOption ? visible.indexOf(this.highlightedOption) : -1;
    index = (index + direction + visible.length) % visible.length;
    this.setHighlighted(visible[index] ?? null);
  }
}
