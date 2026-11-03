import Chart from 'chart.js/auto';

export function initialiseForecastChart() {
    const canvas = document.getElementById('executive-forecast-chart');
    const payload = document.getElementById('executive-forecast-data');

    if (!canvas || !payload) {
        return;
    }

    const data = JSON.parse(payload.textContent);
    const styles = getComputedStyle(document.body);
    const textColour = styles.getPropertyValue('--bs-body-color').trim() || '#212529';
    const currentColour = styles.getPropertyValue('--bs-secondary-color').trim() || '#6c757d';
    const gridColour = styles.getPropertyValue('--bs-border-color').trim() || '#dee2e6';

    const barLabels = {
        id: 'forecastBarLabels',
        afterDatasetsDraw(chart) {
            const context = chart.ctx;
            context.save();
            context.font = `12px ${Chart.defaults.font.family}`;
            context.fillStyle = textColour;
            context.textBaseline = 'middle';

            chart.data.datasets.forEach((dataset, datasetIndex) => {
                chart.getDatasetMeta(datasetIndex).data.forEach((bar, rowIndex) => {
                    const row = data.rows[rowIndex];
                    const value = dataset.data[rowIndex];

                    if (value === null) {
                        return;
                    }

                    const risk = datasetIndex === 0 ? row.currentRisk : row.estimatedRisk;
                    context.fillText(`${value.toFixed(2)} · ${risk}`, bar.x + 8, bar.y);
                });
            });

            context.restore();
        },
    };

    new Chart(canvas, {
        type: 'bar',
        data: {
            labels: data.rows.map(row => [row.area, row.direction ?? 'No forecast']),
            datasets: [
                { label: `Current · ${data.currentDate}`, data: data.rows.map(row => row.current), backgroundColor: currentColour },
                { label: `Forecast · ${data.forecastDate}`, data: data.rows.map(row => row.estimate), backgroundColor: data.rows.map(row => row.colour ?? currentColour) },
            ],
        },
        plugins: [barLabels],
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            animation: false,
            layout: { padding: { right: 125 } },
            datasets: { bar: { maxBarThickness: 18, borderWidth: 0, categoryPercentage: 0.7 } },
            interaction: { mode: 'index', intersect: false },
            scales: {
                x: {
                    min: 0,
                    max: 3,
                    title: { display: true, text: 'Domain maturity score (0–3)', color: textColour },
                    ticks: { stepSize: 0.5, color: textColour },
                    grid: { color: gridColour },
                },
                y: { ticks: { color: textColour, font: { size: 12 }, autoSkip: false }, grid: { display: false } },
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        title: items => data.rows[items[0].dataIndex].area,
                        label: item => {
                            const row = data.rows[item.dataIndex];
                            const risk = item.datasetIndex === 0 ? row.currentRisk : row.estimatedRisk;

                            return `${item.dataset.label}: ${item.parsed.x.toFixed(2)} / 3 · ${risk}`;
                        },
                    },
                },
            },
        },
    });
}
