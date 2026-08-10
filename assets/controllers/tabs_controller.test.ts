import { Application } from '@hotwired/stimulus';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import TabsController from './tabs_controller';

describe('tabs controller', () => {
  let application: Application;

  beforeEach(() => {
    document.body.innerHTML = `
      <div data-controller="tabs" data-tabs-active-tab-value="a">
        <button type="button" data-tabs-target="trigger" data-tab-id="a" data-action="tabs#open">A</button>
        <button type="button" data-tabs-target="trigger" data-tab-id="b" data-action="tabs#open">B</button>
        <div data-tabs-target="tab" data-tab-id="a"></div>
        <div data-tabs-target="tab" data-tab-id="b"></div>
      </div>
    `;
    application = Application.start();
    application.register('tabs', TabsController);
  });

  afterEach(() => {
    application.stop();
    document.body.innerHTML = '';
  });

  it('activates the selected tab', async () => {
    await Promise.resolve();
    const root = document.querySelector('[data-controller="tabs"]') as HTMLElement;
    const controller = application.getControllerForElementAndIdentifier(root, 'tabs') as TabsController & {
      activeTabValue: string;
    };
    const triggerB = document.querySelector(
      'button[data-tabs-target="trigger"][data-tab-id="b"]',
    ) as HTMLButtonElement;

    controller.activeTabValue = 'b';
    await Promise.resolve();

    expect(triggerB.hasAttribute('data-active')).toBe(true);
    expect(triggerB.ariaSelected).toBe('true');
    const tabA = document.querySelector('[data-tabs-target="tab"][data-tab-id="a"]') as HTMLElement;
    const tabB = document.querySelector('[data-tabs-target="tab"][data-tab-id="b"]') as HTMLElement;
    expect(tabB.dataset.state).toBe('active');
    expect(tabB.hidden).toBe(false);
    expect(tabB.classList.contains('hidden')).toBe(false);
    expect(tabA.hidden).toBe(true);
    expect(tabA.classList.contains('hidden')).toBe(true);
  });

  it('open action switches tab from trigger dataset', async () => {
    await Promise.resolve();
    const triggerB = document.querySelector(
      'button[data-tabs-target="trigger"][data-tab-id="b"]',
    ) as HTMLButtonElement;
    triggerB.dispatchEvent(new MouseEvent('click', { bubbles: true }));
    await Promise.resolve();
    expect(triggerB.hasAttribute('data-active')).toBe(true);
    const tabB = document.querySelector('[data-tabs-target="tab"][data-tab-id="b"]') as HTMLElement;
    expect(tabB.dataset.state).toBe('active');
    expect(tabB.hidden).toBe(false);
  });
});
