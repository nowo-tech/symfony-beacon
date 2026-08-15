import { Application } from '@hotwired/stimulus';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import ConfirmSubmitController from './confirm_submit_controller';

describe('confirm-submit controller', () => {
  let application: Application;

  beforeEach(() => {
    document.body.innerHTML = `
      <form
        data-controller="confirm-submit"
        data-confirm-submit-message-value="Delete?"
        data-action="submit->confirm-submit#confirm"
      ></form>
    `;
    application = Application.start();
    application.register('confirm-submit', ConfirmSubmitController);
  });

  afterEach(() => {
    application.stop();
    document.body.innerHTML = '';
    vi.restoreAllMocks();
  });

  it('prevents submit when confirm is declined', async () => {
    await Promise.resolve();
    vi.spyOn(window, 'confirm').mockReturnValue(false);
    const form = document.querySelector('form') as HTMLFormElement;
    const controller = application.getControllerForElementAndIdentifier(
      form,
      'confirm-submit',
    ) as ConfirmSubmitController;
    const event = new Event('submit', { cancelable: true });
    controller.confirm(event);
    expect(event.defaultPrevented).toBe(true);
  });

  it('allows submit when confirm is accepted', async () => {
    await Promise.resolve();
    vi.spyOn(window, 'confirm').mockReturnValue(true);
    const form = document.querySelector('form') as HTMLFormElement;
    const controller = application.getControllerForElementAndIdentifier(
      form,
      'confirm-submit',
    ) as ConfirmSubmitController;
    const event = new Event('submit', { cancelable: true });
    controller.confirm(event);
    expect(event.defaultPrevented).toBe(false);
  });

  it('blocks submit when blocked value is true', async () => {
    await Promise.resolve();
    const form = document.querySelector('form') as HTMLFormElement;
    form.setAttribute('data-confirm-submit-blocked-value', 'true');
    application.stop();
    application = Application.start();
    application.register('confirm-submit', ConfirmSubmitController);
    await Promise.resolve();

    const controller = application.getControllerForElementAndIdentifier(
      form,
      'confirm-submit',
    ) as ConfirmSubmitController;
    const event = new Event('submit', { cancelable: true });
    controller.confirm(event);
    expect(event.defaultPrevented).toBe(true);
  });

  it('allows submit when message is empty without prompting', async () => {
    await Promise.resolve();
    const form = document.querySelector('form') as HTMLFormElement;
    form.setAttribute('data-confirm-submit-message-value', '   ');
    application.stop();
    application = Application.start();
    application.register('confirm-submit', ConfirmSubmitController);
    await Promise.resolve();

    const confirm = vi.spyOn(window, 'confirm');
    const controller = application.getControllerForElementAndIdentifier(
      form,
      'confirm-submit',
    ) as ConfirmSubmitController;
    const event = new Event('submit', { cancelable: true });
    controller.confirm(event);
    expect(confirm).not.toHaveBeenCalled();
    expect(event.defaultPrevented).toBe(false);
  });
});
