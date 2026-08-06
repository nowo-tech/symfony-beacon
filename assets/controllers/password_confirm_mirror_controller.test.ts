import { Application } from '@hotwired/stimulus';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import PasswordConfirmMirrorController from './password_confirm_mirror_controller';

describe('password-confirm-mirror controller', () => {
  let application: Application;

  beforeEach(() => {
    vi.stubGlobal('requestAnimationFrame', (cb: FrameRequestCallback) => {
      cb(0);
      return 0;
    });
    document.body.innerHTML = `
      <form data-controller="password-confirm-mirror">
        <div class="password-strength-modal-item">
          <button type="button">Cancel</button>
          <button type="button">Use</button>
        </div>
        <input class="password-strength-input" type="password" value="GeneratedPass1!" />
        <input name="user_preferences[plainPassword_confirm]" type="password" />
      </form>
    `;
    application = Application.start();
    application.register('password-confirm-mirror', PasswordConfirmMirrorController);
  });

  afterEach(() => {
    application.stop();
    document.body.innerHTML = '';
    vi.unstubAllGlobals();
  });

  it('mirrors strength password into confirm on Use click', async () => {
    await Promise.resolve();
    const useBtn = document.querySelectorAll('button')[1] as HTMLButtonElement;
    useBtn.dispatchEvent(new MouseEvent('click', { bubbles: true }));

    const confirm = document.querySelector(
      'input[name="user_preferences[plainPassword_confirm]"]',
    ) as HTMLInputElement;
    expect(confirm.value).toBe('GeneratedPass1!');
  });
});
