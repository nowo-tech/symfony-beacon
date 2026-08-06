import { Application } from '@hotwired/stimulus';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import ClipboardCopyController from './clipboard_copy_controller';

describe('clipboard-copy controller', () => {
  let application: Application;

  beforeEach(() => {
    document.body.innerHTML = `
      <button
        type="button"
        data-controller="clipboard-copy"
        data-clipboard-copy-text-value="secret-key"
        data-clipboard-copy-label-value="Copy"
        data-clipboard-copy-done-label-value="Copied"
      >Copy</button>
    `;
    application = Application.start();
    application.register('clipboard-copy', ClipboardCopyController);
    Object.assign(navigator, {
      clipboard: {
        writeText: vi.fn().mockResolvedValue(undefined),
      },
    });
  });

  afterEach(() => {
    application.stop();
    document.body.innerHTML = '';
    vi.restoreAllMocks();
  });

  it('copies text and flashes done label', async () => {
    await Promise.resolve();
    vi.useFakeTimers();
    const button = document.querySelector('button') as HTMLButtonElement;
    const controller = application.getControllerForElementAndIdentifier(
      button,
      'clipboard-copy',
    ) as ClipboardCopyController;

    const event = new Event('click');
    Object.defineProperty(event, 'currentTarget', { value: button });
    await controller.copy(event);
    expect(navigator.clipboard.writeText).toHaveBeenCalledWith('secret-key');
    expect(button.textContent).toBe('Copied');

    vi.advanceTimersByTime(1600);
    expect(button.textContent).toBe('Copy');
    vi.useRealTimers();
  });

  it('no-ops on empty text', async () => {
    await Promise.resolve();
    const button = document.querySelector('button') as HTMLButtonElement;
    button.setAttribute('data-clipboard-copy-text-value', '   ');
    application.stop();
    application = Application.start();
    application.register('clipboard-copy', ClipboardCopyController);
    await Promise.resolve();

    const controller = application.getControllerForElementAndIdentifier(
      button,
      'clipboard-copy',
    ) as ClipboardCopyController;
    await controller.copy(new Event('click'));
    expect(navigator.clipboard.writeText).not.toHaveBeenCalled();
  });
});
