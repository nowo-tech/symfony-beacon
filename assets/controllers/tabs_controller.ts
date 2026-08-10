import { Controller } from '@hotwired/stimulus';

/**
 * Simple tab switcher. Toggles `hidden` on panels so it wins over `display: grid/flex`
 * (Tailwind `data-[state=inactive]:hidden` alone can lose to a sibling `grid` utility).
 */
export default class extends Controller {
    static targets = ['trigger', 'tab'];
    static values = { activeTab: String };

    declare triggerTargets: HTMLElement[];
    declare tabTargets: HTMLElement[];
    declare activeTabValue: string;

    open(e: Event): void {
        const currentTarget = e.currentTarget as HTMLElement | null;
        if (null === currentTarget) {
            return;
        }
        this.activeTabValue = currentTarget.dataset.tabId ?? '';
    }

    activeTabValueChanged(): void {
        this.triggerTargets.forEach((trigger) => {
            const isActive = trigger.dataset.tabId === this.activeTabValue;
            trigger.toggleAttribute('data-active', isActive);
            trigger.ariaSelected = isActive ? 'true' : 'false';
        });

        this.tabTargets.forEach((tab) => {
            const isActive = tab.dataset.tabId === this.activeTabValue;
            tab.toggleAttribute('data-active', isActive);
            tab.dataset.state = isActive ? 'active' : 'inactive';
            tab.classList.toggle('hidden', !isActive);
            tab.hidden = !isActive;
        });
    }
}
