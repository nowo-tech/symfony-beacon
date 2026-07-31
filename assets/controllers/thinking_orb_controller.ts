import { Controller } from '@hotwired/stimulus';
import {
  MODE_DRAWS,
  ORB_LABELS,
  resolvePreset,
  watchThemeAndMotion,
  type OrbSize,
  type OrbState,
  type OrbTheme,
} from '../lib/thinking-orbs';

const STATES: readonly OrbState[] = [
  'working',
  'searching',
  'solving',
  'listening',
  'composing',
  'shaping',
];

/**
 * Renders a Thinking Orb (https://orbs.jakubantalik.com/) on a canvas.
 *
 * Values: state, size (20|64), theme (auto|dark|light), speed, paused.
 * Optional: rotateStates — cycles through all six states (page loader).
 */
export default class extends Controller {
  static values = {
    state: { type: String, default: 'working' },
    size: { type: Number, default: 64 },
    theme: { type: String, default: 'auto' },
    speed: { type: Number, default: 1 },
    paused: { type: Boolean, default: false },
    rotateStates: { type: Boolean, default: false },
    rotateInterval: { type: Number, default: 2800 },
    label: { type: String, default: '' },
  };

  declare readonly stateValue: string;
  declare readonly sizeValue: number;
  declare readonly themeValue: string;
  declare readonly speedValue: number;
  declare readonly pausedValue: boolean;
  declare readonly rotateStatesValue: boolean;
  declare readonly rotateIntervalValue: number;
  declare readonly labelValue: string;

  private canvas: HTMLCanvasElement | null = null;
  private stopWatch: (() => void) | null = null;
  private stopLoop: (() => void) | null = null;
  private rotateTimer: number | null = null;
  private dark = true;
  private reduced = false;
  private rotateIndex = 0;

  connect(): void {
    this.ensureCanvas();
    this.syncAria();
    this.stopWatch = watchThemeAndMotion(this.resolveTheme(), this.canvas, (dark, reduced) => {
      this.dark = dark;
      this.reduced = reduced;
      this.restartLoop();
    });
    if (this.rotateStatesValue) {
      this.startRotation();
    }
  }

  disconnect(): void {
    this.stopWatch?.();
    this.stopWatch = null;
    this.stopLoop?.();
    this.stopLoop = null;
    this.clearRotation();
  }

  stateValueChanged(): void {
    this.syncAria();
    this.restartLoop();
  }

  sizeValueChanged(): void {
    this.restartLoop();
  }

  themeValueChanged(): void {
    this.stopWatch?.();
    this.stopWatch = watchThemeAndMotion(this.resolveTheme(), this.canvas, (dark, reduced) => {
      this.dark = dark;
      this.reduced = reduced;
      this.restartLoop();
    });
  }

  speedValueChanged(): void {
    this.restartLoop();
  }

  pausedValueChanged(): void {
    this.restartLoop();
  }

  private ensureCanvas(): void {
    if (this.element instanceof HTMLCanvasElement) {
      this.canvas = this.element;
      return;
    }
    let canvas = this.element.querySelector('canvas');
    if (!(canvas instanceof HTMLCanvasElement)) {
      canvas = document.createElement('canvas');
      this.element.appendChild(canvas);
    }
    this.canvas = canvas;
  }

  private resolveState(): OrbState {
    const raw = this.stateValue as OrbState;
    return STATES.includes(raw) ? raw : 'working';
  }

  private resolveSize(): OrbSize {
    return this.sizeValue === 20 ? 20 : 64;
  }

  private resolveTheme(): OrbTheme {
    const t = this.themeValue;
    return t === 'dark' || t === 'light' || t === 'auto' ? t : 'auto';
  }

  private syncAria(): void {
    const canvas = this.canvas;
    if (!canvas) {
      return;
    }
    const state = this.resolveState();
    const label = this.labelValue.trim() || ORB_LABELS[state];
    canvas.setAttribute('role', 'img');
    canvas.setAttribute('aria-label', label);
    const size = this.resolveSize();
    canvas.style.width = `${size}px`;
    canvas.style.height = `${size}px`;
    canvas.style.display = 'block';
  }

  private restartLoop(): void {
    this.stopLoop?.();
    this.stopLoop = null;
    const canvas = this.canvas;
    if (!canvas) {
      return;
    }

    const size = this.resolveSize();
    const state = this.resolveState();
    const dpr = Math.min(2, (typeof devicePixelRatio !== 'undefined' && devicePixelRatio) || 1);
    canvas.width = Math.round(size * dpr);
    canvas.height = Math.round(size * dpr);
    const ctx = canvas.getContext('2d');
    if (!ctx) {
      return;
    }

    const { mode, speed: baseSpeed, opts } = resolvePreset(state, size);
    const draw = MODE_DRAWS[mode];
    const effSpeed = baseSpeed * (Number.isFinite(this.speedValue) ? this.speedValue : 1);

    const frame = (tSec: number): void => {
      ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
      ctx.clearRect(0, 0, size, size);
      draw(ctx, size, tSec, this.dark, opts);
    };

    if (this.reduced) {
      frame(0.6);
      return;
    }

    let raf = 0;
    let running = false;
    const loop = (): void => {
      frame((performance.now() / 1000) * effSpeed);
      if (running) {
        raf = requestAnimationFrame(loop);
      }
    };
    const start = (): void => {
      if (running || this.pausedValue) {
        return;
      }
      running = true;
      raf = requestAnimationFrame(loop);
    };
    const stop = (): void => {
      running = false;
      cancelAnimationFrame(raf);
    };

    frame((performance.now() / 1000) * effSpeed);

    let visible = true;
    const io =
      typeof IntersectionObserver !== 'undefined'
        ? new IntersectionObserver(([entry]) => {
            visible = entry.isIntersecting;
            if (visible && document.visibilityState !== 'hidden') {
              start();
            } else {
              stop();
            }
          })
        : null;
    io?.observe(canvas);
    const onVis = (): void => {
      if (document.visibilityState === 'hidden') {
        stop();
      } else if (visible) {
        start();
      }
    };
    document.addEventListener('visibilitychange', onVis);
    if (!io) {
      start();
    }

    this.stopLoop = () => {
      stop();
      io?.disconnect();
      document.removeEventListener('visibilitychange', onVis);
    };
  }

  private startRotation(): void {
    this.clearRotation();
    const interval = Math.max(800, this.rotateIntervalValue || 2800);
    this.rotateTimer = window.setInterval(() => {
      this.rotateIndex = (this.rotateIndex + 1) % STATES.length;
      this.stateValue = STATES[this.rotateIndex];
    }, interval);
  }

  private clearRotation(): void {
    if (this.rotateTimer !== null) {
      window.clearInterval(this.rotateTimer);
      this.rotateTimer = null;
    }
  }
}
