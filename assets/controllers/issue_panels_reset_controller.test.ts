import { Application } from '@hotwired/stimulus';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import IssuePanelsResetController from './issue_panels_reset_controller';

describe('issue-panels-reset controller', () => {
  let application: Application;

  beforeEach(() => {
    localStorage.setItem('beacon.issuePanelState', '{"raw":true}');
    document.body.innerHTML = `
      <button
        type="button"
        data-controller="issue-panels-reset"
        data-issue-panels-reset-done-label-value="Cleared"
      >Reset</button>
    `;
    application = Application.start();
    application.register('issue-panels-reset', IssuePanelsResetController);
  });

  afterEach(() => {
    application.stop();
    document.body.innerHTML = '';
    localStorage.clear();
  });

  it('clears panel state and updates label', async () => {
    await Promise.resolve();
    const button = document.querySelector('button') as HTMLButtonElement;
    const controller = application.getControllerForElementAndIdentifier(
      button,
      'issue-panels-reset',
    ) as IssuePanelsResetController;

    controller.reset();
    expect(localStorage.getItem('beacon.issuePanelState')).toBeNull();
    expect(button.textContent).toBe('Cleared');
  });
});
