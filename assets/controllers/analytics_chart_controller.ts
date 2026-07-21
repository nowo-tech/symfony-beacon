import { Controller } from "@hotwired/stimulus";
import {
  Chart,
  LineController,
  LineElement,
  PointElement,
  LinearScale,
  CategoryScale,
  Filler,
  Legend,
  Tooltip,
} from "chart.js";

Chart.register(
  LineController,
  LineElement,
  PointElement,
  LinearScale,
  CategoryScale,
  Filler,
  Legend,
  Tooltip,
);

/**
 * Renders Analytics time-series from JSON in data-analytics-chart-points-value.
 */
export default class extends Controller {
  static targets = ["canvas"];
  static values = {
    points: { type: Array, default: [] },
    labelErrors: { type: String, default: "Errors" },
    labelTransactions: { type: String, default: "Transactions" },
    labelNplus1: { type: String, default: "N+1" },
    filtered: { type: Boolean, default: false },
  };

  declare readonly canvasTarget: HTMLCanvasElement;
  declare readonly hasCanvasTarget: boolean;
  declare readonly pointsValue: Array<{
    date: string;
    errors: number;
    transactions: number | null;
    nplus1: number | null;
  }>;
  declare readonly labelErrorsValue: string;
  declare readonly labelTransactionsValue: string;
  declare readonly labelNplus1Value: string;
  declare readonly filteredValue: boolean;

  private chart: Chart | null = null;

  connect(): void {
    if (!this.hasCanvasTarget) {
      return;
    }
    this.render();
  }

  disconnect(): void {
    this.chart?.destroy();
    this.chart = null;
  }

  private render(): void {
    const labels = this.pointsValue.map((p) => p.date);
    const errors = this.pointsValue.map((p) => p.errors);
    const moss = this.cssColor("--color-moss") || "#1f6f54";
    const ink = this.cssColor("--color-ink") || "#1a1a1a";
    const sand = this.cssColor("--color-sand") || "#d6d0c4";

    const datasets: Array<Record<string, unknown>> = [
      {
        label: this.labelErrorsValue,
        data: errors,
        borderColor: moss,
        backgroundColor: this.withAlpha(moss, 0.12),
        fill: true,
        tension: 0.25,
        pointRadius: labels.length > 60 ? 0 : 2,
        borderWidth: 2,
      },
    ];

    if (!this.filteredValue) {
      datasets.push(
        {
          label: this.labelTransactionsValue,
          data: this.pointsValue.map((p) => p.transactions ?? 0),
          borderColor: ink,
          backgroundColor: "transparent",
          fill: false,
          tension: 0.25,
          pointRadius: labels.length > 60 ? 0 : 2,
          borderWidth: 1.5,
          borderDash: [4, 3],
        },
        {
          label: this.labelNplus1Value,
          data: this.pointsValue.map((p) => p.nplus1 ?? 0),
          borderColor: sand,
          backgroundColor: "transparent",
          fill: false,
          tension: 0.25,
          pointRadius: labels.length > 60 ? 0 : 2,
          borderWidth: 1.5,
        },
      );
    }

    this.chart?.destroy();
    this.chart = new Chart(this.canvasTarget, {
      type: "line",
      data: { labels, datasets: datasets as never },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: "index", intersect: false },
        plugins: {
          legend: {
            position: "top",
            labels: { color: ink, boxWidth: 12, font: { size: 12 } },
          },
          tooltip: { mode: "index", intersect: false },
        },
        scales: {
          x: {
            ticks: {
              color: ink,
              maxRotation: 0,
              autoSkip: true,
              maxTicksLimit: 12,
            },
            grid: { color: this.withAlpha(sand, 0.45) },
          },
          y: {
            beginAtZero: true,
            ticks: {
              color: ink,
              precision: 0,
            },
            grid: { color: this.withAlpha(sand, 0.45) },
          },
        },
      },
    });
  }

  private cssColor(name: string): string {
    return getComputedStyle(document.documentElement).getPropertyValue(name).trim();
  }

  private withAlpha(color: string, alpha: number): string {
    if (color.startsWith("#") && (color.length === 7 || color.length === 4)) {
      const hex =
        color.length === 4
          ? `#${color[1]}${color[1]}${color[2]}${color[2]}${color[3]}${color[3]}`
          : color;
      const r = Number.parseInt(hex.slice(1, 3), 16);
      const g = Number.parseInt(hex.slice(3, 5), 16);
      const b = Number.parseInt(hex.slice(5, 7), 16);
      return `rgba(${r}, ${g}, ${b}, ${alpha})`;
    }
    return color;
  }
}
