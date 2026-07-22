import {
    Chart,
    LineController,
    LineElement,
    PointElement,
    LinearScale,
    CategoryScale,
    BarController,
    BarElement,
    DoughnutController,
    ArcElement,
    Tooltip,
    Filler,
} from 'chart.js';

Chart.register(
    LineController, LineElement, PointElement, LinearScale, CategoryScale,
    BarController, BarElement, DoughnutController, ArcElement, Tooltip, Filler,
);

// Expose a tiny helper so any view can render a chart declaratively.
window.ppChart = function (el, config) {
    return new Chart(el, config);
};

// Alpine `x-data="chart(config)"` component (works with Livewire-bundled or standalone Alpine).
document.addEventListener('alpine:init', () => {
    window.Alpine.data('chart', (config) => ({
        instance: null,
        init() {
            this.instance = new Chart(this.$refs.canvas, config);
        },
        destroy() {
            this.instance?.destroy();
        },
    }));
});
