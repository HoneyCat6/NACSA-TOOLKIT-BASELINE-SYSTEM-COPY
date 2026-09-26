import * as bootstrap from 'bootstrap';
import Alpine from 'alpinejs';
import Chart from 'chart.js/auto';

window.bootstrap = bootstrap;
window.Chart = Chart;
window.Alpine = Alpine;

document.addEventListener('DOMContentLoaded', () => Alpine.start());
