import { Controller } from "@hotwired/stimulus";
import DataTable from "datatables.net-dt";
import "datatables.net-responsive-dt";
import "datatables.net-dt/css/dataTables.dataTables.min.css";
import "datatables.net-responsive-dt/css/responsive.dataTables.min.css";

/**
 * DataTables for issues index — responsive layout only.
 * Sorting and paging are server-side (filter form + query string).
 *
 * Controller lives on a stable wrapper (not the <table>): DataTables wraps/moves
 * the table node on init, which would otherwise Stimulus-disconnect/reconnect.
 */
export default class extends Controller {
  static targets = ["table"];

  declare readonly tableTarget: HTMLTableElement;
  declare readonly hasTableTarget: boolean;

  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  private table: any = null;
  private mountFrame: number | null = null;

  connect(): void {
    this.mountFrame = window.requestAnimationFrame(() => {
      this.mountFrame = null;
      this.mount();
    });
  }

  disconnect(): void {
    if (this.mountFrame !== null) {
      window.cancelAnimationFrame(this.mountFrame);
      this.mountFrame = null;
    }

    if (this.table) {
      this.table.destroy(false);
      this.table = null;
    }
  }

  /** Keep Responsive from toggling the child row when the title link is clicked. */
  openIssue(event: Event): void {
    event.stopPropagation();
  }

  private mount(): void {
    if (!this.hasTableTarget || this.table) {
      return;
    }

    const element = this.tableTarget;
    if (!(element instanceof HTMLTableElement)) {
      return;
    }

    if (DataTable.isDataTable?.(element)) {
      return;
    }

    this.table = new DataTable(element, {
      autoWidth: false,
      deferRender: true,
      scrollX: false,
      paging: false,
      searching: false,
      ordering: false,
      info: false,
      lengthChange: false,
      layout: {
        topStart: null,
        topEnd: null,
        bottomStart: null,
        bottomEnd: null,
      },
      responsive: {
        details: {
          type: "inline",
        },
      },
      columnDefs: [
        { responsivePriority: 1, targets: 0 },
        { responsivePriority: 2, targets: 1 },
        { responsivePriority: 3, targets: 3 },
        { responsivePriority: 4, targets: 8 },
        { responsivePriority: 6, targets: 2 },
        { responsivePriority: 7, targets: 7 },
        { responsivePriority: 8, targets: [4, 5, 6] },
        { targets: [3, 4, 5, 6], className: "dt-body-right dt-head-right" },
        { targets: [7, 8], className: "issue-table__date" },
      ],
    });
  }
}
