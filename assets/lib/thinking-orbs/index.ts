/**
 * Framework-agnostic Thinking Orbs engine for Beacon.
 *
 * Vendored (MIT) from https://github.com/Jakubantalik/thinking-orbs
 * Demo: https://orbs.jakubantalik.com/
 *
 * React is intentionally not used — Stimulus drives a plain `<canvas>`.
 */

export { MODE_DRAWS } from './engine/registry';
export { resolvePreset, STATE_TO_MODE, type ModeKey, type Resolved } from './presets';
export { prefersReducedMotion, resolveDark, watchThemeAndMotion } from './theme';
export {
  ORB_LABELS,
  ORB_STATES,
  type OrbSize,
  type OrbState,
  type OrbTheme,
} from './types';
