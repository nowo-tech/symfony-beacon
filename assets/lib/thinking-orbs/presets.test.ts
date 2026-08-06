import { describe, expect, it } from 'vitest';
import { resolvePreset, STATE_TO_MODE } from './presets';
import type { OrbSize, OrbState } from './types';

describe('resolvePreset', () => {
  const states: OrbState[] = [
    'working',
    'searching',
    'solving',
    'listening',
    'composing',
    'shaping',
  ];
  const sizes: OrbSize[] = [64, 20];

  it.each(states)('maps %s to expected mode', (state) => {
    expect(resolvePreset(state, 64).mode).toBe(STATE_TO_MODE[state]);
  });

  it('returns cached object for same state/size', () => {
    const a = resolvePreset('working', 64);
    const b = resolvePreset('working', 64);
    expect(a).toBe(b);
  });

  it.each(sizes)('produces positive speed and opts for size %s', (size) => {
    for (const state of states) {
      const resolved = resolvePreset(state, size);
      expect(resolved.speed).toBeGreaterThan(0);
      expect(Object.keys(resolved.opts).length).toBeGreaterThan(0);
    }
  });

  it('applies ribbon extras for composing', () => {
    const resolved = resolvePreset('composing', 64);
    expect(resolved.mode).toBe('ribbon');
    expect(resolved.opts.bandMul).toBe(3.9);
  });
});
