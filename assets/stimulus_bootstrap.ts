import { createLazyController, startStimulusApp } from 'vite-plugin-symfony/stimulus/helpers';
import type { Application } from '@hotwired/stimulus';
import ClipboardCopyController from './controllers/clipboard_copy_controller';
import CollapsePanelController from './controllers/collapse_panel_controller';
import ComboboxController from './controllers/combobox_controller';
import ConfirmDialogController from './controllers/confirm_dialog_controller';
import ConfirmSubmitController from './controllers/confirm_submit_controller';
// Side-effect: SameOrigin CSRF cookie on form submit (Symfony stateless CSRF).
import './controllers/csrf_protection_controller';
import HumanKeyLabelController from './controllers/human_key_label_controller';
import IssuePanelsResetController from './controllers/issue_panels_reset_controller';
import IssueRealtimeController from './controllers/issue_realtime_controller';
import MenuNestedCollapseController from './controllers/menu_nested_collapse_controller';
import NavigateSelectController from './controllers/navigate_select_controller';
import PageLoaderController from './controllers/page_loader_controller';
import PasswordConfirmMirrorController from './controllers/password_confirm_mirror_controller';
import PasswordToggleController from './controllers/password_toggle_controller';
import QrLoginController from './controllers/qr_login_controller';
import TabsController from './controllers/tabs_controller';
import TemporaryRevealController from './controllers/temporary_reveal_controller';
import ThinkingOrbController from './controllers/thinking_orb_controller';
import ToastStackController from './controllers/toast_stack_controller';

/** Starts Stimulus (UX controllers from controllers.json + local app controllers). */
const app: Application = startStimulusApp();

// Expose for kit dashboards (dashboard-menu Live modals) — avoid CDN stimulus-live.js / esm.sh.
const stimulusGlobals = window as Window & {
  Stimulus?: Application;
  $$stimulusApp$$?: Application;
  __stimulusApp__?: Application;
};
stimulusGlobals.Stimulus = app;
stimulusGlobals.$$stimulusApp$$ = app;
stimulusGlobals.__stimulusApp__ = app;

app.register('clipboard-copy', ClipboardCopyController);
app.register('collapse-panel', CollapsePanelController);
app.register('combobox', ComboboxController);
app.register('confirm-dialog', ConfirmDialogController);
app.register('confirm-submit', ConfirmSubmitController);
app.register('human-key-label', HumanKeyLabelController);
app.register('issue-panels-reset', IssuePanelsResetController);
app.register('issue-realtime', IssueRealtimeController);
app.register('menu-nested-collapse', MenuNestedCollapseController);
app.register('navigate-select', NavigateSelectController);
app.register('page-loader', PageLoaderController);
app.register('password-confirm-mirror', PasswordConfirmMirrorController);
app.register('password-toggle', PasswordToggleController);
app.register('qr-login', QrLoginController);
app.register('tabs', TabsController);
app.register('temporary-reveal', TemporaryRevealController);
app.register('thinking-orb', ThinkingOrbController);
app.register('toast-stack', ToastStackController);

// Heavy libs (chart.js, DataTables, driver.js): load only when the controller is on the page.
app.register('analytics-chart', createLazyController(() => import('./controllers/analytics_chart_controller')));
app.register('datatable', createLazyController(() => import('./controllers/datatable_controller')));
app.register('product-tour', createLazyController(() => import('./controllers/product_tour_controller')));

export { app };
