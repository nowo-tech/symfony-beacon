import { Controller } from "@hotwired/stimulus";
import DataTable from "datatables.net-dt";
import "datatables.net-responsive-dt";
import "datatables.net-dt/css/dataTables.dataTables.min.css";
import "datatables.net-responsive-dt/css/responsive.dataTables.min.css";

const SORT_KEYS = [
  "title",
  "level",
  "assignee",
  "events",
  "events_24h",
  "events_7d",
  "events_30d",
  "first_seen",
  "last_seen",
] as const;

type SortKey = (typeof SORT_KEYS)[number];

/**
 * DataTables (responsive + paging) for the issues index.
 * Keeps sort / dir / page / per_page in the query string for refreshable state.
 */
export default class extends Controller {
  static values = {
    sort: { type: String, default: "last_seen" },
    dir: { type: String, default: "desc" },
    page: { type: Number, default: 1 },
    perPage: { type: Number, default: 25 },
    empty: { type: String, default: "No matching records" },
    info: { type: String, default: "Showing _START_ to _END_ of _TOTAL_" },
    infoEmpty: { type: String, default: "Showing 0 to 0 of 0" },
    infoFiltered: { type: String, default: "(filtered from _MAX_ total)" },
    lengthMenu: { type: String, default: "Show _MENU_" },
    paginateFirst: { type: String, default: "First" },
    paginateLast: { type: String, default: "Last" },
    paginateNext: { type: String, default: "Next" },
    paginatePrevious: { type: String, default: "Previous" },
    zeroRecords: { type: String, default: "No matching issues" },
  };

  declare readonly sortValue: string;
  declare readonly dirValue: string;
  declare readonly pageValue: number;
  declare readonly perPageValue: number;
  declare readonly emptyValue: string;
  declare readonly infoValue: string;
  declare readonly infoEmptyValue: string;
  declare readonly infoFilteredValue: string;
  declare readonly lengthMenuValue: string;
  declare readonly paginateFirstValue: string;
  declare readonly paginateLastValue: string;
  declare readonly paginateNextValue: string;
  declare readonly paginatePreviousValue: string;
  declare readonly zeroRecordsValue: string;

  // DataTables API instance (typed loosely; package typings vary by entrypoint).
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  private table: any = null;

  connect(): void {
    if (!(this.element instanceof HTMLTableElement)) {
      return;
    }

    const orderCol = this.columnIndex(this.sortValue);
    const orderDir = this.dirValue === "asc" ? "asc" : "desc";
    const pageLength = this.normalizeLength(this.perPageValue);
    const startPage = Math.max(1, this.pageValue);

    this.table = new DataTable(this.element, {
      autoWidth: false,
      deferRender: true,
      layout: {
        topStart: "pageLength",
        topEnd: null,
        bottomStart: "info",
        bottomEnd: "paging",
      },
      order: [[orderCol, orderDir]],
      pageLength,
      displayStart: (startPage - 1) * pageLength,
      lengthMenu: [10, 25, 50, 100],
      paging: true,
      searching: false,
      responsive: true,
      columnDefs: [
        { targets: [3, 4, 5, 6], className: "dt-body-right dt-head-right" },
      ],
      language: {
        emptyTable: this.emptyValue,
        info: this.infoValue,
        infoEmpty: this.infoEmptyValue,
        infoFiltered: this.infoFilteredValue,
        lengthMenu: this.lengthMenuValue,
        zeroRecords: this.zeroRecordsValue,
        paginate: {
          first: this.paginateFirstValue,
          last: this.paginateLastValue,
          next: this.paginateNextValue,
          previous: this.paginatePreviousValue,
        },
      },
    });

    this.table.on("order.dt length.dt page.dt", () => {
      this.writeUrlState();
    });
  }

  disconnect(): void {
    if (this.table) {
      this.table.destroy(false);
      this.table = null;
    }
  }

  private columnIndex(sort: string): number {
    const idx = SORT_KEYS.indexOf(sort as SortKey);

    return idx >= 0 ? idx : SORT_KEYS.indexOf("last_seen");
  }

  private sortKey(index: number): SortKey {
    return SORT_KEYS[index] ?? "last_seen";
  }

  private normalizeLength(value: number): number {
    return [10, 25, 50, 100].includes(value) ? value : 25;
  }

  private writeUrlState(): void {
    if (!this.table || !window.history?.replaceState) {
      return;
    }

    const order = this.table.order();
    const col = Array.isArray(order?.[0]) ? Number(order[0][0]) : 8;
    const dir = Array.isArray(order?.[0]) && order[0][1] === "asc" ? "asc" : "desc";
    const info = this.table.page.info();
    const params = new URLSearchParams(window.location.search);

    params.set("sort", this.sortKey(col));
    params.set("dir", dir);
    params.set("page", String((info?.page ?? 0) + 1));
    params.set("per_page", String(info?.length ?? this.perPageValue));

    for (const key of ["q", "level", "status", "environment", "assignee"]) {
      const value = params.get(key);
      if (value === null || value === "") {
        params.delete(key);
      }
    }

    const query = params.toString();
    const next = `${window.location.pathname}${query ? `?${query}` : ""}`;
    window.history.replaceState({}, "", next);
    this.syncHiddenFields(params);
  }

  private syncHiddenFields(params: URLSearchParams): void {
    const form = document.querySelector<HTMLFormElement>("form.issue-filters");
    if (!form) {
      return;
    }

    for (const name of ["sort", "dir", "per_page"] as const) {
      const input = form.querySelector<HTMLInputElement>(`input[name="${name}"]`);
      const value = params.get(name);
      if (input && value !== null) {
        input.value = value;
      }
    }

    const pageInput = form.querySelector<HTMLInputElement>('input[name="page"]');
    if (pageInput) {
      // Filtering should restart at the first page.
      pageInput.value = "1";
    }
  }
}
