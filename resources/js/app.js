import * as bootstrap from 'bootstrap';
import Alpine from 'alpinejs';
import Chart from 'chart.js/auto';
import { initialiseForecastChart } from './forecast-chart';

window.bootstrap = bootstrap;
window.Chart = Chart;
window.Alpine = Alpine;

document.addEventListener('DOMContentLoaded', () => {
    Alpine.start();
    initialiseForecastChart();
});
