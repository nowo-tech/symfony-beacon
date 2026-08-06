import { Application } from '@hotwired/stimulus';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import MenuNestedCollapseController from './menu_nested_collapse_controller';

describe('menu-nested-collapse controller', () => {
  let application: Application;

  beforeEach(() => {
    localStorage.clear();
    vi.stubGlobal('requestAnimationFrame', (cb: FrameRequestCallback) => {
      cb(0);
      return 0;
    });
    document.body.innerHTML = `
      <nav data-controller="menu-nested-collapse" data-menu-nested-collapse-menu-code-value="dashboard">
        <li>
          <button
            type="button"
            class="menu-nested-toggle"
            data-bs-target="#submenu"
            aria-controls="submenu"
            aria-expanded="true"
          >Toggle</button>
          <div id="submenu" class="collapse show"></div>
        </li>
      </nav>
    `;
    application = Application.start();
    application.register('menu-nested-collapse', MenuNestedCollapseController);
  });

  afterEach(() => {
    application.stop();
    document.body.innerHTML = '';
    localStorage.clear();
    vi.unstubAllGlobals();
  });

  it('toggles panel and persists state', async () => {
    await Promise.resolve();
    const root = document.querySelector('nav') as HTMLElement;
    const controller = application.getControllerForElementAndIdentifier(
      root,
      'menu-nested-collapse',
    ) as MenuNestedCollapseController;
    const button = root.querySelector('button') as HTMLButtonElement;
    const panel = root.querySelector('#submenu') as HTMLElement;

    controller.toggle({
      target: button,
      preventDefault: () => undefined,
    } as unknown as Event);

    expect(panel.classList.contains('show')).toBe(false);
    const saved = JSON.parse(localStorage.getItem('beacon.navCollapse') ?? '{}');
    expect(saved.dashboard.submenu).toBe(false);
  });
});
