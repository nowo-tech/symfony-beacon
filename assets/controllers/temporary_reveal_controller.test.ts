import { Application } from '@hotwired/stimulus';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import TemporaryRevealController from './temporary_reveal_controller';

describe('temporary-reveal controller', () => {
  let application: Application;

  beforeEach(() => {
    document.body.innerHTML = `
      <div
        data-controller="temporary-reveal"
        data-temporary-reveal-secret-value="https://pk:supersecret@localhost/uuid"
        data-temporary-reveal-duration-value="5000"
        data-temporary-reveal-show-label-value="Show DSN"
        data-temporary-reveal-hide-label-value="Hide DSN"
      >
        <code data-temporary-reveal-target="display"></code>
        <button
          type="button"
          data-temporary-reveal-target="toggle"
          data-action="click->temporary-reveal#toggle"
        >Show DSN</button>
      </div>
    `;
    application = Application.start();
    application.register('temporary-reveal', TemporaryRevealController);
  });

  afterEach(() => {
    application.stop();
    document.body.innerHTML = '';
    vi.useRealTimers();
  });

  const getController = async (): Promise<TemporaryRevealController> => {
    await Promise.resolve();
    const root = document.querySelector('[data-controller="temporary-reveal"]') as HTMLElement;
    return application.getControllerForElementAndIdentifier(
      root,
      'temporary-reveal',
    ) as TemporaryRevealController;
  };

  it('masks the secret on connect', async () => {
    await getController();
    const display = document.querySelector('code') as HTMLElement;
    expect(display.textContent).toBe('https://pk:••••••••@localhost/uuid');
    expect(display.dataset.revealed).toBeUndefined();
  });

  it('reveals then auto-hides after duration', async () => {
    vi.useFakeTimers();
    const controller = await getController();
    const display = document.querySelector('code') as HTMLElement;
    const toggle = document.querySelector('button') as HTMLButtonElement;

    controller.toggle();
    expect(display.textContent).toBe('https://pk:supersecret@localhost/uuid');
    expect(display.dataset.revealed).toBe('true');
    expect(toggle.textContent).toBe('Hide DSN');

    vi.advanceTimersByTime(5000);
    expect(display.textContent).toBe('https://pk:••••••••@localhost/uuid');
    expect(display.dataset.revealed).toBeUndefined();
    expect(toggle.textContent).toBe('Show DSN');
  });

  it('hides immediately on second toggle', async () => {
    const controller = await getController();
    const display = document.querySelector('code') as HTMLElement;

    controller.toggle();
    expect(display.dataset.revealed).toBe('true');
    controller.toggle();
    expect(display.textContent).toBe('https://pk:••••••••@localhost/uuid');
    expect(display.dataset.revealed).toBeUndefined();
  });

  it('starts revealed and purges secret when clearOnHide is set', async () => {
    vi.useFakeTimers();
    document.body.innerHTML = `
      <div
        data-controller="temporary-reveal"
        data-temporary-reveal-secret-value="https://pk:supersecret@localhost/uuid"
        data-temporary-reveal-duration-value="5000"
        data-temporary-reveal-start-revealed-value="true"
        data-temporary-reveal-clear-on-hide-value="true"
        data-temporary-reveal-cleared-label-value="Hidden — rotate"
        data-temporary-reveal-show-label-value="Show DSN"
        data-temporary-reveal-hide-label-value="Hide DSN"
      >
        <code data-temporary-reveal-target="display"></code>
        <button type="button" data-temporary-reveal-target="toggle">Hide DSN</button>
        <button type="button" data-clipboard-copy-text-value="https://pk:supersecret@localhost/uuid">Copy</button>
      </div>
    `;
    application.stop();
    application = Application.start();
    application.register('temporary-reveal', TemporaryRevealController);
    await Promise.resolve();

    const root = document.querySelector('[data-controller="temporary-reveal"]') as HTMLElement;
    const display = document.querySelector('code') as HTMLElement;
    const copy = document.querySelector('[data-clipboard-copy-text-value]') as HTMLButtonElement;
    expect(display.textContent).toBe('https://pk:supersecret@localhost/uuid');

    vi.advanceTimersByTime(5000);
    expect(display.textContent).toBe('Hidden — rotate');
    expect(display.dataset.cleared).toBe('true');
    expect(root.getAttribute('data-temporary-reveal-secret-value')).toBeNull();
    expect(copy.getAttribute('data-clipboard-copy-text-value')).toBe('');
    expect(copy.disabled).toBe(true);
  });
});
