/**
 * Gráfico de votaciones del panel.
 *
 * Dos lecturas del mismo dato: área degradada y columnas. Un solo eje vertical,
 * porque las dos series son votos y comparten unidad.
 *
 * Se dibuja en SVG midiendo el ancho real del contenedor y se redibuja al
 * cambiar de tamaño, así ocupa siempre el ancho disponible del panel derecho.
 */
(function () {
  const svg = document.getElementById('vizSvg');
  const lienzo = document.getElementById('vizLienzo');
  const tooltip = document.getElementById('vizTooltip');
  const crudo = document.getElementById('vizDatos');
  if (!svg || !lienzo || !tooltip || !crudo) return;

  const NS = 'http://www.w3.org/2000/svg';
  const datos = JSON.parse(crudo.textContent);
  if (!datos.length) return;

  const SERIES = [
    { clave: 'meGusta', nombre: 'Me gusta', variable: '--viz-si', id: 'si' },
    { clave: 'noMeGusta', nombre: 'No me gusta', variable: '--viz-no', id: 'no' },
  ];

  // Especificaciones fijas de las marcas.
  const MARGEN = { arriba: 26, derecha: 22, abajo: 62, izquierda: 52 };
  const ALTO_TRAZADO = 340;
  const COLUMNA_MAX = 24;   // nunca llenar la banda: el resto es aire
  const RADIO_PUNTA = 4;    // extremo del dato redondeado, base recta
  const HUECO = 2;          // separación en color de superficie entre marcas
  const RADIO_MARCA = 5;    // marcador >= 8px de diámetro

  let modo = 'area';
  let indiceActivo = null;
  let ultimo = null; // geometria del ultimo dibujo, para ubicar el tooltip

  const estilo = getComputedStyle(document.querySelector('.viz-figura'));
  const color = (nombre) => estilo.getPropertyValue(nombre).trim();
  const paleta = {
    si: color('--viz-si'),
    no: color('--viz-no'),
    superficie: color('--viz-surface') || '#ffffff',
    grilla: color('--viz-grid') || '#e1e0d9',
    eje: color('--viz-axis') || '#c3c2b7',
    tenue: color('--viz-muted') || '#898781',
    texto: color('--viz-ink') || '#0f172a',
  };
  const colorSerie = (s) => (s.id === 'si' ? paleta.si : paleta.no);

  const crear = (etiqueta, atributos = {}) => {
    const el = document.createElementNS(NS, etiqueta);
    for (const [k, v] of Object.entries(atributos)) el.setAttribute(k, String(v));
    return el;
  };

  /** Escala vertical con topes redondos: 0, 5, 10... según la magnitud. */
  function escalaY(maximo) {
    if (maximo <= 0) return { tope: 1, pasos: [0, 1] };
    const objetivo = 4;
    const bruto = maximo / objetivo;
    const magnitud = Math.pow(10, Math.floor(Math.log10(bruto)));
    const paso = [1, 2, 2.5, 5, 10].map((m) => m * magnitud).find((p) => p >= bruto) || magnitud * 10;
    const tope = Math.ceil(maximo / paso) * paso;
    const pasos = [];
    for (let v = 0; v <= tope + 1e-9; v += paso) pasos.push(Math.round(v * 1000) / 1000);
    return { tope, pasos };
  }

  /**
   * Recorta un texto al ancho disponible midiendo el render real, no estimando.
   * Devuelve '' si ni una inicial entra, así nunca queda un texto cortado.
   */
  function recortar(texto, anchoMax, tamano, peso) {
    const sonda = crear('text', { x: -9999, y: -9999, 'font-size': tamano, 'font-weight': peso });
    sonda.textContent = texto;
    svg.appendChild(sonda);
    let resultado = texto;
    if (sonda.getComputedTextLength() > anchoMax) {
      let corte = texto.length;
      while (corte > 1) {
        corte -= 1;
        sonda.textContent = texto.slice(0, corte).trimEnd() + '…';
        if (sonda.getComputedTextLength() <= anchoMax) break;
      }
      resultado = corte > 1 ? texto.slice(0, corte).trimEnd() + '…' : '';
    }
    sonda.remove();
    return resultado;
  }

  function texturas(defs) {
    // Canal de respaldo para forced-colors: 45° y su espejo 135°, nunca por defecto.
    SERIES.forEach((s, i) => {
      const p = crear('pattern', {
        id: 'viz-trama-' + s.id, width: 6, height: 6,
        patternUnits: 'userSpaceOnUse',
        patternTransform: 'rotate(' + (i === 0 ? 45 : 135) + ')',
      });
      p.appendChild(crear('rect', { width: 6, height: 6, fill: paleta.superficie }));
      p.appendChild(crear('line', { x1: 0, y1: 0, x2: 0, y2: 6, stroke: colorSerie(s), 'stroke-width': 2.5 }));
      defs.appendChild(p);
    });
  }

  function degradados(defs) {
    // El área es un lavado degradado, no un bloque saturado.
    SERIES.forEach((s) => {
      const g = crear('linearGradient', { id: 'viz-degradado-' + s.id, x1: 0, y1: 0, x2: 0, y2: 1 });
      g.appendChild(crear('stop', { offset: '0%', 'stop-color': colorSerie(s), 'stop-opacity': 0.26 }));
      g.appendChild(crear('stop', { offset: '70%', 'stop-color': colorSerie(s), 'stop-opacity': 0.06 }));
      g.appendChild(crear('stop', { offset: '100%', 'stop-color': colorSerie(s), 'stop-opacity': 0 }));
      defs.appendChild(g);
    });
  }

  function puntaRedondeada(x, y, ancho, alto, radio) {
    const r = Math.max(0, Math.min(radio, ancho / 2, alto));
    const base = y + alto;
    return `M${x},${base} L${x},${y + r} Q${x},${y} ${x + r},${y} `
         + `L${x + ancho - r},${y} Q${x + ancho},${y} ${x + ancho},${y + r} `
         + `L${x + ancho},${base} Z`;
  }

  /** Ancho de la caja de contenido: sin esto el viewBox no coincide con el
   *  ancho renderizado y todo el dibujo sale escalado. */
  function anchoDisponible() {
    const cs = getComputedStyle(lienzo);
    const relleno = parseFloat(cs.paddingLeft) + parseFloat(cs.paddingRight);
    return Math.max(320, Math.floor(lienzo.clientWidth - relleno));
  }

  function dibujar() {
    const ancho = anchoDisponible();
    const alto = ALTO_TRAZADO + MARGEN.arriba + MARGEN.abajo;
    const anchoTrazado = ancho - MARGEN.izquierda - MARGEN.derecha;

    svg.setAttribute('width', ancho);
    svg.setAttribute('height', alto);
    svg.setAttribute('viewBox', `0 0 ${ancho} ${alto}`);
    while (svg.firstChild) svg.removeChild(svg.firstChild);

    const defs = crear('defs');
    degradados(defs);
    texturas(defs);
    svg.appendChild(defs);

    const maximo = Math.max(...datos.flatMap((d) => [d.meGusta, d.noMeGusta]));
    const { tope, pasos } = escalaY(maximo);
    const y = (v) => MARGEN.arriba + ALTO_TRAZADO - (v / tope) * ALTO_TRAZADO;
    const anchoBanda = anchoTrazado / datos.length;
    const centro = (i) => MARGEN.izquierda + anchoBanda * (i + 0.5);
    ultimo = { anchoBanda, centro, y };

    // ---- Grilla y eje vertical: líneas de un pelo, sólidas, discretas ----
    const grupoGrilla = crear('g');
    pasos.forEach((v) => {
      grupoGrilla.appendChild(crear('line', {
        x1: MARGEN.izquierda, y1: y(v), x2: MARGEN.izquierda + anchoTrazado, y2: y(v),
        stroke: v === 0 ? paleta.eje : paleta.grilla, 'stroke-width': 1,
      }));
      const t = crear('text', {
        x: MARGEN.izquierda - 12, y: y(v) + 4, 'text-anchor': 'end',
        'font-size': 11, fill: paleta.tenue, 'font-variant-numeric': 'tabular-nums',
      });
      t.textContent = String(v);
      grupoGrilla.appendChild(t);
    });
    svg.appendChild(grupoGrilla);

    // ---- Etiquetas del eje horizontal: puesto + título recortado a medida ----
    const grupoEje = crear('g');
    datos.forEach((d, i) => {
      const cx = centro(i);
      const puesto = crear('text', {
        x: cx, y: MARGEN.arriba + ALTO_TRAZADO + 22, 'text-anchor': 'middle',
        'font-size': 12, 'font-weight': 700, fill: paleta.texto,
      });
      puesto.textContent = '#' + d.puesto;
      grupoEje.appendChild(puesto);

      const disponible = anchoBanda - 10;
      const corto = recortar(d.titulo, disponible, 10.5, 500);
      if (corto) {
        const t = crear('text', {
          x: cx, y: MARGEN.arriba + ALTO_TRAZADO + 39, 'text-anchor': 'middle',
          'font-size': 10.5, fill: paleta.tenue,
        });
        t.textContent = corto;
        grupoEje.appendChild(t);
      }
    });
    svg.appendChild(grupoEje);

    const capaDatos = crear('g', { class: 'viz-capa-datos' });
    svg.appendChild(capaDatos);

    if (modo === 'area') {
      // Se dibuja primero la serie de mayor techo, para que la menor quede arriba.
      const orden = [...SERIES].sort((a, b) =>
        Math.max(...datos.map((d) => d[b.clave])) - Math.max(...datos.map((d) => d[a.clave])));

      orden.forEach((s) => {
        const puntos = datos.map((d, i) => [centro(i), y(d[s.clave])]);
        const linea = puntos.map((p, i) => (i ? 'L' : 'M') + p[0] + ',' + p[1]).join(' ');
        const base = MARGEN.arriba + ALTO_TRAZADO;
        capaDatos.appendChild(crear('path', {
          d: `${linea} L${puntos[puntos.length - 1][0]},${base} L${puntos[0][0]},${base} Z`,
          fill: `url(#viz-degradado-${s.id})`, class: 'viz-area viz-area-' + s.id,
        }));
        capaDatos.appendChild(crear('path', {
          d: linea, fill: 'none', stroke: colorSerie(s), 'stroke-width': 2,
          'stroke-linejoin': 'round', 'stroke-linecap': 'round',
        }));
        // Anillo en color de superficie: el marcador se lee al cruzarse.
        puntos.forEach((p, i) => {
          capaDatos.appendChild(crear('circle', {
            cx: p[0], cy: p[1], r: RADIO_MARCA,
            fill: colorSerie(s), stroke: paleta.superficie, 'stroke-width': HUECO,
            class: 'viz-punto viz-punto-' + s.id, 'data-indice': i,
          }));
        });
      });

      const cruz = crear('line', {
        id: 'vizCruz', y1: MARGEN.arriba, y2: MARGEN.arriba + ALTO_TRAZADO,
        stroke: paleta.eje, 'stroke-width': 1, opacity: 0, 'pointer-events': 'none',
      });
      svg.appendChild(cruz);
    } else {
      const anchoPar = Math.min(COLUMNA_MAX * 2 + HUECO, anchoBanda * 0.62);
      const anchoCol = (anchoPar - HUECO) / 2;

      datos.forEach((d, i) => {
        const inicio = centro(i) - anchoPar / 2;
        SERIES.forEach((s, j) => {
          const valor = d[s.clave];
          const yTope = y(valor);
          const altoCol = MARGEN.arriba + ALTO_TRAZADO - yTope;
          if (altoCol <= 0) return;
          capaDatos.appendChild(crear('path', {
            d: puntaRedondeada(inicio + j * (anchoCol + HUECO), yTope, anchoCol, altoCol, RADIO_PUNTA),
            fill: colorSerie(s), class: 'viz-columna viz-columna-' + s.id, 'data-indice': i,
          }));
        });
      });
    }

    // ---- Etiqueta directa: solo la líder, nunca un número en cada marca ----
    const lider = datos[0];
    const claveLider = lider.meGusta >= lider.noMeGusta ? 'meGusta' : 'noMeGusta';
    const etiqueta = crear('text', {
      x: centro(0), y: y(lider[claveLider]) - 14, 'text-anchor': 'middle',
      'font-size': 12, 'font-weight': 700, fill: paleta.texto,
    });
    etiqueta.textContent = String(lider[claveLider]);
    svg.appendChild(etiqueta);

    // ---- Zonas de contacto: una banda completa por noticia, cómoda de apuntar ----
    const capaZonas = crear('g');
    datos.forEach((d, i) => {
      const z = crear('rect', {
        x: MARGEN.izquierda + anchoBanda * i, y: MARGEN.arriba,
        width: anchoBanda, height: ALTO_TRAZADO,
        fill: 'transparent', 'data-indice': i, class: 'viz-zona', tabindex: 0,
        role: 'button', 'aria-label': `${d.titulo}: ${d.meGusta} me gusta, ${d.noMeGusta} no me gusta`,
      });
      capaZonas.appendChild(z);
    });
    svg.appendChild(capaZonas);

    capaZonas.querySelectorAll('.viz-zona').forEach((z) => {
      const i = Number(z.dataset.indice);
      z.addEventListener('mouseenter', () => resaltar(i));
      z.addEventListener('focus', () => resaltar(i));
      z.addEventListener('mousemove', (e) => moverTooltip(e.clientX, e.clientY));
      z.addEventListener('mouseleave', apagar);
      z.addEventListener('blur', apagar);
    });

    if (indiceActivo !== null && indiceActivo < datos.length) resaltar(indiceActivo, false);
  }

  function resaltar(i, moverCaja = true) {
    indiceActivo = i;
    const d = datos[i];

    svg.querySelectorAll('.viz-columna, .viz-punto').forEach((el) => {
      el.classList.toggle('is-atenuado', Number(el.dataset.indice) !== i);
    });

    const x = ultimo ? ultimo.centro(i) : 0;
    const cruz = document.getElementById('vizCruz');
    if (cruz) {
      cruz.setAttribute('x1', x);
      cruz.setAttribute('x2', x);
      cruz.setAttribute('opacity', 1);
    }

    tooltip.innerHTML =
      `<span class="viz-tooltip-titulo"></span>`
      + (d.categoria ? `<span class="viz-tooltip-cat"></span>` : '')
      + `<span class="viz-tooltip-fila"><i class="viz-swatch viz-swatch-si"></i>Me gusta<b>${d.meGusta}</b></span>`
      + `<span class="viz-tooltip-fila"><i class="viz-swatch viz-swatch-no"></i>No me gusta<b>${d.noMeGusta}</b></span>`
      + `<span class="viz-tooltip-total">Total<b>${d.total}</b></span>`;
    // textContent, no innerHTML, para el contenido variable.
    tooltip.querySelector('.viz-tooltip-titulo').textContent = '#' + d.puesto + ' · ' + d.titulo;
    if (d.categoria) tooltip.querySelector('.viz-tooltip-cat').textContent = d.categoria;
    tooltip.classList.add('is-visible');

    if (moverCaja && ultimo) {
      // Se prefiere arriba de la marca; si no entra, se voltea abajo.
      const cima = ultimo.y(Math.max(d.meGusta, d.noMeGusta));
      colocarEnSvg(x, cima - tooltip.offsetHeight - 14, cima + 16);
    }
  }

  /** Desfase del SVG dentro del lienzo: el padding no es parte del dibujo. */
  function desfase() {
    const cs = getComputedStyle(lienzo);
    return { x: parseFloat(cs.paddingLeft), y: parseFloat(cs.paddingTop) };
  }

  /** Ubica el tooltip a partir de coordenadas del SVG, con volteo si no entra. */
  function colocarEnSvg(xSvg, ySvgPreferido, ySvgAlternativo) {
    const d = desfase();
    const alto = tooltip.offsetHeight;
    const limite = lienzo.clientHeight - alto - 6;
    let top = ySvgPreferido + d.y;
    if (top < 6) top = Math.min(ySvgAlternativo + d.y, limite);
    colocarTooltip(xSvg + d.x, Math.max(6, Math.min(top, limite)));
  }

  function colocarTooltip(x, y) {
    const t = tooltip.getBoundingClientRect();
    const d = desfase();
    const minIzq = MARGEN.izquierda + d.x;
    const maxIzq = lienzo.clientWidth - d.x - MARGEN.derecha - t.width;
    let izq = x - t.width / 2;
    izq = maxIzq < minIzq ? Math.max(6, maxIzq) : Math.max(minIzq, Math.min(izq, maxIzq));
    tooltip.style.left = izq + 'px';
    tooltip.style.top = Math.max(6, y) + 'px';
  }

  function moverTooltip(clienteX, clienteY) {
    const caja = lienzo.getBoundingClientRect();
    const alto = tooltip.offsetHeight;
    const arriba = clienteY - caja.top - alto - 16;
    const abajo = clienteY - caja.top + 20;
    const limite = lienzo.clientHeight - alto - 6;
    const top = arriba < 6 ? Math.min(abajo, limite) : arriba;
    colocarTooltip(clienteX - caja.left, Math.max(6, Math.min(top, limite)));
  }

  function apagar() {
    indiceActivo = null;
    tooltip.classList.remove('is-visible');
    svg.querySelectorAll('.is-atenuado').forEach((el) => el.classList.remove('is-atenuado'));
    const cruz = document.getElementById('vizCruz');
    if (cruz) cruz.setAttribute('opacity', 0);
  }

  // ---- Cambio de tipo de gráfico ----
  document.querySelectorAll('.viz-modo-btn').forEach((btn) => {
    btn.addEventListener('click', () => {
      if (btn.dataset.modo === modo) return;
      modo = btn.dataset.modo;
      document.querySelectorAll('.viz-modo-btn').forEach((b) => {
        const activo = b === btn;
        b.classList.toggle('is-activo', activo);
        b.setAttribute('aria-pressed', activo ? 'true' : 'false');
      });
      apagar();
      dibujar();
    });
  });

  // ---- Gemelo en tabla ----
  const tablaBtn = document.getElementById('vizTablaBtn');
  const tabla = document.getElementById('vizTabla');
  if (tablaBtn && tabla) {
    tablaBtn.addEventListener('click', () => {
      const abierta = !tabla.hidden;
      tabla.hidden = abierta;
      tablaBtn.setAttribute('aria-expanded', abierta ? 'false' : 'true');
      tablaBtn.classList.toggle('is-activo', !abierta);
    });
  }

  // ---- Trama solo cuando el sistema fuerza colores ----
  const forzado = window.matchMedia('(forced-colors: active)');
  const aplicarTrama = () => document.getElementById('vizFigura')
    .classList.toggle('viz-con-trama', forzado.matches);
  if (forzado.addEventListener) forzado.addEventListener('change', aplicarTrama);
  aplicarTrama();

  dibujar();

  // Redibuja al cambiar el ancho disponible del panel.
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
