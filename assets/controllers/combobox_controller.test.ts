import { Application } from '@hotwired/stimulus';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import ComboboxController from './combobox_controller';

describe('combobox controller', () => {
  let application: Application;

  beforeEach(() => {
    document.body.innerHTML = `
      <div data-controller="combobox">
        <input data-combobox-target="query" data-required-message="Pick one" />
        <input type="hidden" data-combobox-target="value" value="" />
        <ul data-combobox-target="list" hidden>
          <li>
            <button type="button"
              data-combobox-target="option"
              data-action="combobox#select"
              data-search="alpha one"
              data-value="1"
              data-label="Alpha">Alpha</button>
          </li>
          <li>
            <button type="button"
              data-combobox-target="option"
              data-action="combobox#select"
              data-search="beta two"
              data-value="2"
              data-label="Beta">Beta</button>
          </li>
          <li data-combobox-target="empty" hidden>No matches</li>
        </ul>
      </div>
    `;
    application = Application.start();
    application.register('combobox', ComboboxController);
  });

  afterEach(() => {
    application.stop();
    document.body.innerHTML = '';
  });

  const getController = async (): Promise<ComboboxController> => {
    await Promise.resolve();
    const el = document.querySelector('[data-controller="combobox"]') as HTMLElement;
    return application.getControllerForElementAndIdentifier(el, 'combobox') as ComboboxController;
  };

  it('filters options and clears stale value', async () => {
    const controller = await getController();
    const query = document.querySelector('[data-combobox-target="query"]') as HTMLInputElement;
    const value = document.querySelector('[data-combobox-target="value"]') as HTMLInputElement;
    const options = document.querySelectorAll('[data-combobox-target="option"]');
    const empty = document.querySelector('[data-combobox-target="empty"]') as HTMLElement;

    value.value = '1';
    query.value = 'beta';
    controller.filter();

    expect(options[0].hidden).toBe(true);
    expect(options[1].hidden).toBe(false);
    expect(empty.hidden).toBe(true);
    expect(value.value).toBe('');
  });

  it('shows empty state when nothing matches', async () => {
    const controller = await getController();
    const query = document.querySelector('[data-combobox-target="query"]') as HTMLInputElement;
    const empty = document.querySelector('[data-combobox-target="empty"]') as HTMLElement;

    query.value = 'zzz';
    controller.filter();
    expect(empty.hidden).toBe(false);
  });

  it('selects an option and closes the list', async () => {
    const controller = await getController();
    controller.open();
    const button = document.querySelector('[data-value="2"]') as HTMLButtonElement;
    const event = new Event('click', { cancelable: true });
    Object.defineProperty(event, 'currentTarget', { value: button });
    controller.select(event);

    const value = document.querySelector('[data-combobox-target="value"]') as HTMLInputElement;
    const query = document.querySelector('[data-combobox-target="query"]') as HTMLInputElement;
    const list = document.querySelector('[data-combobox-target="list"]') as HTMLElement;
    expect(value.value).toBe('2');
    expect(query.value).toBe('Beta');
    expect(list.hidden).toBe(true);
    expect(button.classList.contains('is-selected')).toBe(true);
  });

  it('ignores select without value and non-element currentTarget', async () => {
    const controller = await getController();
    const emptyBtn = document.createElement('button');
    emptyBtn.dataset.value = '';
    const event = new Event('click', { cancelable: true });
    Object.defineProperty(event, 'currentTarget', { value: emptyBtn });
    controller.select(event);

    const bad = new Event('click', { cancelable: true });
    Object.defineProperty(bad, 'currentTarget', { value: null });
    controller.select(bad);
  });

  it('opens on focus and handles Escape / Enter', async () => {
    const controller = await getController();
    const query = document.querySelector('[data-combobox-target="query"]') as HTMLInputElement;
    const list = document.querySelector('[data-combobox-target="list"]') as HTMLElement;
    const first = document.querySelector('[data-value="1"]') as HTMLButtonElement;
    const clickSpy = vi.spyOn(first, 'click');

    controller.onQueryFocus();
    expect(list.hidden).toBe(false);

    controller.onQueryKeydown(new KeyboardEvent('keydown', { key: 'Escape' }));
    expect(list.hidden).toBe(true);

    controller.onQueryFocus();
    controller.onQueryKeydown(new KeyboardEvent('keydown', { key: 'Enter' }));
    expect(clickSpy).toHaveBeenCalled();
  });

  it('requireValue blocks submit when empty', async () => {
    const controller = await getController();
    const query = document.querySelector('[data-combobox-target="query"]') as HTMLInputElement;
    const report = vi.spyOn(query, 'reportValidity').mockReturnValue(false);
    const event = new Event('submit', { cancelable: true });
    controller.requireValue(event);
    expect(event.defaultPrevented).toBe(true);
    expect(query.validationMessage).toBe('Pick one');
    expect(report).toHaveBeenCalled();

    const value = document.querySelector('[data-combobox-target="value"]') as HTMLInputElement;
    value.value = '1';
    const ok = new Event('submit', { cancelable: true });
    controller.requireValue(ok);
    expect(ok.defaultPrevented).toBe(false);
  });
});
