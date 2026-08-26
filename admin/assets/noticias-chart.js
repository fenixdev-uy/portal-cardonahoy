/** Gráfico de área de publicaciones diarias del mes corriente. */
(function () {
  const svg = document.getElementById('publicationChart');
  const canvas = document.getElementById('publicationChartCanvas');
  const tooltip = document.getElementById('publicationChartTooltip');
  const source = document.getElementById('publicationChartData');
  const card = document.querySelector('.publication-chart-card');
  if (!svg || !canvas || !tooltip || !source || !card) return;

  const data = JSON.parse(source.textContent);
  if (!data.length) return;

  const NS = 'http://www.w3.org/2000/svg';
  const margin = { top: 24, right: 20, bottom: 42, left: 42 };
  const plotHeight = 210;
  let geometry = null;
  let activeIndex = null;

  const make = (tag, attrs = {}) => {
    const node = document.createElementNS(NS, tag);
    Object.entries(attrs).forEach(([name, value]) => node.setAttribute(name, String(value)));
    return node;
  };

  function scale(maxValue) {
    if (maxValue <= 1) return { max: 1, ticks: [0, 1] };
    const raw = maxValue / 4;
    const magnitude = Math.pow(10, Math.floor(Math.log10(raw)));
    const step = [1, 2, 2.5, 5, 10].map((n) => n * magnitude).find((n) => n >= raw) || magnitude * 10;
    const max = Math.ceil(maxValue / step) * step;
    const ticks = [];
    for (let value = 0; value <= max + 1e-9; value += step) ticks.push(Math.round(value * 1000) / 1000);
    return { max, ticks };
  }

  function availableWidth() {
    const style = getComputedStyle(canvas);
    return Math.max(320, Math.floor(canvas.clientWidth - parseFloat(style.paddingLeft) - parseFloat(style.paddingRight)));
  }

  function draw() {
    if (!card.open) return;

    const width = availableWidth();
    const height = margin.top + plotHeight + margin.bottom;
    const plotWidth = width - margin.left - margin.right;
    const band = plotWidth / data.length;
    const maxValue = Math.max(...data.map((item) => item.cantidad));
    const axis = scale(maxValue);
    const x = (index) => margin.left + band * (index + 0.5);
    const y = (value) => margin.top + plotHeight - (value / axis.max) * plotHeight;
    geometry = { width, height, band, x, y };

    svg.setAttribute('width', width);
    svg.setAttribute('height', height);
    svg.setAttribute('viewBox', `0 0 ${width} ${height}`);
    svg.replaceChildren();

    const defs = make('defs');
    const gradient = make('linearGradient', { id: 'publicationAreaGradient', x1: 0, y1: 0, x2: 0, y2: 1 });
    gradient.appendChild(make('stop', { offset: '0%', 'stop-color': '#2563eb', 'stop-opacity': 0.34 }));
    gradient.appendChild(make('stop', { offset: '62%', 'stop-color': '#3b82f6', 'stop-opacity': 0.11 }));
    gradient.appendChild(make('stop', { offset: '100%', 'stop-color': '#60a5fa', 'stop-opacity': 0 }));
    defs.appendChild(gradient);
    const lineGradient = make('linearGradient', { id: 'publicationLineGradient', x1: 0, y1: 0, x2: 1, y2: 0 });
    lineGradient.appendChild(make('stop', { offset: '0%', 'stop-color': '#2563eb' }));
    lineGradient.appendChild(make('stop', { offset: '100%', 'stop-color': '#0ea5e9' }));
    defs.appendChild(lineGradient);
    const glow = make('filter', { id: 'publicationGlow', x: '-30%', y: '-30%', width: '160%', height: '160%' });
    glow.appendChild(make('feDropShadow', { dx: 0, dy: 3, stdDeviation: 4, 'flood-color': '#2563eb', 'flood-opacity': 0.18 }));
    defs.appendChild(glow);
    svg.appendChild(defs);

    axis.ticks.forEach((value) => {
      svg.appendChild(make('line', {
        x1: margin.left, y1: y(value), x2: width - margin.right, y2: y(value),
        stroke: value === 0 ? '#cbd5e1' : '#edf2f7', 'stroke-width': 1,
      }));
      const label = make('text', {
        x: margin.left - 11, y: y(value) + 4, 'text-anchor': 'end',
        fill: '#94a3b8', 'font-size': 11, 'font-variant-numeric': 'tabular-nums',
      });
      label.textContent = String(value);
      svg.appendChild(label);
    });

    const points = data.map((item, index) => [x(index), y(item.cantidad)]);
    const linePath = points.map((point, index) => `${index ? 'L' : 'M'}${point[0]},${point[1]}`).join(' ');
    const base = margin.top + plotHeight;
    svg.appendChild(make('path', {
      d: `${linePath} L${points[points.length - 1][0]},${base} L${points[0][0]},${base} Z`,
      fill: 'url(#publicationAreaGradient)',
    }));
    svg.appendChild(make('path', {
      d: linePath, fill: 'none', stroke: 'url(#publicationLineGradient)',
      'stroke-width': 2.5, 'stroke-linecap': 'round', 'stroke-linejoin': 'round',
      filter: 'url(#publicationGlow)',
    }));

    points.forEach((point, index) => {
      if (data[index].cantidad === 0) return;
      svg.appendChild(make('circle', {
        cx: point[0], cy: point[1], r: 4.5, fill: '#2563eb',
        stroke: '#ffffff', 'stroke-width': 2.5, class: 'publication-chart-point',
      }));
    });

    const labelEvery = width < 620 ? 5 : 3;
    data.forEach((item, index) => {
      const show = item.dia === 1 || item.dia === data.length || item.dia % labelEvery === 0;
      if (show) {
        const label = make('text', {
          x: x(index), y: base + 25, 'text-anchor': 'middle',
          fill: '#94a3b8', 'font-size': 10.5, 'font-weight': 600,
        });
        label.textContent = String(item.dia);
        svg.appendChild(label);
      }

      const hit = make('rect', {
        x: margin.left + band * index, y: margin.top, width: band, height: plotHeight,
        fill: 'transparent', class: 'publication-chart-hit', tabindex: 0,
        role: 'button', 'data-index': index,
        'aria-label': `Día ${item.dia}: ${item.cantidad} ${item.cantidad === 1 ? 'noticia publicada' : 'noticias publicadas'}`,
      });
      hit.addEventListener('mouseenter', () => showTooltip(index));
      hit.addEventListener('mousemove', () => placeTooltip(index));
      hit.addEventListener('focus', () => showTooltip(index));
      hit.addEventListener('mouseleave', hideTooltip);
      hit.addEventListener('blur', hideTooltip);
      svg.appendChild(hit);
    });

    if (activeIndex !== null) showTooltip(activeIndex);
  }

  function showTooltip(index) {
    activeIndex = index;
    const item = data[index];
    tooltip.innerHTML = `Día ${item.dia}<br><strong>${item.cantidad} ${item.cantidad === 1 ? 'noticia publicada' : 'noticias publicadas'}</strong>`;
    tooltip.classList.add('is-visible');
    placeTooltip(index);
  }

  function placeTooltip(index) {
    if (!geometry) return;
    const item = data[index];
    const left = geometry.x(index);
    const top = geometry.y(item.cantidad);
    const boxWidth = tooltip.offsetWidth || 150;
    const boxHeight = tooltip.offsetHeight || 54;
    const boundedLeft = Math.max(6, Math.min(left - boxWidth / 2, geometry.width - boxWidth - 6));
    const above = top - boxHeight - 13;
    tooltip.style.left = `${boundedLeft}px`;
    tooltip.style.top = `${above >= 4 ? above : top + 13}px`;
  }

  function hideTooltip() {
    activeIndex = null;
    tooltip.classList.remove('is-visible');
  }

  card.addEventListener('toggle', () => {
    if (card.open) requestAnimationFrame(draw);
    else hideTooltip();
  });

  if ('ResizeObserver' in window) {
    const observer = new ResizeObserver(() => requestAnimationFrame(draw));
    observer.observe(canvas);
  } else {
    window.addEventListener('resize', draw);
  }

  draw();
})();
