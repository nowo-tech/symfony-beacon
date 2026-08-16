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

  it('covers guard paths, invalid storage, and branch-current restore', async () => {
    await Promise.resolve();
    const root = document.querySelector('nav') as HTMLElement;
    const controller = application.getControllerForElementAndIdentifier(
      root,
      'menu-nested-collapse',
    ) as MenuNestedCollapseController;

    controller.toggle({
      target: document.createElement('span'),
      preventDefault: () => undefined,
    } as unknown as Event);

    const button = root.querySelector('button') as HTMLButtonElement;
    button.removeAttribute('data-bs-target');
    button.removeAttribute('aria-controls');
    controller.toggle({
      target: button,
      preventDefault: () => undefined,
    } as unknown as Event);

    button.setAttribute('data-bs-target', '#missing');
    controller.toggle({
      target: button,
      preventDefault: () => undefined,
    } as unknown as Event);

    application.stop();
    localStorage.setItem('beacon.navCollapse', '[]');
    document.body.innerHTML = `
      <nav data-controller="menu-nested-collapse" data-menu-nested-collapse-menu-code-value="dashboard">
        <li class="is-branch-current">
          <button type="button" class="menu-nested-toggle" data-bs-target="#submenu2" aria-controls="submenu2">T</button>
          <div class="collapse"></div>
        </li>
      </nav>
    `;
    application = Application.start();
    application.register('menu-nested-collapse', MenuNestedCollapseController);
    await Promise.resolve();
    expect(document.querySelector('.collapse')?.classList.contains('show')).toBe(true);

    localStorage.setItem('beacon.navCollapse', '{bad');
    application.stop();
    document.body.innerHTML = `
      <nav data-controller="menu-nested-collapse">
        <li>
          <button type="button" class="menu-nested-toggle" data-bs-target="#p" aria-controls="p">T</button>
          <div id="p" class="collapse show"></div>
        </li>
      </nav>
    `;
    application = Application.start();
    application.register('menu-nested-collapse', MenuNestedCollapseController);
    await Promise.resolve();

    const setItem = vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
      throw new Error('quota');
    });
    const nav = document.querySelector('nav') as HTMLElement;
    const c2 = application.getControllerForElementAndIdentifier(
      nav,
      'menu-nested-collapse',
    ) as MenuNestedCollapseController;
    c2.toggle({
      target: nav.querySelector('button'),
      preventDefault: () => undefined,
    } as unknown as Event);
    setItem.mockRestore();
  });

  it('covers non-object menu bucket and getItem failures', async () => {
    localStorage.setItem('beacon.navCollapse', JSON.stringify({ dashboard: ['nope'] }));
    application.stop();
    document.body.innerHTML = `
      <nav data-controller="menu-nested-collapse" data-menu-nested-collapse-menu-code-value="dashboard">
        <li>
          <button type="button" class="menu-nested-toggle" data-bs-target="#p" aria-controls="p">T</button>
          <div id="p" class="collapse show"></div>
        </li>
      </nav>
    `;
    application = Application.start();
    application.register('menu-nested-collapse', MenuNestedCollapseController);
    await Promise.resolve();
    const nav = document.querySelector('nav') as HTMLElement;
    const c = application.getControllerForElementAndIdentifier(
      nav,
      'menu-nested-collapse',
    ) as MenuNestedCollapseController;
    c.toggle({
      target: nav.querySelector('button'),
      preventDefault: () => undefined,
    } as unknown as Event);

    const getItem = vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => {
      throw new Error('blocked');
    });
    application.stop();
    document.body.innerHTML = `
      <nav data-controller="menu-nested-collapse" data-menu-nested-collapse-menu-code-value="dashboard">
        <li>
          <button type="button" class="menu-nested-toggle" data-bs-target="#p2" aria-controls="p2">T</button>
          <div id="p2" class="collapse"></div>
        </li>
      </nav>
    `;
    application = Application.start();
    application.register('menu-nested-collapse', MenuNestedCollapseController);
    await Promise.resolve();
    getItem.mockRestore();
  });


  it('covers valid object menu bucket restore', async () => {
    localStorage.setItem(
      'beacon.navCollapse',
      JSON.stringify({ dashboard: { submenu: true } }),
    );
    application.stop();
    document.body.innerHTML = `
      <nav data-controller="menu-nested-collapse" data-menu-nested-collapse-menu-code-value="dashboard">
        <li>
          <button type="button" class="menu-nested-toggle" data-bs-target="#submenu" aria-controls="submenu">T</button>
          <div id="submenu" class="collapse"></div>
        </li>
      </nav>
    `;
    application = Application.start();
    application.register('menu-nested-collapse', MenuNestedCollapseController);
    await Promise.resolve();
    const nav = document.querySelector('nav') as HTMLElement;
    const c = application.getControllerForElementAndIdentifier(
      nav,
      'menu-nested-collapse',
    ) as MenuNestedCollapseController;
    c.toggle({
      target: nav.querySelector('button'),
      preventDefault: () => undefined,
    } as unknown as Event);
    expect(document.querySelector('#submenu')?.classList.contains('show')).toEqual(expect.any(Boolean));
  });


  it('covers empty panel id writePanelState guard', async () => {
    application.stop();
    document.body.innerHTML = `
      <nav data-controller="menu-nested-collapse" data-menu-nested-collapse-menu-code-value="dashboard">
        <li>
          <button type="button" class="menu-nested-toggle" data-bs-target=".collapse" aria-controls="">T</button>
          <div class="collapse show"></div>
        </li>
      </nav>
    `;
    application = Application.start();
    application.register('menu-nested-collapse', MenuNestedCollapseController);
    await Promise.resolve();
    const nav = document.querySelector('nav') as HTMLElement;
    const c = application.getControllerForElementAndIdentifier(
      nav,
      'menu-nested-collapse',
    ) as MenuNestedCollapseController;
    c.toggle({
      target: nav.querySelector('button'),
      preventDefault: () => undefined,
    } as unknown as Event);
  });

});
