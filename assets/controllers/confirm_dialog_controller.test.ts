import { Application } from '@hotwired/stimulus';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import ConfirmDialogController from './confirm_dialog_controller';

describe('confirm-dialog controller', () => {
  let application: Application;

  beforeEach(() => {
    if (!HTMLDialogElement.prototype.showModal) {
      HTMLDialogElement.prototype.showModal = function showModal(this: HTMLDialogElement) {
        this.setAttribute('open', '');
      };
    }
    if (!HTMLDialogElement.prototype.close) {
      HTMLDialogElement.prototype.close = function close(this: HTMLDialogElement) {
        this.removeAttribute('open');
      };
    }
    document.body.innerHTML = `
      <div
        data-controller="confirm-dialog"
        data-confirm-dialog-expected-value="DELETE"
        data-confirm-dialog-open-on-connect-value="false"
      >
        <button type="button" data-action="confirm-dialog#open">Open</button>
        <dialog class="confirm-dialog" data-confirm-dialog-target="dialog">
          <input data-confirm-dialog-target="confirmInput" />
          <button type="submit" data-confirm-dialog-target="submit" disabled>Confirm</button>
          <button type="button" data-confirm-dialog-close>Cancel</button>
        </dialog>
      </div>
    `;
    application = Application.start();
    application.register('confirm-dialog', ConfirmDialogController);
  });

  afterEach(() => {
    application.stop();
    document.body.innerHTML = '';
    vi.useRealTimers();
  });

  const getController = async (): Promise<ConfirmDialogController> => {
    await Promise.resolve();
    const el = document.querySelector('[data-controller="confirm-dialog"]') as HTMLElement;
    return application.getControllerForElementAndIdentifier(
      el,
      'confirm-dialog',
    ) as ConfirmDialogController;
  };

  it('opens dialog, portals to body, and enables submit when expected matches', async () => {
    vi.stubGlobal('requestAnimationFrame', (cb: FrameRequestCallback) => {
      cb(0);
      return 1;
    });
    const controller = await getController();
    const dialog = document.querySelector('dialog') as HTMLDialogElement;
    const showModal = vi
      .spyOn(dialog, 'showModal')
      .mockImplementation(function (this: HTMLDialogElement) {
        this.setAttribute('open', '');
      });

    controller.open();
    expect(dialog.parentElement).toBe(document.body);
    expect(showModal).toHaveBeenCalled();

    const input = document.querySelector(
      '[data-confirm-dialog-target="confirmInput"]',
    ) as HTMLInputElement;
    const submit = document.querySelector(
      '[data-confirm-dialog-target="submit"]',
    ) as HTMLButtonElement;
    expect(submit.disabled).toBe(true);
    input.value = 'DELETE';
    controller.syncSubmit();
    expect(submit.disabled).toBe(false);

    controller.close();
    expect(dialog.hasAttribute('open')).toBe(false);
  });

  it('closes via data-confirm-dialog-close click handler', async () => {
    vi.stubGlobal('requestAnimationFrame', (cb: FrameRequestCallback) => {
      cb(0);
      return 1;
    });
    const controller = await getController();
    const dialog = document.querySelector('dialog') as HTMLDialogElement;
    vi.spyOn(dialog, 'showModal').mockImplementation(function (this: HTMLDialogElement) {
      this.setAttribute('open', '');
    });
    const closeSpy = vi.spyOn(dialog, 'close').mockImplementation(function (this: HTMLDialogElement) {
      this.removeAttribute('open');
    });

    controller.open();
    const cancel = dialog.querySelector('[data-confirm-dialog-close]') as HTMLButtonElement;
    cancel.dispatchEvent(new MouseEvent('click', { bubbles: true }));
    expect(closeSpy).toHaveBeenCalled();
  });

  it('keeps submit enabled when no expected value', async () => {
    application.stop();
    document.body.innerHTML = `
      <div data-controller="confirm-dialog">
        <dialog class="confirm-dialog" data-confirm-dialog-target="dialog">
          <button type="submit" data-confirm-dialog-target="submit">Go</button>
        </dialog>
      </div>
    `;
    application = Application.start();
    application.register('confirm-dialog', ConfirmDialogController);
    const controller = await getController();
    const submit = document.querySelector(
      '[data-confirm-dialog-target="submit"]',
    ) as HTMLButtonElement;
    controller.syncSubmit();
    expect(submit.disabled).toBe(false);
  });

  it('opens on connect when configured', async () => {
    application.stop();
    document.body.innerHTML = `
      <div
        data-controller="confirm-dialog"
        data-confirm-dialog-open-on-connect-value="true"
      >
        <dialog class="confirm-dialog" data-confirm-dialog-target="dialog"></dialog>
      </div>
    `;
    vi.stubGlobal('requestAnimationFrame', (cb: FrameRequestCallback) => {
      cb(0);
      return 1;
    });
    application = Application.start();
    application.register('confirm-dialog', ConfirmDialogController);
    await Promise.resolve();
    const dialog = document.querySelector('dialog') as HTMLDialogElement;
    expect(dialog.parentElement).toBe(document.body);
  });
});
