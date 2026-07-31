/**
 * Thinking Orbs types (framework-agnostic).
 *
 * Upstream: https://github.com/Jakubantalik/thinking-orbs (MIT)
 * Demo: https://orbs.jakubantalik.com/
 */

/**
 * The six shipped states — each a hand-tuned animation:
 * - `working`   — particles on tilted orbits
 * - `searching` — a scan meridian sweeps a dotted globe
 * - `solving`   — bands scramble in quarter turns, then click back
 * - `listening` — a waveform rolls through latitude rings
 * - `composing` — an undulating multi-band sash
 * - `shaping`   — a dotted outline morphs circle → triangle → square
 */
export type OrbState = 'working' | 'searching' | 'solving' | 'listening' | 'composing' | 'shaping';

/**
 * Rendered size in CSS pixels. Exactly two tuned presets ship:
 * 64 (chat-avatar scale) and 20 (inline-text scale).
 */
export type OrbSize = 64 | 20;

/**
 * Theme mode.
 *
 * - `auto` (default) resolves from ancestor `data-theme` / `.dark`|`.light`,
 *   then `prefers-color-scheme`.
 * - `dark` / `light` pin the palette regardless of context.
 */
export type OrbTheme = 'auto' | 'dark' | 'light';

export const ORB_STATES: readonly OrbState[] = [
  'working',
  'searching',
  'solving',
  'listening',
  'composing',
  'shaping',
] as const;

export const ORB_LABELS: Record<OrbState, string> = {
  working: 'Working…',
  searching: 'Searching…',
  solving: 'Solving…',
  listening: 'Listening…',
  composing: 'Composing…',
  shaping: 'Shaping…',
};
