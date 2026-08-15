import { Application } from '@hotwired/stimulus';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import NavigateSelectController from './navigate_select_controller';

describe('navigate-select controller', () => {
  let application: Application;

  beforeEach(() => {
    document.body.innerHTML = `
      <select data-controller="navigate-select" data-action="change->navigate-select#change">
        <option value="">Pick</option>
        <option value="/projects/1">One</option>
      </select>
    `;
    application = Application.start();
    application.register('navigate-select', NavigateSelectController);
  });

  afterEach(() => {
    application.stop();
    document.body.innerHTML = '';
    vi.restoreAllMocks();
  });

  it('assigns location when option has a URL', async () => {
    await Promise.resolve();
    const assign = vi.fn();
    vi.stubGlobal('location', { ...window.location, assign });

    const select = document.querySelector('select') as HTMLSelectElement;
    const controller = application.getControllerForElementAndIdentifier(
      select,
      'navigate-select',
    ) as NavigateSelectController;

    select.value = '/projects/1';
    controller.change({ currentTarget: select } as unknown as Event);
    expect(assign).toHaveBeenCalledWith('/projects/1');
  });

  it('ignores empty selection', async () => {
    await Promise.resolve();
    const assign = vi.fn();
    vi.stubGlobal('location', { ...window.location, assign });

    const select = document.querySelector('select') as HTMLSelectElement;
    const controller = application.getControllerForElementAndIdentifier(
      select,
      'navigate-select',
    ) as NavigateSelectController;

    select.value = '';
    controller.change({ currentTarget: select } as unknown as Event);
    expect(assign).not.toHaveBeenCalled();
  });

  it('ignores non-select currentTarget', async () => {
    await Promise.resolve();
    const assign = vi.fn();
    vi.stubGlobal('location', { ...window.location, assign });

    const select = document.querySelector('select') as HTMLSelectElement;
    const controller = application.getControllerForElementAndIdentifier(
      select,
      'navigate-select',
    ) as NavigateSelectController;

    controller.change({ currentTarget: document.createElement('div') } as unknown as Event);
    expect(assign).not.toHaveBeenCalled();
  });
});
