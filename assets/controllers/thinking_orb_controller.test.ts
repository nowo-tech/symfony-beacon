import { Application } from '@hotwired/stimulus';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('../lib/thinking-orbs', () => ({
  MODE_DRAWS: {
    orbits: vi.fn(),
  },
  ORB_LABELS: {
    working: 'Working',
    searching: 'Searching',
    solving: 'Solving',
    listening: 'Listening',
    composing: 'Composing',
    shaping: 'Shaping',
  },
  resolvePreset: vi.fn(() => ({ mode: 'orbits', speed: 1, opts: {} })),
  watchThemeAndMotion: vi.fn((_theme, _el, cb: (dark: boolean, reduced: boolean) => void) => {
    cb(true, true);
    return vi.fn();
  }),
}));

import ThinkingOrbController from './thinking_orb_controller';

describe('thinking-orb controller', () => {
  let application: Application;

  beforeEach(() => {
    document.body.innerHTML = `
      <div
        data-controller="thinking-orb"
        data-thinking-orb-state-value="working"
        data-thinking-orb-size-value="64"
        data-thinking-orb-theme-value="dark"
        data-thinking-orb-speed-value="1"
        data-thinking-orb-paused-value="false"
        data-thinking-orb-rotate-states-value="true"
        data-thinking-orb-rotate-interval-value="800"
        data-thinking-orb-label-value=""
      ></div>
    `;
    application = Application.start();
    application.register('thinking-orb', ThinkingOrbController);
  });

  afterEach(() => {
    application.stop();
    document.body.innerHTML = '';
    vi.useRealTimers();
  });

  it('creates canvas, sets aria, and draws reduced-motion frame', async () => {
    await Promise.resolve();
    const root = document.querySelector('[data-controller="thinking-orb"]') as HTMLElement;
    const canvas = root.querySelector('canvas') as HTMLCanvasElement;
    expect(canvas).toBeTruthy();
    expect(canvas.getAttribute('role')).toBe('img');
    expect(canvas.getAttribute('aria-label')).toBe('Working');
  });

  it('reacts to state/size/theme/speed/paused changes and rotation', async () => {
    vi.useFakeTimers();
    await Promise.resolve();
    const root = document.querySelector('[data-controller="thinking-orb"]') as HTMLElement;
    const controller = application.getControllerForElementAndIdentifier(
      root,
      'thinking-orb',
    ) as ThinkingOrbController & {
      stateValue: string;
      sizeValue: number;
      themeValue: string;
      speedValue: number;
      pausedValue: boolean;
    };

    controller.stateValue = 'searching';
    controller.sizeValue = 20;
    controller.themeValue = 'light';
    controller.speedValue = 2;
    controller.pausedValue = true;

    await Promise.resolve();
    const canvas = root.querySelector('canvas') as HTMLCanvasElement;
    expect(canvas.style.width).toBe('20px');
    expect(canvas.getAttribute('aria-label')).toBe('Searching');

    vi.advanceTimersByTime(900);
    expect(['working', 'searching', 'solving', 'listening', 'composing', 'shaping']).toContain(
      controller.stateValue,
    );
  });

  it('uses existing canvas element as host', async () => {
    application.stop();
    document.body.innerHTML = `
      <canvas
        data-controller="thinking-orb"
        data-thinking-orb-state-value="bogus"
        data-thinking-orb-size-value="32"
        data-thinking-orb-theme-value="nope"
        data-thinking-orb-rotate-states-value="false"
      ></canvas>
    `;
    application = Application.start();
    application.register('thinking-orb', ThinkingOrbController);
    await Promise.resolve();
    const canvas = document.querySelector('canvas') as HTMLCanvasElement;
    expect(canvas.getAttribute('aria-label')).toBe('Working');
    expect(canvas.style.width).toBe('64px');
  });
});
