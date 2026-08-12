import { Application } from '@hotwired/stimulus';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const { Chart, destroyMock, chartCalls } = vi.hoisted(() => {
  const destroyMock = vi.fn();
  const chartCalls: unknown[][] = [];
  class Chart {
    static register = vi.fn();
    destroy = destroyMock;
    constructor(...args: unknown[]) {
      chartCalls.push(args);
    }
  }
  return { Chart, destroyMock, chartCalls };
});

vi.mock('chart.js', () => ({
  Chart,
  LineController: {},
  LineElement: {},
  PointElement: {},
  LinearScale: {},
  CategoryScale: {},
  Filler: {},
  Legend: {},
  Tooltip: {},
}));

import AnalyticsChartController from './analytics_chart_controller';

describe('analytics-chart controller', () => {
  let application: Application;

  beforeEach(() => {
    chartCalls.length = 0;
    destroyMock.mockClear();
    document.body.innerHTML = `
      <div
        data-controller="analytics-chart"
        data-analytics-chart-points-value='[{"date":"2026-01-01","errors":2,"transactions":3,"nplus1":1},{"date":"2026-01-02","errors":1,"transactions":null,"nplus1":null}]'
        data-analytics-chart-filtered-value="false"
      >
        <canvas data-analytics-chart-target="canvas"></canvas>
      </div>
    `;
    application = Application.start();
    application.register('analytics-chart', AnalyticsChartController);
  });

  afterEach(() => {
    application.stop();
    document.body.innerHTML = '';
  });

  it('creates a chart on connect and destroys on disconnect', async () => {
    await Promise.resolve();
    expect(chartCalls).toHaveLength(1);
    const config = chartCalls[0][1] as {
      data: { datasets: unknown[]; labels: string[] };
    };
    expect(config.data.labels).toEqual(['2026-01-01', '2026-01-02']);
    expect(config.data.datasets).toHaveLength(3);

    const el = document.querySelector('[data-controller="analytics-chart"]') as HTMLElement;
    const controller = application.getControllerForElementAndIdentifier(
      el,
      'analytics-chart',
    ) as AnalyticsChartController;
    controller.disconnect();
    expect(destroyMock).toHaveBeenCalled();
  });

  it('renders only errors dataset when filtered', async () => {
    application.stop();
    chartCalls.length = 0;
    document.body.innerHTML = `
      <div
        data-controller="analytics-chart"
        data-analytics-chart-points-value='[{"date":"d","errors":1,"transactions":1,"nplus1":1}]'
        data-analytics-chart-filtered-value="true"
      >
        <canvas data-analytics-chart-target="canvas"></canvas>
      </div>
    `;
    application = Application.start();
    application.register('analytics-chart', AnalyticsChartController);
    await Promise.resolve();
    const config = chartCalls[0][1] as { data: { datasets: unknown[] } };
    expect(config.data.datasets).toHaveLength(1);
  });

  it('no-ops without canvas target', async () => {
    application.stop();
    chartCalls.length = 0;
    document.body.innerHTML = `<div data-controller="analytics-chart"></div>`;
    application = Application.start();
    application.register('analytics-chart', AnalyticsChartController);
    await Promise.resolve();
    expect(chartCalls).toHaveLength(0);
  });
});
