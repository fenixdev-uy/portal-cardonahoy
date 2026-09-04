/**
 * Actualización silenciosa para las pantallas de análisis.
 *
 * Consulta la misma URL para respetar los filtros activos. Solo trabaja con
 * la pestaña visible y modifica las zonas cuyos datos pueden cambiar, por lo
 * que conserva controles, tabla abierta y posición de scroll.
 */
(function () {
  const script = document.currentScript;
  const segundos = Math.max(5, Number(script && script.dataset.refreshSeconds) || 30);
  const demora = segundos * 1000;
  let temporizador = null;
  let controlador = null;
  let actualizando = false;

  function programar() {
    clearTimeout(temporizador);
    temporizador = null;
    if (document.visibilityState !== 'visible') return;
    temporizador = window.setTimeout(actualizar, demora);
  }

  function copiarContenido(selector, documentoNuevo) {
    const actual = document.querySelector(selector);
    const nuevo = documentoNuevo.querySelector(selector);
    if (actual && nuevo) {
      actual.innerHTML = nuevo.innerHTML;
      if (nuevo.hasAttribute('aria-label')) {
        actual.setAttribute('aria-label', nuevo.getAttribute('aria-label'));
      }
    }
  }

  function requiereRecarga(documentoNuevo) {
    const tieneGrafico = Boolean(document.getElementById('vizDatos'));
    const tendraGrafico = Boolean(documentoNuevo.getElementById('vizDatos'));
    return tieneGrafico !== tendraGrafico;
  }

  function aplicar(documentoNuevo) {
    const datosActuales = document.getElementById('vizDatos');
    const datosNuevos = documentoNuevo.getElementById('vizDatos');

    copiarContenido('.viz-resumen', documentoNuevo);
    copiarContenido('.viz-panel:not(.viz-tabla-panel) .viz-panel-head > div:first-child', documentoNuevo);
    copiarContenido('.viz-tasa-compartidos', documentoNuevo);

    const tablaActual = document.getElementById('vizTabla');
    const tablaNueva = documentoNuevo.getElementById('vizTabla');
    if (tablaActual && tablaNueva) tablaActual.innerHTML = tablaNueva.innerHTML;

    if (datosActuales && datosNuevos) {
      datosActuales.textContent = datosNuevos.textContent;
      const resumenExportarActual = document.getElementById('vizExportarResumen');
      const resumenExportarNuevo = documentoNuevo.getElementById('vizExportarResumen');
      if (resumenExportarActual && resumenExportarNuevo) {
        resumenExportarActual.textContent = resumenExportarNuevo.textContent;
      }
      const datos = JSON.parse(datosNuevos.textContent);
      document.dispatchEvent(new CustomEvent('analisis:datos-actualizados', { detail: { datos } }));
    }
  }

  async function actualizar() {
    clearTimeout(temporizador);
    temporizador = null;
    if (actualizando || document.visibilityState !== 'visible') {
      programar();
      return;
    }

    actualizando = true;
    controlador = new AbortController();
    try {
      const respuesta = await fetch(window.location.href, {
        method: 'GET',
        credentials: 'same-origin',
        cache: 'no-store',
        headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' },
        signal: controlador.signal,
      });
      if (!respuesta.ok) throw new Error('Respuesta ' + respuesta.status);

      const html = await respuesta.text();
      if (document.visibilityState !== 'visible') return;
      const documentoNuevo = new DOMParser().parseFromString(html, 'text/html');

      // Si aparece el primer dato o desaparece el último, se reconstruye una
      // sola vez la estructura completa de la pantalla.
      if (requiereRecarga(documentoNuevo)) {
        window.location.reload();
        return;
      }

      aplicar(documentoNuevo);
      document.dispatchEvent(new CustomEvent('analisis:actualizado', {
        detail: { fecha: new Date().toISOString() },
      }));
    } catch (error) {
      if (error.name !== 'AbortError') {
        document.dispatchEvent(new CustomEvent('analisis:error-actualizacion'));
      }
    } finally {
      actualizando = false;
      controlador = null;
      programar();
    }
  }

  document.addEventListener('visibilitychange', () => {
    clearTimeout(temporizador);
    temporizador = null;
    if (document.visibilityState !== 'visible') {
      if (controlador) controlador.abort();
      return;
    }
    actualizar();
  });

  // Evento útil para una actualización manual y para las pruebas automáticas.
  document.addEventListener('analisis:refrescar-ahora', actualizar);
  programar();
})();
