/** Exporta el análisis filtrado como PNG de alta resolución para compartir. */
(function () {
  const boton = document.getElementById('vizExportarBtn');
  const estado = document.getElementById('vizExportarEstado');
  const datosNodo = document.getElementById('vizDatos');
  const resumenNodo = document.getElementById('vizExportarResumen');
  if (!boton || !estado || !datosNodo || !resumenNodo) return;

  const COLORES = {
    fondo: '#f4f7fb',
    superficie: '#ffffff',
    texto: '#0f172a',
    tenue: '#64748b',
    grilla: '#e6ebf1',
    eje: '#cbd5e1',
    vistas: '#2a78d6',
    compartidas: '#f08a24',
    referenciaFondo: '#fff8f1',
    referenciaBorde: '#f4d5b5',
    referenciaTexto: '#9a4e0c',
  };
  const ESCALA = 2;
  const ANCHO = 1600;
  const ALTO = 980;
  let temporizadorEstado = null;

  function leerJson(nodo) {
    return JSON.parse(nodo.textContent || '{}');
  }

  function numero(valor) {
    return Number(valor || 0).toLocaleString('es-UY');
  }

  function porcentaje(valor) {
    return valor === null || !Number.isFinite(Number(valor))
      ? '—'
      : Number(valor).toLocaleString('es-UY', { minimumFractionDigits: 1, maximumFractionDigits: 1 }) + '%';
  }

  function redondeado(ctx, x, y, ancho, alto, radio) {
    const r = Math.min(radio, ancho / 2, alto / 2);
    ctx.beginPath();
    ctx.moveTo(x + r, y);
    ctx.arcTo(x + ancho, y, x + ancho, y + alto, r);
    ctx.arcTo(x + ancho, y + alto, x, y + alto, r);
    ctx.arcTo(x, y + alto, x, y, r);
    ctx.arcTo(x, y, x + ancho, y, r);
    ctx.closePath();
  }

  function escalaVertical(maximo) {
    if (maximo <= 0) return { tope: 1, pasos: [0, 1] };
    const bruto = maximo / 4;
    const magnitud = Math.pow(10, Math.floor(Math.log10(bruto)));
    const paso = [1, 2, 2.5, 5, 10].map((m) => m * magnitud).find((p) => p >= bruto) || magnitud * 10;
    const tope = Math.ceil(maximo / paso) * paso;
    const pasos = [];
    for (let valor = 0; valor <= tope + 1e-9; valor += paso) pasos.push(valor);
    return { tope, pasos };
  }

  function seriesActivas() {
    return Array.from(document.querySelectorAll('.viz-serie-btn[data-serie][aria-pressed="true"]'))
      .map((control) => control.dataset.serie)
      .filter((serie) => serie === 'vistas' || serie === 'compartidas');
  }

  function modoActivo() {
    return document.querySelector('.viz-modo-btn.is-activo')?.dataset.modo === 'columnas' ? 'columnas' : 'area';
  }

  function colorSerie(serie) {
    return serie === 'compartidas' ? COLORES.compartidas : COLORES.vistas;
  }

  function nombreSerie(serie) {
    return serie === 'compartidas' ? 'Compartidas' : 'Vistas';
  }

  function dibujarLeyenda(ctx, series, x, y) {
    let cursor = x;
    ctx.font = '700 18px Arial, sans-serif';
    ctx.textBaseline = 'middle';
    series.forEach((serie) => {
      ctx.fillStyle = colorSerie(serie);
      redondeado(ctx, cursor, y - 7, 14, 14, 4);
      ctx.fill();
      ctx.fillStyle = COLORES.tenue;
      ctx.fillText(nombreSerie(serie), cursor + 23, y);
      cursor += 23 + ctx.measureText(nombreSerie(serie)).width + 30;
    });
  }

  function dibujarGrafico(ctx, datos, series, modo, caja) {
    const margen = { arriba: 35, derecha: 20, abajo: 78, izquierda: 55 };
    const x0 = caja.x + margen.izquierda;
    const y0 = caja.y + margen.arriba;
    const ancho = caja.ancho - margen.izquierda - margen.derecha;
    const alto = caja.alto - margen.arriba - margen.abajo;
    const maximo = Math.max(0, ...datos.flatMap((fila) => series.map((serie) => Number(fila[serie] || 0))));
    const escala = escalaVertical(maximo);
    const y = (valor) => y0 + alto - (Number(valor || 0) / escala.tope) * alto;
    const banda = ancho / Math.max(datos.length, 1);
    const centro = (indice) => x0 + banda * (indice + 0.5);

    ctx.font = '500 16px Arial, sans-serif';
    ctx.textAlign = 'right';
    ctx.textBaseline = 'middle';
    escala.pasos.forEach((valor) => {
      const py = y(valor);
      ctx.strokeStyle = valor === 0 ? COLORES.eje : COLORES.grilla;
      ctx.lineWidth = 1;
      ctx.beginPath();
      ctx.moveTo(x0, py + 0.5);
      ctx.lineTo(x0 + ancho, py + 0.5);
      ctx.stroke();
      ctx.fillStyle = COLORES.tenue;
      ctx.fillText(numero(valor), x0 - 12, py);
    });

    if (modo === 'columnas') {
      const hueco = 5;
      const anchoGrupo = Math.min(58, banda * 0.66);
      const anchoBarra = Math.max(7, (anchoGrupo - hueco * (series.length - 1)) / series.length);
      datos.forEach((fila, indice) => {
        const inicio = centro(indice) - (anchoBarra * series.length + hueco * (series.length - 1)) / 2;
        series.forEach((serie, serieIndice) => {
          const py = y(fila[serie]);
          const altura = y0 + alto - py;
          if (altura <= 0) return;
          ctx.fillStyle = colorSerie(serie);
          redondeado(ctx, inicio + serieIndice * (anchoBarra + hueco), py, anchoBarra, altura, 5);
          ctx.fill();
        });
      });
    } else {
      [...series].sort((a, b) => Math.max(...datos.map((fila) => fila[b])) - Math.max(...datos.map((fila) => fila[a])))
        .forEach((serie) => {
          const puntos = datos.map((fila, indice) => ({ x: centro(indice), y: y(fila[serie]) }));
          ctx.beginPath();
          puntos.forEach((punto, indice) => indice ? ctx.lineTo(punto.x, punto.y) : ctx.moveTo(punto.x, punto.y));
          ctx.lineTo(puntos[puntos.length - 1].x, y0 + alto);
          ctx.lineTo(puntos[0].x, y0 + alto);
          ctx.closePath();
          ctx.globalAlpha = 0.11;
          ctx.fillStyle = colorSerie(serie);
          ctx.fill();
          ctx.globalAlpha = 1;
          ctx.beginPath();
          puntos.forEach((punto, indice) => indice ? ctx.lineTo(punto.x, punto.y) : ctx.moveTo(punto.x, punto.y));
          ctx.strokeStyle = colorSerie(serie);
          ctx.lineWidth = 3;
          ctx.lineJoin = 'round';
          ctx.lineCap = 'round';
          ctx.stroke();
          puntos.forEach((punto) => {
            ctx.beginPath();
            ctx.arc(punto.x, punto.y, 6, 0, Math.PI * 2);
            ctx.fillStyle = colorSerie(serie);
            ctx.fill();
            ctx.strokeStyle = COLORES.superficie;
            ctx.lineWidth = 3;
            ctx.stroke();
          });
        });
    }

    ctx.textAlign = 'center';
    datos.forEach((fila, indice) => {
      const cx = centro(indice);
      ctx.fillStyle = COLORES.texto;
      ctx.font = '800 17px Arial, sans-serif';
      ctx.fillText('#' + fila.puesto, cx, y0 + alto + 25);
      ctx.fillStyle = COLORES.tenue;
      ctx.font = '500 14px Arial, sans-serif';
      ctx.fillText(truncarTexto(ctx, fila.titulo, Math.max(55, banda - 12)), cx, y0 + alto + 49);
    });
  }

  function truncarTexto(ctx, texto, ancho) {
    let salida = String(texto || '');
    if (!salida || ctx.measureText(salida).width <= ancho) return salida;
    while (salida.length > 1 && ctx.measureText(salida + '…').width > ancho) salida = salida.slice(0, -1);
    return salida.trimEnd() + '…';
  }

  function dibujarTasasMensuales(ctx, resumen, caja) {
    const centroX = caja.x + caja.ancho / 2;
    const tasas = Array.isArray(resumen.tasas_mensuales) ? resumen.tasas_mensuales : [];
    const escala = Math.max(1, Number(resumen.escala_tasas || 10));
    const xBarra = caja.x + 91;
    const anchoBarra = caja.ancho - 174;

    ctx.textAlign = 'center';
    ctx.fillStyle = COLORES.referenciaTexto;
    ctx.font = '800 15px Arial, sans-serif';
    ctx.fillText('PORCENTAJE DE DISTRIBUCIÓN', centroX, caja.y + 38);
    ctx.fillStyle = COLORES.texto;
    ctx.font = '800 28px Arial, sans-serif';
    ctx.fillText(`Tasa mensual ${resumen.anio_tasas}`, centroX, caja.y + 76);
    ctx.fillStyle = COLORES.tenue;
    ctx.font = '500 16px Arial, sans-serif';
    ctx.fillText('Independiente del período filtrado', centroX, caja.y + 107);

    ctx.font = '600 13px Arial, sans-serif';
    ctx.textAlign = 'left';
    ctx.fillText('0%', xBarra, caja.y + 139);
    ctx.textAlign = 'right';
    ctx.fillText(`${numero(escala)}%`, xBarra + anchoBarra, caja.y + 139);

    tasas.forEach((fila, indice) => {
      const y = caja.y + 171 + indice * 43;
      const tasa = fila.tasa === null ? null : Number(fila.tasa);
      const proporcion = tasa === null ? 0 : Math.min(1, Math.max(0, tasa / escala));
      ctx.textAlign = 'left';
      ctx.fillStyle = COLORES.tenue;
      ctx.font = '750 15px Arial, sans-serif';
      ctx.fillText(String(fila.abreviado || '').toUpperCase(), caja.x + 28, y + 1);

      ctx.fillStyle = COLORES.grilla;
      redondeado(ctx, xBarra, y - 7, anchoBarra, 14, 4);
      ctx.fill();
      if (proporcion > 0) {
        ctx.fillStyle = COLORES.compartidas;
        redondeado(ctx, xBarra, y - 7, anchoBarra * proporcion, 14, 4);
        ctx.fill();
      }

      const referenciaX = xBarra + anchoBarra * Math.min(1, Number(resumen.referencia || 0) / escala);
      ctx.fillStyle = COLORES.referenciaTexto;
      redondeado(ctx, referenciaX - 1.5, y - 11, 3, 22, 1.5);
      ctx.fill();

      ctx.textAlign = 'right';
      ctx.fillStyle = COLORES.texto;
      ctx.font = '800 18px Arial, sans-serif';
      ctx.fillText(porcentaje(fila.tasa), caja.x + caja.ancho - 28, y + 1);
    });

    ctx.fillStyle = COLORES.referenciaFondo;
    ctx.strokeStyle = COLORES.referenciaBorde;
    ctx.lineWidth = 1.5;
    redondeado(ctx, caja.x + 28, caja.y + 562, caja.ancho - 56, 54, 13);
    ctx.fill();
    ctx.stroke();
    ctx.textAlign = 'left';
    ctx.fillStyle = COLORES.referenciaTexto;
    ctx.font = '750 16px Arial, sans-serif';
    ctx.fillText('Media histórica mensual', caja.x + 48, caja.y + 590);
    ctx.textAlign = 'right';
    ctx.font = '850 20px Arial, sans-serif';
    ctx.fillText(Number(resumen.referencia).toLocaleString('es-UY') + '%', caja.x + caja.ancho - 48, caja.y + 590);

    ctx.textAlign = 'center';
    ctx.fillStyle = COLORES.tenue;
    ctx.font = '600 15px Arial, sans-serif';
    ctx.fillText(resumen.periodo_tasas, centroX, caja.y + 634);
    ctx.font = '500 14px Arial, sans-serif';
    ctx.fillText('La marca vertical indica la referencia del 5%', centroX, caja.y + 657);
  }

  function crearPng() {
    return new Promise((resolve, reject) => {
      const datos = leerJson(datosNodo);
      const resumen = leerJson(resumenNodo);
      const series = seriesActivas();
      if (!Array.isArray(datos) || !datos.length || !series.length) {
        reject(new Error('No hay datos visibles para generar la imagen.'));
        return;
      }

      const canvas = document.createElement('canvas');
      canvas.width = ANCHO * ESCALA;
      canvas.height = ALTO * ESCALA;
      const ctx = canvas.getContext('2d');
      if (!ctx) {
        reject(new Error('No se pudo preparar la imagen.'));
        return;
      }
      ctx.scale(ESCALA, ESCALA);
      ctx.fillStyle = COLORES.fondo;
      ctx.fillRect(0, 0, ANCHO, ALTO);

      ctx.fillStyle = COLORES.texto;
      ctx.font = '850 38px Arial, sans-serif';
      ctx.textAlign = 'left';
      ctx.fillText('CardonaHoy · Análisis de Vistas', 50, 62);
      ctx.fillStyle = COLORES.tenue;
      ctx.font = '600 18px Arial, sans-serif';
      const modo = modoActivo();
      ctx.fillText(`Período: ${resumen.rango}  ·  Vista: ${modo === 'columnas' ? 'Columnas' : 'Área'}  ·  Series: ${series.map(nombreSerie).join(' + ')}`, 50, 96);

      const tarjetas = [
        ['Noticias con actividad', resumen.noticias],
        ['Vistas', resumen.vistas],
        ['Compartidas', resumen.compartidas],
      ];
      tarjetas.forEach(([etiqueta, valor], indice) => {
        const x = 50 + indice * 245;
        ctx.fillStyle = COLORES.superficie;
        redondeado(ctx, x, 125, 220, 90, 14);
        ctx.fill();
        ctx.fillStyle = COLORES.tenue;
        ctx.font = '750 14px Arial, sans-serif';
        ctx.fillText(String(etiqueta).toUpperCase(), x + 18, 154);
        ctx.fillStyle = COLORES.texto;
        ctx.font = '850 30px Arial, sans-serif';
        ctx.fillText(numero(valor), x + 18, 193);
      });

      const panel = { x: 50, y: 245, ancho: 1500, alto: 675 };
      ctx.fillStyle = COLORES.superficie;
      redondeado(ctx, panel.x, panel.y, panel.ancho, panel.alto, 18);
      ctx.fill();
      ctx.strokeStyle = COLORES.grilla;
      ctx.lineWidth = 1;
      ctx.beginPath();
      ctx.moveTo(1110.5, panel.y);
      ctx.lineTo(1110.5, panel.y + panel.alto);
      ctx.stroke();

      ctx.fillStyle = COLORES.texto;
      ctx.font = '800 25px Arial, sans-serif';
      ctx.fillText('Noticias con más vistas y compartidas', 82, 287);
      dibujarLeyenda(ctx, series, 700, 281);
      dibujarGrafico(ctx, datos, series, modo, { x: 74, y: 302, ancho: 1000, alto: 565 });
      ctx.fillStyle = COLORES.tenue;
      ctx.font = '500 15px Arial, sans-serif';
      ctx.textAlign = 'left';
      ctx.fillText('Eje vertical en cantidad de eventos · Top 8 del período', 82, 891);

      dibujarTasasMensuales(ctx, resumen, { x: 1111, y: 246, ancho: 439, alto: 674 });

      canvas.toBlob((blob) => blob ? resolve(blob) : reject(new Error('No se pudo generar el PNG.')), 'image/png');
    });
  }

  function descargar(blob) {
    const resumen = leerJson(resumenNodo);
    const enlace = document.createElement('a');
    const url = URL.createObjectURL(blob);
    enlace.href = url;
    enlace.download = `cardonahoy-vistas-${resumen.desde}-a-${resumen.hasta}.png`;
    document.body.appendChild(enlace);
    enlace.click();
    enlace.remove();
    window.setTimeout(() => URL.revokeObjectURL(url), 1000);
  }

  function informar(mensaje, copiado = false) {
    clearTimeout(temporizadorEstado);
    estado.textContent = mensaje;
    boton.classList.toggle('is-copiado', copiado);
    temporizadorEstado = window.setTimeout(() => {
      estado.textContent = '';
      boton.classList.remove('is-copiado');
    }, 3200);
  }

  boton.disabled = false;
  boton.addEventListener('click', async () => {
    boton.disabled = true;
    informar('Preparando PNG…');
    try {
      const blob = await crearPng();
      const puedeCopiar = window.isSecureContext
        && navigator.clipboard?.write
        && typeof window.ClipboardItem !== 'undefined';
      if (puedeCopiar) {
        try {
          await navigator.clipboard.write([new ClipboardItem({ 'image/png': blob })]);
          informar('Imagen copiada', true);
          return;
        } catch (error) {
          descargar(blob);
          informar('PNG descargado: el navegador bloqueó la copia');
          return;
        }
      }
      descargar(blob);
      informar('PNG descargado: copia no disponible');
    } catch (error) {
      informar(error.message || 'No se pudo generar la imagen');
    } finally {
      boton.disabled = false;
    }
  });
})();
