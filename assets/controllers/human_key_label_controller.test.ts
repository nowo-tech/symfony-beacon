import { Application } from '@hotwired/stimulus';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import HumanKeyLabelController from './human_key_label_controller';

describe('human-key-label controller', () => {
  let application: Application;

  beforeEach(() => {
    document.body.innerHTML = `
      <div
        data-controller="human-key-label"
        data-human-key-label-adjectives-value='["calm","bright"]'
        data-human-key-label-nouns-value='["beacon","harbor"]'
      >
        <input data-human-key-label-target="label" type="text" />
      </div>
    `;
    application = Application.start();
    application.register('human-key-label', HumanKeyLabelController);
  });

  afterEach(() => {
    application.stop();
    document.body.innerHTML = '';
    vi.restoreAllMocks();
  });

  it('fills label with adjective-noun', async () => {
    await Promise.resolve();
    vi.spyOn(Math, 'random').mockReturnValue(0);
    const root = document.querySelector('[data-controller="human-key-label"]') as HTMLElement;
    const input = root.querySelector('input') as HTMLInputElement;
    const controller = application.getControllerForElementAndIdentifier(
      root,
      'human-key-label',
    ) as HumanKeyLabelController;

    controller.generate(new Event('click'));
    expect(input.value).toBe('calm-beacon');
  });
});
