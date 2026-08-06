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
});
