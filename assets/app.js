import './stimulus_bootstrap.js';
import 'leaflet/dist/leaflet.min.css';
import './styles/app.css';

/*
 * Apply the CSS data-viz tokens to every Symfony UX Chart.js chart before it
 * renders, so light/dark mode lives in app.css only.
 */
document.addEventListener('chartjs:pre-connect', (event) => {
    const css = getComputedStyle(document.documentElement);
    const token = (name) => css.getPropertyValue(name).trim();
    const { config } = event.detail;
    const kind = event.target.dataset.chartTheme;

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
        scale.grid = { color: token('--grid'), ...(scale.grid ?? {}) };
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
});
