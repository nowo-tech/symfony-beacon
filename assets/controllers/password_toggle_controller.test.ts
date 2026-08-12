import { Application } from '@hotwired/stimulus';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import PasswordToggleController from './password_toggle_controller';

describe('password-toggle controller', () => {
  let application: Application;

  beforeEach(() => {
    document.body.innerHTML = `
      <input type="password" id="pwd" />
      <button
        type="button"
        id="toggle"
        data-controller="password-toggle"
        data-password-toggle-show-label-value="Show"
        data-password-toggle-hide-label-value="Hide"
        aria-label="Show"
      >
        <span class="icon-hidden"></span>
        <span class="icon-visible"></span>
      </button>
    `;
    application = Application.start();
    application.register('password-toggle', PasswordToggleController);
  });

  afterEach(() => {
    application.stop();
    document.body.innerHTML = '';
  });

  it('toggles password visibility, aria-label, and is-password-visible class', async () => {
    await Promise.resolve();
    const input = document.getElementById('pwd') as HTMLInputElement;
    const button = document.getElementById('toggle') as HTMLButtonElement;
    const controller = application.getControllerForElementAndIdentifier(
      button,
      'password-toggle',
    ) as PasswordToggleController;

    controller.toggle();
    expect(input.type).toBe('text');
    expect(button.getAttribute('aria-label')).toBe('Hide');
    expect(button.classList.contains('is-password-visible')).toBe(true);

    controller.toggle();
    expect(input.type).toBe('password');
    expect(button.getAttribute('aria-label')).toBe('Show');
    expect(button.classList.contains('is-password-visible')).toBe(false);
  });

  it('toggles on Enter keydown', async () => {
    await Promise.resolve();
    const input = document.getElementById('pwd') as HTMLInputElement;
    const button = document.getElementById('toggle') as HTMLButtonElement;
    const controller = application.getControllerForElementAndIdentifier(
      button,
      'password-toggle',
    ) as PasswordToggleController;

    controller.keydown(new KeyboardEvent('keydown', { key: 'Enter' }));
    expect(input.type).toBe('text');
    expect(button.classList.contains('is-password-visible')).toBe(true);
  });
});
