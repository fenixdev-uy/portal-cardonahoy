# Registro de incidentes — Portal de Noticias

Este archivo conserva fallas reales, su causa raíz, la corrección aplicada y las comprobaciones necesarias para evitar diagnósticos incompletos. No reemplaza `CONTINUIDAD.md`: aquí se registran incidentes operativos y aprendizajes reutilizables; la continuidad mantiene el estado general del proyecto.

## Formato para nuevos incidentes

Cada entrada debe indicar:

- fecha, estado y ambientes afectados;
- impacto y síntomas observables;
- causa raíz comprobada;
- por qué las verificaciones anteriores no la detectaron;
- corrección y alcance de seguridad;
- validación en el dominio real;
- prevención y archivos relacionados.

---

## INC-2026-08-28-001 — Google Analytics bloqueado por CSP

**Estado:** resuelto y confirmado por el usuario<br>
**Ambientes:** DEV y PROD<br>
**Componente:** Código del Header, vistas virtuales de noticias y cabeceras Apache

### Impacto

El snippet de Google Analytics estaba guardado y activo, pero las visitas de la portada y las vistas virtuales generadas al abrir noticias no llegaban de forma confiable a Google Analytics. El código parecía correcto y eso desvió el diagnóstico hacia el tag, la medición optimizada y la navegación del drawer.

### Síntomas

- `gtag()` y `trackStoryView()` existían y construían el título, la URL canónica y el path esperados.
- El HTML público contenía el snippet configurado.
- Las pruebas locales podían aparentar funcionamiento correcto.
- En los dominios servidos por Apache, Analytics no registraba las visitas como se esperaba.

### Causa raíz

La política `Content-Security-Policy` de `.htaccess` no incluía:

- `https://www.googletagmanager.com` en `script-src`, por lo que el navegador podía bloquear la carga de `gtag.js`;
- los endpoints de Google Analytics en `connect-src`, por lo que también podía bloquear el envío de la medición.

El problema no estaba en la generación del `page_view`: era una restricción efectiva del servidor sobre el navegador.

### Por qué no se detectó en la revisión inicial

La validación se concentró en el código y en pruebas con un servidor local que no procesa `.htaccess`. Se comprobó que el evento virtual se construyera correctamente, pero no se inspeccionó al mismo tiempo la CSP entregada por el dominio real. Concluir que «estaba todo bien» sin validar esa última capa fue incorrecto.

### Corrección aplicada

Se amplió la CSP únicamente para Google Analytics/GTM:

- `script-src`: `https://www.googletagmanager.com`;
- `frame-src`: `https://www.googletagmanager.com`;
- `connect-src`: `https://www.googletagmanager.com`, `https://www.google-analytics.com`, `https://*.google-analytics.com` y `https://*.analytics.google.com`.

No se habilitó Meta Pixel ni ningún otro proveedor. El resto de las restricciones CSP permanece activo.

### Validación y cierre

- La cabecera HTTPS real de DEV y PROD contiene las excepciones aprobadas.
- DEV entrega el snippet activo `G-ZXFMBHWBCS` y conserva los atributos de título y URL usados por las vistas virtuales.
- `testgo.html` en PROD responde HTTP 200 con el mismo tag.
- El usuario confirmó que Analytics comenzó a registrar las vistas inmediatamente tanto en PROD como en DEV.

### Prevención obligatoria

Ante cualquier integración cargada desde **Código del Header**:

1. Validar el HTML y la configuración persistida.
2. Consultar las cabeceras HTTPS del dominio real; una prueba con el servidor PHP local no valida `.htaccess`.
3. Revisar la consola del navegador en busca de rechazos CSP.
4. Confirmar en Network que carga el script externo y que sale la solicitud de medición.
5. Separar claramente «evento construido por JavaScript» de «evento aceptado y enviado por el navegador».
6. No afirmar que la integración funciona únicamente por pruebas de código o de un entorno que no reproduce Apache.
7. No abrir navegadores automatizados contra una propiedad real sin autorización, para no contaminar Analytics.

### Archivos relacionados

- `.htaccess`
- `admin/configuracion-codigo-header.php`
- `admin/includes/funciones.php`
- `assets/js/portal.js`
- `index.php`
- `noticia.php`
- `testgo.html`
