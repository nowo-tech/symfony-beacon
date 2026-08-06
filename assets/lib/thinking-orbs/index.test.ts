import { describe, expect, it } from 'vitest';
import { ORB_STATES, resolvePreset, resolveDark } from './index';

describe('thinking-orbs public API', () => {
  it('re-exports states and helpers', () => {
    expect(ORB_STATES.length).toBeGreaterThan(0);
    expect(resolvePreset('working', 64).mode).toBe('orbits');
    expect(resolveDark('light', null)).toBe(false);
  });
});
