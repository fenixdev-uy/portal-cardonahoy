/** Gráfico temporal de publicaciones: área o columnas sobre una única serie. */
(function () {
  const svg = document.getElementById('vizSvg');
  const lienzo = document.getElementById('vizLienzo');
  const tooltip = document.getElementById('vizTooltip');
  const crudo = document.getElementById('vizDatos');
  const figura = document.getElementById('vizFigura');
  if (!svg || !lienzo || !tooltip || !crudo || !figura) return;

  const NS = 'http://www.w3.org/2000/svg';
  const MARGEN = { arriba: 26, derecha: 22, abajo: 54, izquierda: 52 };
  const ALTO_TRAZADO = 340;
  const COLUMNA_MAX = 28;
  let datos = JSON.parse(crudo.textContent);
  let modo = 'area';
  let indiceActivo = null;
  let geometria = null;

  const estilo = getComputedStyle(figura);
  const color = estilo.getPropertyValue('--viz-publicaciones').trim() || '#2a78d6';
  const paleta = {
    superficie: estilo.getPropertyValue('--viz-surface').trim() || '#ffffff',
    grilla: estilo.getPropertyValue('--viz-grid').trim() || '#eef1f5',
    eje: estilo.getPropertyValue('--viz-axis').trim() || '#cbd5e1',
    tenue: estilo.getPropertyValue('--viz-muted').trim() || '#7c8797',
    texto: estilo.getPropertyValue('--viz-ink').trim() || '#0f172a',
  };

  const crear = (etiqueta, atributos = {}) => {
    const elemento = document.createElementNS(NS, etiqueta);
    Object.entries(atributos).forEach(([nombre, valor]) => elemento.setAttribute(nombre, String(valor)));
    return elemento;
  };

  function escalaY(maximo) {
    if (maximo <= 0) return { tope: 1, pasos: [0, 1] };
    const bruto = maximo / 4;
    const magnitud = Math.pow(10, Math.floor(Math.log10(bruto)));
    const paso = [1, 2, 2.5, 5, 10].map((valor) => valor * magnitud).find((valor) => valor >= bruto) || magnitud * 10;
    const tope = Math.ceil(maximo / paso) * paso;
    const pasos = [];
    for (let valor = 0; valor <= tope + 1e-9; valor += paso) pasos.push(Math.round(valor * 1000) / 1000);
    return { tope, pasos };
  }

  function anchoDisponible() {
    const css = getComputedStyle(lienzo);
    return Math.max(320, Math.floor(lienzo.clientWidth - parseFloat(css.paddingLeft) - parseFloat(css.paddingRight)));
  }

  function definiciones() {
    const defs = crear('defs');
    const degradado = crear('linearGradient', { id: 'viz-degradado-publicaciones', x1: 0, y1: 0, x2: 0, y2: 1 });
    degradado.appendChild(crear('stop', { offset: '0%', 'stop-color': color, 'stop-opacity': .28 }));
    degradado.appendChild(crear('stop', { offset: '70%', 'stop-color': color, 'stop-opacity': .06 }));
    degradado.appendChild(crear('stop', { offset: '100%', 'stop-color': color, 'stop-opacity': 0 }));
    defs.appendChild(degradado);

    const trama = crear('pattern', {
      id: 'viz-trama-publicaciones', width: 6, height: 6,
      patternUnits: 'userSpaceOnUse', patternTransform: 'rotate(45)',
    });
    trama.appendChild(crear('rect', { width: 6, height: 6, fill: paleta.superficie }));
    trama.appendChild(crear('line', { x1: 0, y1: 0, x2: 0, y2: 6, stroke: color, 'stroke-width': 2.5 }));
    defs.appendChild(trama);
    return defs;
  }

  function puntaRedondeada(x, y, ancho, alto) {
    const radio = Math.max(0, Math.min(4, ancho / 2, alto));
    const base = y + alto;
    return `M${x},${base} L${x},${y + radio} Q${x},${y} ${x + radio},${y} `
      + `L${x + ancho - radio},${y} Q${x + ancho},${y} ${x + ancho},${y + radio} `
      + `L${x + ancho},${base} Z`;
  }

  function dibujar() {
    if (!datos.length) return;
    const ancho = anchoDisponible();
    const alto = ALTO_TRAZADO + MARGEN.arriba + MARGEN.abajo;
    const anchoTrazado = ancho - MARGEN.izquierda - MARGEN.derecha;
    const anchoBanda = anchoTrazado / datos.length;
    const centro = (indice) => MARGEN.izquierda + anchoBanda * (indice + .5);
    const maximo = Math.max(...datos.map((dato) => Number(dato.publicaciones) || 0));
    const escala = escalaY(maximo);
    const y = (valor) => MARGEN.arriba + ALTO_TRAZADO - (valor / escala.tope) * ALTO_TRAZADO;
    geometria = { ancho, anchoBanda, centro, y };

    svg.setAttribute('width', ancho);
    svg.setAttribute('height', alto);
    svg.setAttribute('viewBox', `0 0 ${ancho} ${alto}`);
    svg.replaceChildren(definiciones());

    const grilla = crear('g');
    escala.pasos.forEach((valor) => {
      grilla.appendChild(crear('line', {
        x1: MARGEN.izquierda, y1: y(valor), x2: MARGEN.izquierda + anchoTrazado, y2: y(valor),
        stroke: valor === 0 ? paleta.eje : paleta.grilla, 'stroke-width': 1,
      }));
      const etiqueta = crear('text', {
        x: MARGEN.izquierda - 12, y: y(valor) + 4, 'text-anchor': 'end',
        'font-size': 11, fill: paleta.tenue, 'font-variant-numeric': 'tabular-nums',
      });
      etiqueta.textContent = String(valor);
      grilla.appendChild(etiqueta);
    });
    svg.appendChild(grilla);

    const maxEtiquetas = ancho < 620 ? 6 : Math.max(7, Math.min(12, Math.floor(anchoTrazado / 70)));
    const salto = datos.length <= maxEtiquetas ? 1 : Math.ceil((datos.length - 1) / (maxEtiquetas - 1));
    const base = MARGEN.arriba + ALTO_TRAZADO;
    const eje = crear('g');
    datos.forEach((dato, indice) => {
      if (indice !== 0 && indice !== datos.length - 1 && indice % salto !== 0) return;
      const etiqueta = crear('text', {
        x: centro(indice), y: base + 25, 'text-anchor': 'middle',
        'font-size': 10.5, 'font-weight': 600, fill: paleta.tenue,
      });
      etiqueta.textContent = dato.etiqueta;
      eje.appendChild(etiqueta);
    });
    svg.appendChild(eje);

    const capaDatos = crear('g', { class: 'viz-capa-datos' });
    svg.appendChild(capaDatos);
    if (modo === 'area') {
      const puntos = datos.map((dato, indice) => [centro(indice), y(Number(dato.publicaciones) || 0)]);
      const linea = puntos.map((punto, indice) => `${indice ? 'L' : 'M'}${punto[0]},${punto[1]}`).join(' ');
      capaDatos.appendChild(crear('path', {
        d: `${linea} L${puntos[puntos.length - 1][0]},${base} L${puntos[0][0]},${base} Z`,
        fill: 'url(#viz-degradado-publicaciones)', class: 'viz-area viz-area-publicaciones',
      }));
      capaDatos.appendChild(crear('path', {
        d: linea, fill: 'none', stroke: color, 'stroke-width': 2,
        'stroke-linejoin': 'round', 'stroke-linecap': 'round',
      }));
      puntos.forEach((punto, indice) => {
        if (Number(datos[indice].publicaciones) < 1) return;
        capaDatos.appendChild(crear('circle', {
          cx: punto[0], cy: punto[1], r: 5, fill: color,
          stroke: paleta.superficie, 'stroke-width': 2,
          class: 'viz-punto viz-punto-publicaciones', 'data-indice': indice,
        }));
      });
      svg.appendChild(crear('line', {
        id: 'vizCruz', y1: MARGEN.arriba, y2: base,
        stroke: paleta.eje, 'stroke-width': 1, opacity: 0, 'pointer-events': 'none',
      }));
    } else {
      const anchoColumna = Math.min(COLUMNA_MAX, anchoBanda * .58);
      datos.forEach((dato, indice) => {
        const valor = Number(dato.publicaciones) || 0;
        const cima = y(valor);
        const altoColumna = base - cima;
        if (altoColumna <= 0) return;
        capaDatos.appendChild(crear('path', {
          d: puntaRedondeada(centro(indice) - anchoColumna / 2, cima, anchoColumna, altoColumna),
          fill: color, class: 'viz-columna viz-columna-publicaciones', 'data-indice': indice,
        }));
      });
    }

    const indiceMaximo = datos.reduce((mejor, dato, indice) =>
      Number(dato.publicaciones) > Number(datos[mejor].publicaciones) ? indice : mejor, 0);
    const valorMaximo = Number(datos[indiceMaximo].publicaciones) || 0;
    if (valorMaximo > 0) {
      const etiquetaMaximo = crear('text', {
        x: centro(indiceMaximo), y: y(valorMaximo) - 14, 'text-anchor': 'middle',
        'font-size': 12, 'font-weight': 700, fill: paleta.texto,
      });
      etiquetaMaximo.textContent = String(valorMaximo);
      svg.appendChild(etiquetaMaximo);
    }

    const zonas = crear('g');
    datos.forEach((dato, indice) => {
      zonas.appendChild(crear('rect', {
        x: MARGEN.izquierda + anchoBanda * indice, y: MARGEN.arriba,
        width: anchoBanda, height: ALTO_TRAZADO, fill: 'transparent',
        class: 'viz-zona', tabindex: 0, role: 'button', 'data-indice': indice,
        'aria-label': `${dato.fechaVisible}: ${dato.publicaciones} ${Number(dato.publicaciones) === 1 ? 'noticia publicada' : 'noticias publicadas'}`,
      }));
    });
    svg.appendChild(zonas);
    zonas.querySelectorAll('.viz-zona').forEach((zona) => {
      const indice = Number(zona.dataset.indice);
      zona.addEventListener('mouseenter', () => resaltar(indice));
      zona.addEventListener('focus', () => resaltar(indice));
      zona.addEventListener('mousemove', (evento) => moverTooltip(evento.clientX, evento.clientY));
      zona.addEventListener('mouseleave', apagar);
      zona.addEventListener('blur', apagar);
    });
    if (indiceActivo !== null && indiceActivo < datos.length) resaltar(indiceActivo, false);
  }

  function resaltar(indice, moverCaja = true) {
    indiceActivo = indice;
    const dato = datos[indice];
    svg.querySelectorAll('.viz-columna, .viz-punto').forEach((elemento) => {
      elemento.classList.toggle('is-atenuado', Number(elemento.dataset.indice) !== indice);
    });
    const cruz = document.getElementById('vizCruz');
    if (cruz && geometria) {
      cruz.setAttribute('x1', geometria.centro(indice));
      cruz.setAttribute('x2', geometria.centro(indice));
      cruz.setAttribute('opacity', 1);
    }
    tooltip.innerHTML = '<span class="viz-tooltip-titulo"></span><span class="viz-tooltip-fila"><i class="viz-swatch viz-swatch-publicaciones"></i>Publicaciones<b></b></span>';
    tooltip.querySelector('.viz-tooltip-titulo').textContent = dato.fechaVisible;
    tooltip.querySelector('b').textContent = String(dato.publicaciones);
    tooltip.classList.add('is-visible');
    if (moverCaja && geometria) {
      const cima = geometria.y(Number(dato.publicaciones) || 0);
      colocarEnSvg(geometria.centro(indice), cima - tooltip.offsetHeight - 14, cima + 16);
    }
  }

  function desfase() {
    const css = getComputedStyle(lienzo);
    return { x: parseFloat(css.paddingLeft), y: parseFloat(css.paddingTop) };
  }

  function colocarEnSvg(xSvg, yPreferido, yAlternativo) {
    const offset = desfase();
    const limite = lienzo.clientHeight - tooltip.offsetHeight - 6;
    let top = yPreferido + offset.y;
    if (top < 6) top = Math.min(yAlternativo + offset.y, limite);
    colocarTooltip(xSvg + offset.x, Math.max(6, Math.min(top, limite)));
  }

  function colocarTooltip(x, y) {
    const caja = tooltip.getBoundingClientRect();
    const offset = desfase();
    const minimo = MARGEN.izquierda + offset.x;
    const maximo = lienzo.clientWidth - offset.x - MARGEN.derecha - caja.width;
    const izquierdaIdeal = x - caja.width / 2;
    tooltip.style.left = `${maximo < minimo ? Math.max(6, maximo) : Math.max(minimo, Math.min(izquierdaIdeal, maximo))}px`;
    tooltip.style.top = `${Math.max(6, y)}px`;
  }

  function moverTooltip(clienteX, clienteY) {
    const caja = lienzo.getBoundingClientRect();
    const arriba = clienteY - caja.top - tooltip.offsetHeight - 16;
    const abajo = clienteY - caja.top + 20;
    const limite = lienzo.clientHeight - tooltip.offsetHeight - 6;
    colocarTooltip(clienteX - caja.left, Math.max(6, Math.min(arriba < 6 ? abajo : arriba, limite)));
  }

  function apagar() {
    indiceActivo = null;
    tooltip.classList.remove('is-visible');
    svg.querySelectorAll('.is-atenuado').forEach((elemento) => elemento.classList.remove('is-atenuado'));
    const cruz = document.getElementById('vizCruz');
    if (cruz) cruz.setAttribute('opacity', 0);
  }

  document.querySelectorAll('.viz-modo-btn').forEach((boton) => {
    boton.addEventListener('click', () => {
      if (boton.dataset.modo === modo) return;
      modo = boton.dataset.modo;
      document.querySelectorAll('.viz-modo-btn').forEach((control) => {
        const activo = control === boton;
        control.classList.toggle('is-activo', activo);
        control.setAttribute('aria-pressed', activo ? 'true' : 'false');
      });
      apagar();
      dibujar();
    });
  });

  const coloresForzados = window.matchMedia('(forced-colors: active)');
  const aplicarTrama = () => figura.classList.toggle('viz-con-trama', coloresForzados.matches);
  if (coloresForzados.addEventListener) coloresForzados.addEventListener('change', aplicarTrama);
  aplicarTrama();
  dibujar();

  document.addEventListener('analisis:datos-actualizados', (evento) => {
    const nuevosDatos = evento.detail && evento.detail.datos;
    if (!Array.isArray(nuevosDatos) || !nuevosDatos.length) return;
    datos = nuevosDatos;
    apagar();
    dibujar();
  });

  if (window.ResizeObserver) {
    let pendiente = null;
    new ResizeObserver(() => {
      clearTimeout(pendiente);
      pendiente = setTimeout(dibujar, 80);
    }).observe(lienzo);
  } else {
    window.addEventListener('resize', dibujar);
  }
})();
