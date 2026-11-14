let plotlyPromise;
function loadPlotly() {
    if (window.Plotly) return Promise.resolve(window.Plotly);
    if (plotlyPromise) return plotlyPromise;
    const source = document.querySelector('meta[name="gc-plotly-src"]')?.content;
    if (!source) return Promise.reject(new Error('Источник графиков не настроен'));
    plotlyPromise = new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = source;
        script.async = true;
        script.onload = () => window.Plotly ? resolve(window.Plotly) : reject(new Error('Библиотека графиков не загрузилась'));
        script.onerror = () => { script.remove(); reject(new Error('Не удалось загрузить график')); };
        document.head.appendChild(script);
    }).catch(error => { plotlyPromise = null; throw error; });
    return plotlyPromise;
}

export default function initCharts() {
    const chartElements = [...document.querySelectorAll('[data-plotly-histogram], [data-plotly-report-chart]')];
    if (!chartElements.length) return;
    const started = new WeakSet();
    const visible = element => element.getClientRects().length > 0 && (!element.closest('[data-report-activity]') || element.closest('[data-report-activity]').open);
    const render = async element => {
        if (!visible(element)) return;
        if (started.has(element)) { if (window.Plotly && element.classList.contains('js-plotly-plot')) window.Plotly.Plots.resize(element); return; }
        started.add(element);
        element.setAttribute('aria-busy', 'true');
        try {
            await loadPlotly();
            if (!visible(element)) { started.delete(element); return; }
            await draw(element);
        } catch (error) {
            started.delete(element);
            element.replaceChildren();
            const retry = document.createElement('button');
            retry.className = 'btn btn-outline-secondary btn-sm';
            retry.type = 'button';
            retry.textContent = 'Повторить загрузку графика';
            retry.addEventListener('click', () => { element.replaceChildren(); render(element); });
            element.appendChild(retry);
        } finally { element.removeAttribute('aria-busy'); }
    };
    const draw = async element => {
        const chartTheme = getComputedStyle(document.documentElement);
        const chartPrimary = chartTheme.getPropertyValue('--gc-primary').trim();
        const chartAccent = chartTheme.getPropertyValue('--gc-accent').trim();
        const chartMuted = chartTheme.getPropertyValue('--gc-on-surface-muted').trim();
        const chartBorder = chartTheme.getPropertyValue('--gc-border').trim();
        if (element.hasAttribute('data-plotly-histogram')) {
            element.style.height = '240px';
            return window.Plotly.newPlot(element, [{
                x: JSON.parse(element.dataset.plotlyHistogram),
                type: 'histogram',
                autobinx: false,
                marker: { color: chartPrimary, opacity: 0.7, line: { color: chartPrimary, width: 1 } },
                opacity: 0.75,
                xbins: { end: 110, size: 15, start: 0 }
            }], {
                autosize: true, height: 240,
                font: { color: chartMuted },
                paper_bgcolor: 'rgba(0,0,0,0)', plot_bgcolor: 'rgba(0,0,0,0)',
                xaxis: { gridcolor: chartBorder, zeroline: false }, yaxis: { gridcolor: chartBorder, zeroline: false },
                margin: { l: 32, r: 10, b: 28, t: 8, pad: 0 },
                showlegend: false
            }, { displayModeBar: false, responsive: true });
        }

        const data = [];
        const pulseKeys = JSON.parse(element.dataset.pulseKeys || '[]');
        if (pulseKeys.length) data.push({
            name: 'Просмотры', x: pulseKeys,
            y: JSON.parse(element.dataset.pulseValues || '[]'),
            type: 'scatter', mode: 'lines+markers',
            line: { color: chartPrimary, width: 2 },
            marker: { color: chartPrimary, size: 4 },
            hovertemplate: '%{x}<br>Просмотры: %{y}<extra></extra>'
        });
        if (element.dataset.taskKeys && element.dataset.taskValues) {
            data.push({
                x: JSON.parse(element.dataset.taskKeys),
                y: JSON.parse(element.dataset.taskValues),
                name: 'Опыт', type: 'scatter', mode: 'lines+markers',
                yaxis: 'y2',
                line: { color: chartAccent, width: 1.5 },
                fill: 'none',
                marker: { color: chartAccent, size: 4 },
                hovertemplate: '%{x}<br>Опыт: %{y} XP<extra></extra>'
            });
        }
        return window.Plotly.newPlot(element, data, {
            font: { color: chartMuted },
            paper_bgcolor: 'rgba(0,0,0,0)',
            plot_bgcolor: 'rgba(0,0,0,0)',
            height: 200,
            xaxis: { gridcolor: chartBorder, tickfont: { size: 11 }, zeroline: false, showline: false },
            yaxis: { title: { text: 'Просмотры', font: { size: 11 } }, gridcolor: chartBorder, tickfont: { size: 11 }, rangemode: 'tozero', zeroline: false, showline: false, visible: pulseKeys.length > 0 },
            yaxis2: { title: { text: 'XP', font: { size: 11 } }, side: 'right', showgrid: false, tickfont: { size: 11 }, rangemode: 'tozero', zeroline: false, showline: false, overlaying: 'y', visible: Boolean(element.dataset.taskKeys) },
            margin: { l: pulseKeys.length ? 52 : 12, r: element.dataset.taskKeys ? 48 : 12, b: 34, t: 12, pad: 0 },
            showlegend: false
        }, { displayModeBar: false, responsive: true });
    };
    const renderVisible = () => chartElements.forEach(element => render(element));
    document.querySelectorAll('[data-report-activity]').forEach(details => details.addEventListener('toggle', () => {
        if (details.open) requestAnimationFrame(renderVisible);
    }));
    document.addEventListener('shown.bs.collapse', () => requestAnimationFrame(renderVisible));
    document.addEventListener('shown.bs.tab', () => requestAnimationFrame(renderVisible));
    renderVisible();
}
