import { Application } from '@hotwired/stimulus';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import ToastStackController from './toast_stack_controller';

describe('toast-stack controller', () => {
  let application: Application;

  beforeEach(() => {
    vi.useFakeTimers();
    document.body.innerHTML = `
      <div data-controller="toast-stack">
        <div data-toast-stack-target="toast" data-timeout="1000" class="toast">
          Hello
          <button type="button" data-action="toast-stack#dismiss">x</button>
        </div>
      </div>
    `;
    application = Application.start();
    application.register('toast-stack', ToastStackController);
  });

  afterEach(() => {
    application.stop();
    document.body.innerHTML = '';
    vi.useRealTimers();
  });

  it('auto-dismisses after timeout', async () => {
    await Promise.resolve();
    const toast = document.querySelector('[data-toast-stack-target="toast"]') as HTMLElement;
    expect(toast).toBeTruthy();
    vi.advanceTimersByTime(1000);
    expect(toast.classList.contains('is-leaving')).toBe(true);
  });

  it('dismisses on button click', async () => {
    await Promise.resolve();
    const button = document.querySelector('button') as HTMLButtonElement;
    const toast = document.querySelector('[data-toast-stack-target="toast"]') as HTMLElement;
    const controller = application.getControllerForElementAndIdentifier(
      document.querySelector('[data-controller="toast-stack"]') as HTMLElement,
      'toast-stack',
    ) as ToastStackController;

    const event = new Event('click');
    Object.defineProperty(event, 'currentTarget', { value: button });
    controller.dismiss(event);
    expect(toast.classList.contains('is-leaving')).toBe(true);
  });
});
