import { startStimulusApp } from 'vite-plugin-symfony/stimulus/helpers';
import type { Application } from '@hotwired/stimulus';

/** Starts Stimulus and registers UX controllers from assets/controllers.json. */
const app: Application = startStimulusApp();

// Register any custom, third-party controllers here:
// app.register('some_controller_name', SomeImportedController);

export { app };
