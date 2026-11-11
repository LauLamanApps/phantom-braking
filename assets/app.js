import './stimulus_bootstrap.js';
import 'leaflet/dist/leaflet.min.css';
import 'leaflet.markercluster/dist/MarkerCluster.css';
import './styles/app.css';

/*
 * Apply the CSS data-viz tokens to every Symfony UX Chart.js chart before it
 * renders, so light/dark mode lives in app.css only. On a theme switch (or the
 * device changing it while no theme is picked) the charts are repainted.
 */
function applyTokens(config, kind) {
    const css = getComputedStyle(document.documentElement);
    const token = (name) => css.getPropertyValue(name).trim();

    config.data.datasets.forEach((dataset) => {
        dataset.backgroundColor = kind === 'line' ? token('--series-1-soft') : token('--series-1');
        dataset.borderColor = token('--series-1');
        dataset.pointBackgroundColor = token('--series-1');
        dataset.pointBorderColor = token('--surface-1');
        dataset.pointBorderWidth = 2;
        dataset.hoverBackgroundColor = token('--series-1');
    });

    const options = config.options ?? (config.options = {});
    options.color = token('--text-secondary');
    Object.values(options.scales ?? {}).forEach((scale) => {
        scale.ticks = { ...(scale.ticks ?? {}), color: token('--text-secondary') };
        scale.grid = { ...(scale.grid ?? {}), color: token('--grid') };
        scale.border = { display: false };
    });
    options.plugins = options.plugins ?? {};
    options.plugins.tooltip = {
        backgroundColor: token('--surface-1'),
        titleColor: token('--text-primary'),
        bodyColor: token('--text-secondary'),
        borderColor: token('--grid'),
        borderWidth: 1,
        padding: 10,
        displayColors: false,
    };
}

const charts = new Map();

document.addEventListener('chartjs:pre-connect', (event) => {
    applyTokens(event.detail.config, event.target.dataset.chartTheme);
});
document.addEventListener('chartjs:connect', (event) => charts.set(event.detail.chart, event.target.dataset.chartTheme));
document.addEventListener('chartjs:disconnect', (event) => charts.delete(event.detail.chart));

window.addEventListener('theme:change', () => {
    charts.forEach((kind, chart) => {
        applyTokens(chart.config, kind);
        chart.update(); // not 'none': that keeps the cached point colours
    });
});
matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
    if (!document.documentElement.dataset.theme) {
        window.dispatchEvent(new CustomEvent('theme:change'));
    }
});
