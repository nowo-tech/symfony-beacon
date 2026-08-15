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

  it('pauses on hover and removes toast after leave fallback', async () => {
    await Promise.resolve();
    const toast = document.querySelector('[data-toast-stack-target="toast"]') as HTMLElement;
    toast.dispatchEvent(new Event('mouseenter'));
    vi.advanceTimersByTime(2000);
    expect(toast.classList.contains('is-leaving')).toBe(false);
    toast.dispatchEvent(new Event('mouseleave'));
    vi.advanceTimersByTime(1000);
    expect(toast.classList.contains('is-leaving')).toBe(true);
    vi.advanceTimersByTime(400);
    expect(document.querySelector('[data-toast-stack-target="toast"]')).toBeNull();
    expect(document.querySelector('[data-controller="toast-stack"]')).toBeNull();
  });

  it('ignores non-element dismiss targets and non-positive timeouts', async () => {
    application.stop();
    document.body.innerHTML = `
      <div data-controller="toast-stack">
        <div data-toast-stack-target="toast" data-timeout="0" class="toast">Stay</div>
      </div>
    `;
    application = Application.start();
    application.register('toast-stack', ToastStackController);
    await Promise.resolve();
    const controller = application.getControllerForElementAndIdentifier(
      document.querySelector('[data-controller="toast-stack"]') as HTMLElement,
      'toast-stack',
    ) as ToastStackController;
    const event = new Event('click');
    Object.defineProperty(event, 'currentTarget', { value: null });
    controller.dismiss(event);
    vi.advanceTimersByTime(5000);
    expect(document.querySelector('[data-toast-stack-target="toast"]')).toBeTruthy();
  });

  it('ignores duplicate leave and second done callback', async () => {
    await Promise.resolve();
    const toast = document.querySelector('[data-toast-stack-target="toast"]') as HTMLElement;
    const controller = application.getControllerForElementAndIdentifier(
      document.querySelector('[data-controller="toast-stack"]') as HTMLElement,
      'toast-stack',
    ) as ToastStackController;
    const event = new Event('click');
    Object.defineProperty(event, 'currentTarget', {
      value: toast.querySelector('button'),
    });
    controller.dismiss(event);
    expect(toast.classList.contains('is-leaving')).toBe(true);
    controller.dismiss(event);
    toast.dispatchEvent(new Event('animationend'));
    vi.advanceTimersByTime(400);
    expect(document.querySelector('[data-toast-stack-target="toast"]')).toBeNull();
  });
});
