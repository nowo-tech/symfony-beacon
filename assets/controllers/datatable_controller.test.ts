import { Application } from '@hotwired/stimulus';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const { DataTable, destroyMock, isDataTable, dtCalls } = vi.hoisted(() => {
  const destroyMock = vi.fn();
  const isDataTable = vi.fn(() => false);
  const dtCalls: unknown[][] = [];
  class DataTable {
    static isDataTable = isDataTable;
    destroy = destroyMock;
    constructor(...args: unknown[]) {
      dtCalls.push(args);
    }
  }
  return { DataTable, destroyMock, isDataTable, dtCalls };
});

vi.mock('datatables.net-dt', () => ({ default: DataTable }));
vi.mock('datatables.net-responsive-dt', () => ({}));
vi.mock('datatables.net-dt/css/dataTables.dataTables.min.css', () => ({}));
vi.mock('datatables.net-responsive-dt/css/responsive.dataTables.min.css', () => ({}));

import DatatableController from './datatable_controller';

describe('datatable controller', () => {
  let application: Application;

  beforeEach(() => {
    dtCalls.length = 0;
    destroyMock.mockClear();
    isDataTable.mockReturnValue(false);
    vi.stubGlobal('requestAnimationFrame', (cb: FrameRequestCallback) => {
      cb(0);
      return 1;
    });
    vi.stubGlobal('cancelAnimationFrame', vi.fn());
    document.body.innerHTML = `
      <div data-controller="datatable">
        <table data-datatable-target="table">
          <thead><tr><th>A</th></tr></thead>
          <tbody><tr><td><a href="/i/1" data-action="datatable#openIssue">Issue</a></td></tr></tbody>
        </table>
      </div>
    `;
    application = Application.start();
    application.register('datatable', DatatableController);
  });

  afterEach(() => {
    application.stop();
    document.body.innerHTML = '';
    vi.unstubAllGlobals();
  });

  it('mounts DataTable after rAF and destroys on disconnect', async () => {
    await Promise.resolve();
    expect(dtCalls).toHaveLength(1);
    const options = dtCalls[0][1] as { paging: boolean; ordering: boolean };
    expect(options.paging).toBe(false);
    expect(options.ordering).toBe(false);

    const el = document.querySelector('[data-controller="datatable"]') as HTMLElement;
    const controller = application.getControllerForElementAndIdentifier(
      el,
      'datatable',
    ) as DatatableController;
    controller.disconnect();
    expect(destroyMock).toHaveBeenCalledWith(false);
  });

  it('stops propagation on openIssue', async () => {
    await Promise.resolve();
    const el = document.querySelector('[data-controller="datatable"]') as HTMLElement;
    const controller = application.getControllerForElementAndIdentifier(
      el,
      'datatable',
    ) as DatatableController;
    const event = new Event('click', { bubbles: true, cancelable: true });
    const stop = vi.spyOn(event, 'stopPropagation');
    controller.openIssue(event);
    expect(stop).toHaveBeenCalled();
  });

  it('skips mount when already a DataTable', async () => {
    application.stop();
    dtCalls.length = 0;
    isDataTable.mockReturnValue(true);
    document.body.innerHTML = `
      <div data-controller="datatable">
        <table data-datatable-target="table"><thead><tr><th>A</th></tr></thead></table>
      </div>
    `;
    application = Application.start();
    application.register('datatable', DatatableController);
    await Promise.resolve();
    expect(dtCalls).toHaveLength(0);
  });
});
