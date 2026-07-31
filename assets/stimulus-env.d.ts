/// <reference types="vite/client" />

/** Markers read by vite-plugin-symfony when controllers are loaded via `?stimulus`. */
interface ImportMeta {
  stimulusFetch: 'lazy' | 'eager';
  stimulusIdentifier: string;
  stimulusEnabled: boolean;
}
