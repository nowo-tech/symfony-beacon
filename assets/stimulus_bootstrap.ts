import { createLazyController, startStimulusApp } from 'vite-plugin-symfony/stimulus/helpers';
import type { Application } from '@hotwired/stimulus';
import ClipboardCopyController from './controllers/clipboard_copy_controller';
import CollapsePanelController from './controllers/collapse_panel_controller';
import ComboboxController from './controllers/combobox_controller';
import ConfirmDialogController from './controllers/confirm_dialog_controller';
import HumanKeyLabelController from './controllers/human_key_label_controller';
import IssueRealtimeController from './controllers/issue_realtime_controller';
import PageLoaderController from './controllers/page_loader_controller';
import ThinkingOrbController from './controllers/thinking_orb_controller';
import ToastStackController from './controllers/toast_stack_controller';

/** Starts Stimulus (UX controllers from controllers.json + local app controllers). */
const app: Application = startStimulusApp();

app.register('clipboard-copy', ClipboardCopyController);
app.register('collapse-panel', CollapsePanelController);
app.register('combobox', ComboboxController);
app.register('confirm-dialog', ConfirmDialogController);
app.register('human-key-label', HumanKeyLabelController);
app.register('issue-realtime', IssueRealtimeController);
app.register('page-loader', PageLoaderController);
app.register('thinking-orb', ThinkingOrbController);
app.register('toast-stack', ToastStackController);

// Heavy libs (chart.js, DataTables, driver.js): load only when the controller is on the page.
app.register('analytics-chart', createLazyController(() => import('./controllers/analytics_chart_controller')));
app.register('datatable', createLazyController(() => import('./controllers/datatable_controller')));
app.register('product-tour', createLazyController(() => import('./controllers/product_tour_controller')));

export { app };
