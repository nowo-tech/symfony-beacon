import { describe, expect, it } from 'vitest';
import { BASE_PROFILES, scaleCounts, scaleRadii } from './profiles';

describe('scaleCounts', () => {
  it('scales lattice pairs by sqrt', () => {
    const scaled = scaleCounts({ latRings: 16, lonDensity: 36 }, 0.25);
    expect(scaled.latRings).toBe(8);
    expect(scaled.lonDensity).toBe(18);
  });

  it('scales flat counts linearly with floor', () => {
    const scaled = scaleCounts({ orbitN: 12, ghostN: 40 }, 0.5);
    expect(scaled.orbitN).toBe(6);
    expect(scaled.ghostN).toBe(20);
  });

  it('scales icon density with minimum', () => {
    expect(scaleCounts({ iconD: 1 }, 0.01).iconD).toBe(0.02);
  });
});

describe('scaleRadii', () => {
  it('multiplies radius keys and tracks rSizeMul', () => {
    const scaled = scaleRadii({ rBase: 1, rDepth: 2, rSizeMul: 1 }, 1.5);
    expect(scaled.rBase).toBe(1.5);
    expect(scaled.rDepth).toBe(3);
    expect(scaled.rSizeMul).toBe(1.5);
  });
});

describe('BASE_PROFILES', () => {
  it('defines all shipped modes', () => {
    for (const mode of ['globe', 'orbits', 'rubik', 'wave', 'ribbon', 'morph']) {
      expect(BASE_PROFILES[mode]).toBeDefined();
    }
  });
});
