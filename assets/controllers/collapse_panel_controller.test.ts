import { Application } from '@hotwired/stimulus';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import CollapsePanelController from './collapse_panel_controller';

describe('collapse-panel controller', () => {
  let application: Application;

  beforeEach(() => {
    localStorage.clear();
    window.__BEACON_ISSUE_PANEL_DEFAULTS__ = ['raw'];
    document.body.innerHTML = `
      <div data-controller="collapse-panel" data-collapse-panel-id-value="raw">
        <button type="button" data-collapse-panel-target="button" aria-expanded="true"></button>
        <span data-collapse-panel-target="icon"></span>
        <div data-collapse-panel-target="body">content</div>
      </div>
    `;
    application = Application.start();
    application.register('collapse-panel', CollapsePanelController);
  });

  afterEach(() => {
    application.stop();
    document.body.innerHTML = '';
    localStorage.clear();
    delete window.__BEACON_ISSUE_PANEL_DEFAULTS__;
  });

  it('starts collapsed when id is in defaults', async () => {
    await Promise.resolve();
    const root = document.querySelector('[data-controller="collapse-panel"]') as HTMLElement;
    expect(root.classList.contains('is-collapsed')).toBe(true);
    const body = root.querySelector('[data-collapse-panel-target="body"]') as HTMLElement;
    expect(body.hidden).toBe(true);
  });

  it('toggles open state and persists', async () => {
    await Promise.resolve();
    const root = document.querySelector('[data-controller="collapse-panel"]') as HTMLElement;
    const controller = application.getControllerForElementAndIdentifier(
      root,
      'collapse-panel',
    ) as CollapsePanelController;

    controller.toggle();
    expect(root.classList.contains('is-collapsed')).toBe(false);
    const saved = JSON.parse(localStorage.getItem('beacon.issuePanelState') ?? '{}');
    expect(saved.raw).toBe(true);
  });

  it('restores saved open state and ignores invalid storage', async () => {
    application.stop();
    localStorage.setItem('beacon.issuePanelState', JSON.stringify({ details: false }));
    document.body.innerHTML = `
      <div data-controller="collapse-panel" data-collapse-panel-id-value="details">
        <button type="button" data-collapse-panel-target="button"></button>
        <div data-collapse-panel-target="body">content</div>
      </div>
    `;
    application = Application.start();
    application.register('collapse-panel', CollapsePanelController);
    await Promise.resolve();
    expect(
      (document.querySelector('[data-controller="collapse-panel"]') as HTMLElement).classList.contains(
        'is-collapsed',
      ),
    ).toBe(true);

    application.stop();
    localStorage.setItem('beacon.issuePanelState', '[1,2]');
    window.__BEACON_ISSUE_PANEL_DEFAULTS__ = undefined;
    document.body.innerHTML = `
      <div data-controller="collapse-panel" data-collapse-panel-id-value="other">
        <button type="button" data-collapse-panel-target="button"></button>
        <div data-collapse-panel-target="body">content</div>
      </div>
    `;
    application = Application.start();
    application.register('collapse-panel', CollapsePanelController);
    await Promise.resolve();
    expect(
      (document.querySelector('[data-controller="collapse-panel"]') as HTMLElement).classList.contains(
        'is-collapsed',
      ),
    ).toBe(false);
  });

  it('tolerates localStorage read/write failures', async () => {
    application.stop();
    const getItem = vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => {
      throw new Error('blocked');
    });
    const setItem = vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
      throw new Error('quota');
    });
    document.body.innerHTML = `
      <div data-controller="collapse-panel" data-collapse-panel-id-value="x">
        <button type="button" data-collapse-panel-target="button"></button>
        <div data-collapse-panel-target="body">content</div>
      </div>
    `;
    application = Application.start();
    application.register('collapse-panel', CollapsePanelController);
    await Promise.resolve();
    const controller = application.getControllerForElementAndIdentifier(
      document.querySelector('[data-controller="collapse-panel"]') as HTMLElement,
      'collapse-panel',
    ) as CollapsePanelController;
    controller.toggle();
    getItem.mockRestore();
    setItem.mockRestore();
  });
});
