import { startStimulusApp } from 'vite-plugin-symfony/stimulus/helpers';
import type { Application } from '@hotwired/stimulus';
import AnalyticsChartController from './controllers/analytics_chart_controller';
import ClipboardCopyController from './controllers/clipboard_copy_controller';
import CollapsePanelController from './controllers/collapse_panel_controller';
import ComboboxController from './controllers/combobox_controller';
import ConfirmDialogController from './controllers/confirm_dialog_controller';
import DatatableController from './controllers/datatable_controller';
import HumanKeyLabelController from './controllers/human_key_label_controller';
import ProductTourController from './controllers/product_tour_controller';
import ToastStackController from './controllers/toast_stack_controller';

/** Starts Stimulus (UX controllers from controllers.json + local app controllers). */
const app: Application = startStimulusApp();

app.register('analytics-chart', AnalyticsChartController);
app.register('clipboard-copy', ClipboardCopyController);
app.register('collapse-panel', CollapsePanelController);
app.register('combobox', ComboboxController);
app.register('confirm-dialog', ConfirmDialogController);
app.register('datatable', DatatableController);
app.register('human-key-label', HumanKeyLabelController);
app.register('product-tour', ProductTourController);
app.register('toast-stack', ToastStackController);

export { app };
