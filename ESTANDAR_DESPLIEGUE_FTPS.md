# Estándar de despliegue incremental por FTPS

Procedimiento reutilizable para publicar proyectos web administrados desde este entorno. El objetivo es que la primera instalación pueda transferirse completa y que las siguientes publicaciones modifiquen únicamente los archivos necesarios, con trazabilidad, verificación y posibilidad de recuperación.

Este documento describe el contrato de trabajo. Los datos particulares de cada proyecto viven fuera de Git en `servicios.local.json` y nunca deben copiarse a este archivo.

## 1. Principios obligatorios

1. **Mínimo alcance:** subir solamente los archivos nuevos o modificados.
2. **Sin borrado implícito:** una ausencia local nunca autoriza por sí sola a borrar el archivo remoto.
3. **Secretos fuera de Git y de la web:** credenciales, configuraciones privadas y respaldos no forman parte del paquete publicable.
4. **Estado remoto verificable:** registrar los hashes del último despliegue confirmado.
5. **Protección contra cambios externos:** no sobrescribir un archivo remoto que haya cambiado desde el último despliegue sin revisar el conflicto.
6. **Transferencia recuperable:** respaldar cada archivo remoto que vaya a reemplazarse.
7. **Datos separados del código:** base de datos, uploads, logs, sesiones y cachés tienen flujos propios.
8. **Validar antes y después:** una transferencia exitosa no demuestra por sí sola que la aplicación funciona.
9. **TLS verificado:** los despliegues normales no deben desactivar la validación del certificado.
10. **Registro solo después del éxito:** no actualizar el estado de despliegue si alguna transferencia o validación falla.
11. **Entorno de base explícito:** ninguna migración puede inferir su destino desde la conexión que resulte accesible; debe declarar `development` o `production` antes del respaldo y la escritura.

## 2. Archivo maestro privado `servicios.local.json`

Cada proyecto tendrá en su raíz un `servicios.local.json` privado y legible por herramientas, con secciones independientes para el proyecto, despliegue, bases por entorno y servicios externos:

```json
{
  "version": 2,
  "project": {
    "name": "NOMBRE_DEL_PROYECTO",
    "public_url": "https://ejemplo.com/"
  },
  "deployment": {
    "protocol": "ftps",
    "host": "servidor-ftps.ejemplo.com",
    "port": 21,
    "username": "USUARIO_FTPS",
    "password": "CONTRASENA_FTPS",
    "remote_path": "/",
    "verify_tls": true,
    "database_environment": "production"
  },
  "databases": {
    "development": {
      "label": "DEV - proyecto local",
      "host": "HOST_BASE_DEV",
      "port": 3306,
      "name": "NOMBRE_BASE_DEV",
      "username": "USUARIO_BASE_DEV",
      "password": "CONTRASENA_BASE_DEV",
      "charset": "utf8mb4"
    },
    "production": {
      "label": "PROD - sitio publicado",
      "host": "localhost",
      "port": 3306,
      "name": "NOMBRE_BASE_PROD",
      "username": "USUARIO_BASE_PROD",
      "password": "CONTRASENA_BASE_PROD",
      "charset": "utf8mb4"
    }
  },
  "deepseek": {
    "api_key": "CLAVE_API_DEEPSEEK",
    "base_url": "https://api.deepseek.com",
    "model": "deepseek-v4-flash",
    "timeout_seconds": 45
  }
}
```

Reglas:

- Es la fuente maestra local para automatismos y tareas operativas, no un archivo runtime que deba copiarse completo a producción.
- El despliegue normal consume solamente `deployment`. `deployment.database_environment` identifica la base asociada al sitio publicado, pero **no autoriza** migraciones.
- `databases.development` y `databases.production` son conexiones distintas y obligatorias. No conservar una sección ambigua `database` en la versión 2.
- Toda operación de base debe recibir el entorno exacto, resolver únicamente `databases.<entorno>` y mostrar en el preflight el nombre del entorno y una huella no reversible del destino. Nunca elegir una conexión porque es la primera que responde.
- La aplicación utiliza sus configuraciones runtime separadas. En este portal, DEV continúa leyendo `admin/config.local.php` y producción su propio `admin/config.local.php`; el archivo maestro no reemplaza ni se publica como runtime.
- Usar FTPS explícito o, cuando el servidor lo permita, preferir SFTP.
- La ruta debe ser la que ve la cuenta al autenticarse. Una cuenta confinada puede mostrar `/` aunque el proveedor la haya asociado internamente con `/public_html/proyecto`.
- Aplicar permisos locales `600`.
- Agregar `/servicios.local.json` al `.gitignore` del proyecto.
- Bloquear su acceso HTTP en Apache/Nginx.
- Excluirlo siempre de cualquier subida, copia, ZIP, log o captura de comandos.
- No mostrar usuarios, contraseñas ni claves API en salidas de diagnóstico. Las comprobaciones deben limitarse a estructura, tipos, presencia o valores redactados.
- La clave de DeepSeek debe permanecer en backend; nunca se inserta en JavaScript, HTML, respuestas de error o logs.
- La plantilla versionable es `servicios.example.json`; nunca debe contener datos reales.
- El formato es JSON estricto: usar comillas dobles, escapar caracteres cuando corresponda y validar sin imprimir su contenido. En este proyecto ejecutar `php tools/validar-servicios.php`, que comprueba versión, entornos, campos, selector y permisos, y solo muestra huellas no reversibles.
- Durante una migración desde otro formato, conservar el original dentro de `.deploy/respaldos-configuracion/`, con permisos `600`, hasta comprobar estructura y conexiones. No mantener dos copias activas de los mismos secretos.

## 3. Estado local del despliegue

El automatismo mantendrá un directorio privado `.deploy/`, ignorado por Git, bloqueado por el servidor web y excluido de toda transferencia:

```text
.deploy/
├── estado.json
├── ultimo-plan.json
└── respaldos/
    └── AAAA-MM-DD_HHMMSS/
```

`estado.json` registrará, como mínimo:

```json
{
  "version": 1,
  "servidor": "identificador-sin-secretos",
  "ruta_remota": "/",
  "desplegado_en": "AAAA-MM-DDTHH:MM:SSZ",
  "git_commit": "opcional",
  "archivos": {
    "index.php": {
      "sha256": "...",
      "bytes": 1234
    }
  }
}
```

El manifiesto representa los bytes que fueron enviados y verificados, no simplemente el último commit. Por eso también funciona durante una etapa con cambios sin commit.

Git conserva el historial humano y permite revertir código. El manifiesto conserva el estado operativo realmente publicado. Ninguno reemplaza al otro.

## 4. Exclusiones mínimas

La lista concreta debe revisarse para cada proyecto. Como base, nunca desplegar:

```text
.git/
.deploy/
.agents/
.codex/
tools/
.ea-php-cli.cache y cualquier copia anidada
subir.txt
subir.example.txt
servicios.local.json
servicios.example.json
*.local.php
.env
.env.*
error_log
*.log
README.md
CONTINUIDAD.md
ESTANDAR_DESPLIEGUE_FTPS.md
node_modules/
vendor/ solo cuando se instale o genere en el servidor
tests/
capturas/
archivos temporales del editor o del sistema
```

Antes de transferir, revisar también enlaces simbólicos. Los accesos internos del entorno de trabajo y los enlaces de selección de PHP del hosting no son archivos de la aplicación y no deben copiarse. El primer despliegue real del Portal de Noticias confirmó esta exclusión: FTPS no soportó esos enlaces y las carpetas internas vacías tuvieron que retirarse del destino.

No aplicar exclusiones genéricas a ciegas. Por ejemplo, un proyecto PHP sin Composer en producción puede necesitar `vendor/`, mientras que otro lo genera en el servidor.

Tratar aparte:

- Configuración privada de producción.
- Base de datos y migraciones.
- Imágenes, audios y documentos cargados por usuarios.
- Cachés, sesiones y logs.
- Archivos generados por la aplicación.

Un despliegue de código nunca debe vaciar ni sincronizar destructivamente esas rutas.

## 5. Primera publicación completa

### 5.1 Preflight local

1. Confirmar la carpeta y el repositorio correctos.
2. Leer las instrucciones y continuidad del proyecto.
3. Revisar `git status --short --branch` sin descartar cambios.
4. Validar que `servicios.local.json` esté protegido e ignorado.
5. Confirmar que el esquema sea versión 2, que existan `development` y `production`, y que `deployment.database_environment` señale el entorno esperado.
6. Construir la lista exacta de archivos publicables.
7. Revisar secretos, datos de clientes, dumps, logs y archivos de prueba.
8. Ejecutar las validaciones aplicables: sintaxis, tests, `git diff --check`, integridad de assets y configuración.
9. Presentar el resumen de archivos y tamaño antes de la escritura externa.

Cuando una aplicación necesite una parte del archivo maestro, generar una configuración runtime mínima y específica; nunca publicar `servicios.local.json` completo. En este portal, la integración editorial con DeepSeek usa solamente `admin/servicios.runtime.local.json`, derivado localmente así:

```bash
jq '{deepseek: .deepseek}' servicios.local.json | install -m 600 /dev/stdin admin/servicios.runtime.local.json
```

El runtime queda ignorado por Git y bloqueado por Apache mediante el patrón `*.local.json`. En el despliegue se transfiere por un paso privado, explícito y separado del código, únicamente al directorio `admin/`; no debe contener `deployment`, FTP ni base de datos. Después de instalarlo se valida su lectura desde PHP y que una petición HTTP directa sea rechazada, sin imprimir la clave.

### 5.2 Preflight remoto de solo lectura

1. Resolver el servidor y conectar mediante TLS verificado.
2. Confirmar que la cuenta está restringida a la carpeta prevista.
3. Listar el destino sin modificarlo.
4. Comprobar espacio disponible cuando el hosting lo permita.
5. Detectar si el destino está vacío o contiene una instalación previa.
6. Detenerse ante un certificado vencido, nombre incorrecto o cadena incompleta. Una excepción TLS solo puede usarse para un diagnóstico puntual expresamente autorizado, nunca como configuración normal del despliegue.

### 5.3 Transferencia inicial

1. No usar borrado espejo (`--delete`).
2. En una carpeta vacía, subir primero assets y dependencias; dejar puntos de entrada y configuración pública para el final.
3. Si ya existe una instalación, respaldarla o aplicar el flujo incremental archivo por archivo.
4. Transferir la configuración privada mediante un paso separado y con permisos restrictivos, solo si el usuario lo autoriza.
5. Importar la base o ejecutar migraciones mediante su procedimiento específico, nunca por inferencia.
6. Verificar tamaño y contenido de los archivos transferidos.
7. Probar por HTTP las rutas principales y revisar errores del servidor sin modificar datos reales innecesariamente.
8. Crear `estado.json` únicamente después de que el usuario confirme el resultado.

## 6. Publicación incremental

Antes de escribir, generar `ultimo-plan.json` con cuatro grupos:

- `agregar`: existe localmente y no figura en el último estado.
- `modificar`: existe en ambos lugares y cambió su SHA-256.
- `sin_cambios`: coincide con el último estado.
- `eliminar_candidato`: figuraba desplegado pero ya no existe localmente.

El plan debe mostrar rutas y tamaños, nunca secretos.

### 6.1 Control de conflicto remoto

Para cada archivo que será reemplazado:

1. Descargar temporalmente la versión remota.
2. Calcular su SHA-256.
3. Compararlo con el hash registrado en `estado.json`.
4. Si no coincide, detener ese archivo y avisar que alguien o algún proceso modificó el servidor fuera del flujo normal.
5. No decidir automáticamente si gana la copia local o la remota.

### 6.2 Respaldo y transferencia

Para cada archivo autorizado:

1. Guardar la versión remota anterior en `.deploy/respaldos/AAAA-MM-DD_HHMMSS/`, conservando su ruta relativa.
2. Subir la nueva versión con un nombre temporal en el mismo directorio remoto.
3. Renombrarla al nombre definitivo cuando la transferencia termine. Esto reduce el tiempo en que un archivo PHP o JavaScript puede quedar incompleto.
4. Descargar o leer nuevamente la copia remota y verificar tamaño y SHA-256.
5. Ejecutar la validación específica del cambio y una comprobación breve de regresión.
6. Actualizar `estado.json` solamente si todas las verificaciones fueron correctas.

Si el servidor no permite renombrado atómico, documentar esa limitación y usar modo mantenimiento cuando el riesgo lo justifique.

## 7. Eliminaciones

Los archivos de `eliminar_candidato` se muestran en un bloque separado.

- No se eliminan durante una publicación normal.
- Requieren confirmación explícita con las rutas exactas.
- Antes de borrar, se descargan al respaldo de esa publicación.
- Se confirma después que desaparecieron únicamente los objetivos autorizados.
- Directorios, uploads y datos generados requieren una revisión adicional.

Nunca ejecutar una sincronización destructiva sobre `/`, una ruta vacía, una variable sin validar o una carpeta distinta de la configurada.

## 8. Base de datos

FTP/FTPS despliega archivos; no despliega correctamente el estado de una base de datos.

Toda modificación de esquema o datos debe incluir:

1. Entorno escrito explícitamente: `development` o `production`.
2. Resolución exclusiva de `databases.<entorno>`; rechazar `database` legacy y cualquier selector ausente o desconocido.
3. Preflight que muestre entorno, etiqueta y huella del destino, y compruebe su correspondencia con el runtime del ambiente sin imprimir credenciales.
4. Respaldo previo identificado con el entorno en su nombre; un respaldo de DEV nunca satisface el requisito para PROD.
5. Migración versionada e idempotente cuando sea posible.
6. Autorización separada del despliegue de archivos, indicando migración y entorno.
7. Ejecución separada del código.
8. Verificación posterior sobre **el mismo entorno**: esquema, permisos, filas semilla e invariantes.
9. Estrategia de recuperación documentada.

Si una conexión solo funciona desde el servidor —por ejemplo `production.host = localhost`— no sustituirla por DEV. Usar un mecanismo remoto autenticado, efímero y de alcance exacto; retirar ejecutor y token después de comprobar su desaparición. Un diagnóstico remoto no debe quedar públicamente accesible ni devolver credenciales, nombres de usuarios o información innecesaria.

Nunca ejecutar `schema.sql` completo sobre una instalación existente salvo que el procedimiento lo exija expresamente.

## 9. Recuperación

Ante una falla:

1. Detener las transferencias pendientes.
2. No actualizar `estado.json`.
3. Identificar exactamente qué archivos llegaron a cambiar.
4. Restaurar desde `.deploy/respaldos/` los archivos afectados.
5. Repetir las validaciones de sintaxis y HTTP.
6. Registrar el resultado y la causa.

El respaldo local de una publicación se conserva hasta que la versión nueva quede validada y exista otra forma segura de reconstruir la anterior mediante Git o artefactos versionados.

## 10. Comandos de trabajo acordados

- **«Prepará el despliegue»**: operación de solo lectura. Calcula diferencias, valida y presenta el plan; no escribe en el servidor.
- **«Subí los cambios actuales»**: autoriza a transferir los archivos agregados/modificados del plan claro y acotado. No autoriza borrados ni migraciones implícitas.
- **«Subí todo el proyecto»**: autoriza la publicación inicial completa con las exclusiones documentadas. No autoriza borrar archivos remotos preexistentes.
- **«Eliminá estos archivos remotos»**: debe incluir o aprobar la lista exacta de rutas.
- **«Ejecutá la migración en DEV/PROD»**: autoriza únicamente la migración y el entorno identificados, después de presentar huella, respaldo, alcance y validaciones. Si el entorno no está claro, detenerse y preguntar.

Al terminar, el informe debe indicar:

- Archivos agregados, modificados y, si fueron autorizados, eliminados.
- Archivos omitidos por exclusión.
- Validaciones realizadas y sus límites.
- Resultado de la verificación remota.
- Estado del respaldo y posibilidad de recuperación.
- Problemas pendientes, como certificados o configuración del hosting.

## 11. Diferencia respecto de los paquetes `update`

El paquete descargable sigue siendo apropiado cuando el cliente instala manualmente una versión o cuando se necesita un artefacto transportable con `MANIFEST`, checksums e instrucciones.

El despliegue incremental directo reutiliza esos mismos principios, pero elimina el ZIP intermedio:

- El manifiesto vive en el estado local de despliegue.
- Los hashes deciden qué archivos cambiaron.
- Los respaldos reemplazan la reversión manual del paquete.
- La transferencia va directamente al servidor autorizado.
- El resultado se comprueba inmediatamente en el entorno real.

Ambos procedimientos deben conservar exclusiones, validaciones, trazabilidad y eliminación explícita.

## 12. Lista de control breve

### Antes

- [ ] Proyecto y ruta confirmados.
- [ ] Credenciales protegidas.
- [ ] Certificado TLS válido.
- [ ] Estado Git revisado sin descartar trabajo.
- [ ] Lista de exclusiones revisada.
- [ ] Plan de archivos calculado.
- [ ] Conflictos remotos descartados.
- [ ] Validaciones locales correctas.
- [ ] Respaldo previsto.

### Después

- [ ] Transferencias finalizadas sin error.
- [ ] Tamaños y hashes remotos verificados.
- [ ] Sintaxis y HTTP comprobados.
- [ ] Flujo solicitado validado.
- [ ] Estado de despliegue actualizado.
- [ ] Respaldo conservado.
- [ ] Informe entregado sin secretos.

## 13. Alta de un proyecto nuevo

Cuando el usuario solicite aplicar este estándar en otro proyecto, revisar primero la documentación y los archivos disponibles. Preguntar solamente por la información que no pueda comprobarse localmente.

### 13.1 Información mínima

1. Carpeta local exacta del proyecto.
2. Tipo de publicación: inicial completa o incremental.
3. Protocolo disponible: preferentemente SFTP; en su defecto, FTPS explícito.
4. Servidor, puerto, usuario y método de autenticación.
5. Ruta remota efectiva vista por esa cuenta.
6. URL pública donde se validará la aplicación.
7. Confirmación de que la cuenta está limitada al proyecto o identificación precisa del alcance permitido.
8. Carpetas de datos persistentes que no deben sincronizarse: uploads, documentos, logs, sesiones, cachés u otras.
9. Configuración privada requerida en producción y forma autorizada de instalarla.
10. Conexiones separadas de base para DEV y PROD, entorno asociado al despliegue, migraciones y procedimiento de respaldo.
11. Comandos de validación propios del proyecto.
12. Requisitos de permisos, propietario de archivos o modo mantenimiento.

### 13.2 Preguntas que deben hacerse solo cuando correspondan

- ¿El destino está vacío o contiene una instalación en uso?
- ¿Debe copiarse contenido persistente en la primera publicación?
- ¿Las bases DEV y PROD ya existen o alguna necesita importación/migración?
- ¿Hay archivos modificados directamente en el servidor que deban conservarse?
- ¿Existe una ventana de mantenimiento o la publicación debe hacerse sin interrupción?
- ¿Qué rutas públicas representan el flujo mínimo que debe probarse después?
- ¿Está autorizado un respaldo remoto/local antes de reemplazar archivos?
- ¿Deben eliminarse archivos obsoletos? Si la respuesta es sí, solicitar la lista o aprobación exacta.

No volver a pedir datos que ya estén completos y sean verificables en `servicios.local.json`, la documentación del proyecto o el manifiesto del último despliegue. Sí volver a confirmar cualquier dato ambiguo que pueda dirigir una escritura hacia otra carpeta, servidor o base de datos.

### 13.3 Secuencia de incorporación

1. Confirmar el checkout y leer las instrucciones del proyecto.
2. Revisar el estado Git sin modificarlo.
3. Validar `servicios.local.json` versión 2 de forma redactada, con DEV, PROD y selector de despliegue explícitos.
4. Proteger `servicios.local.json` y preparar las exclusiones locales.
5. Probar DNS, TLS y conexión de solo lectura.
6. Confirmar la ruta remota mediante un archivo de prueba cuando sea necesario.
7. Identificar datos persistentes, configuración privada y bases DEV/PROD; comprobar que no sean confundibles antes de migrar.
8. Generar el primer plan de despliegue.
9. Presentar alcance, exclusiones, validaciones y recuperación antes de la primera escritura.
10. Ejecutar únicamente la publicación autorizada y registrar el estado verificado.

Si falta una decisión que pueda cambiar materialmente el resultado, detenerse y preguntarla. Si los datos son suficientes y el pedido autoriza claramente la acción, continuar sin preguntas redundantes.

## 14. FTPS en servidores cPanel/WHM con varias cuentas

### 14.1 Alcance del certificado

En cPanel/WHM el servicio FTP no usa SNI por cuenta. ProFTPD presenta un único certificado de servicio para todas las cuentas alojadas en el mismo servidor. Por lo tanto:

- La corrección del certificado y de su cadena se realiza **una vez por servidor**, no una vez por cuenta o proyecto.
- Todas las cuentas del mismo VPS deben conectarse mediante el hostname canónico cubierto por ese certificado.
- Cada proyecto conserva su propio usuario, contraseña y ruta confinada en `servicios.local.json`; compartir hostname no amplía el acceso de ninguna cuenta.
- Un VPS distinto requiere su propio preflight y, si corresponde, su propia corrección del servicio FTPS.
- No usar `ftp.dominio-del-cliente` por comodidad si el certificado global no contiene ese nombre. Un DNS que apunta al servidor no reemplaza la validación del hostname.

### 14.2 Caso validado en el cloud actual

Validado el **25 de agosto de 2026** sobre cPanel & WHM 136.0.36, AlmaLinux 9.8 y ProFTPD 1.3.9.

- Hostname FTPS canónico: `vps-4962765-x.dattaweb.com`.
- Puerto: `21`, FTPS explícito mediante `AUTH TLS`.
- El certificado de Let's Encrypt era válido y no estaba vencido, pero al usar `ftp.digitales.uy` existía `hostname mismatch`.
- ProFTPD entregaba solamente el certificado hoja y omitía la cadena intermedia, provocando `unable to get local issuer certificate`.
- WHM/cPanel en el puerto 2087 sí entregaba correctamente la cadena completa del mismo certificado.
- En **WHM → Configuración del servicio → Administrar los certificados SSL de servicio**, se aplicó el certificado de cPanel/WHM al servicio FTP. No usar **Restablecer certificado**, porque puede reemplazarlo por uno autofirmado.
- La configuración activa de ProFTPD contenía `TLSRSACertificateFile` y `TLSRSACertificateKeyFile`, pero no `TLSCertificateChainFile`.
- cPanel ya mantenía `/var/cpanel/ssl/ftp/ftpd-ca.pem` con los dos certificados de la cadena. Se comprobó antes de usarlo con:

```bash
openssl verify -untrusted /var/cpanel/ssl/ftp/ftpd-ca.pem /etc/ftpd-rsa.pem
```

El resultado obligatorio fue `/etc/ftpd-rsa.pem: OK`.

### 14.3 Corrección aplicada en ProFTPD

Antes de editar se guardó un respaldo recuperable:

```text
/root/proftpd.conf.before-chain-20260825
```

Se agregó dentro del bloque TLS de `/etc/proftpd.conf`:

```apache
TLSCertificateChainFile /var/cpanel/ssl/ftp/ftpd-ca.pem
```

Luego se ejecutaron, en este orden:

```bash
proftpd -t
/usr/local/cpanel/scripts/restartsrv_proftpd --restart
```

No reiniciar si `proftpd -t` no informa `Syntax check complete`. Ante un error, restaurar primero el respaldo y volver a validar.

La verificación externa final debe usar el hostname canónico y no debe contener `-insecure` ni otra excepción:

```bash
openssl s_client \
  -connect vps-4962765-x.dattaweb.com:21 \
  -starttls ftp \
  -servername vps-4962765-x.dattaweb.com \
  -verify_return_error \
  -verify_hostname vps-4962765-x.dattaweb.com
```

El resultado confirmado fue `Verify return code: 0 (ok)`, con cadena desde el certificado del servidor hasta `ISRG Root X1`. También se autenticó la cuenta confinada y se listó `/` mediante `curl --ssl-reqd`, sin `--insecure`.

Después del éxito:

- Actualizar `deployment.host` de cada `servicios.local.json` del mismo VPS al hostname canónico.
- Marcar `tls_certificate_verified: true` únicamente después de una conexión real validada.
- No copiar usuarios ni contraseñas entre proyectos.
- No volver a aceptar automáticamente un certificado distinto, vencido o con cadena incompleta.

### 14.4 Persistencia y control preventivo

`/etc/proftpd.conf` puede ser regenerado por cPanel al reconstruir o cambiar la configuración FTP. La directiva manual podría desaparecer. Por eso cada despliegue debe validar TLS **antes de autenticarse o transferir archivos**; si la cadena vuelve a fallar, el proceso debe detenerse sin subir nada.

Después de una actualización de cPanel, cambio de servidor FTP, renovación anómala o reconstrucción de ProFTPD:

1. Comprobar que `TLSCertificateChainFile /var/cpanel/ssl/ftp/ftpd-ca.pem` siga presente.
2. Confirmar que `openssl verify` continúe devolviendo `OK`.
3. Ejecutar `proftpd -t` antes de cualquier reinicio.
4. Reiniciar ProFTPD solo con sintaxis válida.
5. Repetir la verificación externa de hostname y cadena.

Si esta corrección tuviera que reponerse repetidamente, resolver su persistencia con soporte de cPanel o un mecanismo administrado del servidor; no ocultar el problema reintroduciendo `--insecure`.

## 15. Arquitectura reusable de trabajo

Este estándar separa responsabilidades para que desarrollar, desplegar y migrar no sean una única operación ambigua:

```text
Código versionable
      │
      ├── validación local ───────────────► plan incremental + hashes
      │                                           │
      │                                           ▼
      │                                    despliegue FTPS
      │                                           │
      ▼                                           ▼
runtime DEV                                runtime PROD
admin/config.local.php                     admin/config.local.php
      │                                           │
      ▼                                           ▼
databases.development                      databases.production

servicios.local.json v2 = mapa maestro privado y selector explícito
.deploy/                  = estado, planes y respaldos recuperables
```

Contratos:

1. **Código:** Git y el manifiesto deciden qué bytes cambiaron; una publicación no borra datos ni ejecuta SQL por sí sola.
2. **Control local:** `servicios.local.json` conoce todos los destinos, pero nunca es runtime ni se publica.
3. **Runtime por ambiente:** cada instalación conserva únicamente las credenciales que necesita. DEV y PROD no comparten automáticamente `config.local.php`.
4. **Datos:** una migración declara el entorno, comprueba la correspondencia con su runtime, respalda esa base, ejecuta una pieza idempotente y valida invariantes en el mismo destino.
5. **Servicios externos:** cada integración recibe un runtime mínimo; por ejemplo DeepSeek no recibe FTP ni credenciales de base.
6. **Persistencia:** uploads, documentos y otros datos generados quedan fuera de sincronizaciones de código y de eliminaciones implícitas.
7. **Recuperación:** cada reemplazo conserva la versión remota anterior y cada cambio de datos conserva un respaldo identificado por ambiente.
8. **Confirmación:** las verificaciones automáticas registran `verified_pending_user_confirmation`; la aprobación del usuario convierte ese resultado en baseline confirmado.

Para iniciar un proyecto nuevo se copian `servicios.example.json`, este documento y el validador seguro. Luego se completan DEV, PROD, despliegue, persistentes y runtimes específicos antes de la primera publicación. El método puede usar FTPS, SFTP u otro transporte, pero la separación entre código, secretos, ambientes, datos, respaldo y confirmación permanece igual.
