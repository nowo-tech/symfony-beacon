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
        data-clipboard-copy-url-value="/export.md"
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
    vi.unstubAllGlobals();
  });

  const getController = async (): Promise<ClipboardCopyController> => {
    await Promise.resolve();
    const button = document.querySelector('button') as HTMLButtonElement;
    return application.getControllerForElementAndIdentifier(
      button,
      'clipboard-copy',
    ) as ClipboardCopyController;
  };

  it('copies text and flashes done label', async () => {
    vi.useFakeTimers();
    const button = document.querySelector('button') as HTMLButtonElement;
    const controller = await getController();

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

  it('copies from URL and ignores failures', async () => {
    const fetchMock = vi
      .fn()
      .mockResolvedValueOnce({ ok: true, text: async () => '  markdown  ' })
      .mockResolvedValueOnce({ ok: false })
      .mockRejectedValueOnce(new Error('network'));
    vi.stubGlobal('fetch', fetchMock);

    const button = document.querySelector('button') as HTMLButtonElement;
    const controller = await getController();
    const event = new Event('click');
    Object.defineProperty(event, 'currentTarget', { value: button });

    await controller.copyFromUrl(event);
    expect(navigator.clipboard.writeText).toHaveBeenCalledWith('markdown');

    await controller.copyFromUrl(event);
    await controller.copyFromUrl(event);
  });

  it('falls back when clipboard API is missing and clears timer on disconnect', async () => {
    Object.assign(navigator, { clipboard: undefined });
    const exec = vi.fn().mockReturnValue(true);
    Object.defineProperty(document, 'execCommand', {
      configurable: true,
      value: exec,
    });
    vi.useFakeTimers();

    const button = document.querySelector('button') as HTMLButtonElement;
    const controller = await getController();
    const event = new Event('click');
    Object.defineProperty(event, 'currentTarget', { value: button });
    await controller.copy(event);
    expect(exec).toHaveBeenCalledWith('copy');
    expect(button.textContent).toBe('Copied');

    controller.disconnect();
    vi.advanceTimersByTime(2000);
    expect(button.textContent).toBe('Copied');
    vi.useRealTimers();
  });

  it('covers empty URL, empty body, clipboard failure fallback, and non-element flash', async () => {
    vi.useFakeTimers();
    const button = document.querySelector('button') as HTMLButtonElement;
    button.removeAttribute('data-clipboard-copy-url-value');
    application.stop();
    application = Application.start();
    application.register('clipboard-copy', ClipboardCopyController);
    await Promise.resolve();

    const controller = application.getControllerForElementAndIdentifier(
      button,
      'clipboard-copy',
    ) as ClipboardCopyController;

    await controller.copyFromUrl(new Event('click'));

    button.setAttribute('data-clipboard-copy-url-value', '/empty.md');
    application.stop();
    application = Application.start();
    application.register('clipboard-copy', ClipboardCopyController);
    await Promise.resolve();
    const controller2 = application.getControllerForElementAndIdentifier(
      button,
      'clipboard-copy',
    ) as ClipboardCopyController;

    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue({ ok: true, text: async () => '   ' }),
    );
    const emptyEvent = new Event('click');
    Object.defineProperty(emptyEvent, 'currentTarget', { value: button });
    await controller2.copyFromUrl(emptyEvent);
    expect(navigator.clipboard.writeText).not.toHaveBeenCalled();

    vi.mocked(navigator.clipboard.writeText).mockRejectedValueOnce(new Error('denied'));
    const exec = vi.fn().mockReturnValue(true);
    Object.defineProperty(document, 'execCommand', {
      configurable: true,
      value: exec,
    });
    const event = new Event('click');
    Object.defineProperty(event, 'currentTarget', { value: button });
    await controller2.copy(event);
    expect(exec).toHaveBeenCalledWith('copy');
    expect(button.textContent).toBe('Copied');

    // Second flash while timer active clears previous timeout.
    await controller2.copy(event);
    expect(button.textContent).toBe('Copied');

    const nullTarget = new Event('click');
    Object.defineProperty(nullTarget, 'currentTarget', { value: null });
    await controller2.copy(nullTarget);
    vi.useRealTimers();
  });
});
