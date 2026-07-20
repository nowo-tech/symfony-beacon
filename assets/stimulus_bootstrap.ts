import { startStimulusApp } from 'vite-plugin-symfony/stimulus/helpers';
import type { Application } from '@hotwired/stimulus';
import ClipboardCopyController from './controllers/clipboard_copy_controller';
import CollapsePanelController from './controllers/collapse_panel_controller';
import ConfirmDialogController from './controllers/confirm_dialog_controller';
import DatatableController from './controllers/datatable_controller';
import HumanKeyLabelController from './controllers/human_key_label_controller';

/** Starts Stimulus (UX controllers from controllers.json + local app controllers). */
const app: Application = startStimulusApp();

app.register('clipboard-copy', ClipboardCopyController);
app.register('collapse-panel', CollapsePanelController);
app.register('confirm-dialog', ConfirmDialogController);
app.register('datatable', DatatableController);
app.register('human-key-label', HumanKeyLabelController);

export { app };
