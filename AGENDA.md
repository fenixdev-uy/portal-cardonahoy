# Agenda — Portal Cardona Hoy

Última actualización: **29 de agosto de 2026**.

Este archivo es la **única agenda aplicable a `portal-cardonahoy`**. La aplicación nace del baseline aprobado `5ec3e42`, pero no comparte configuración privada, base de datos, uploads, despliegue ni remoto con RS Medios.

## Prioridad activa

1. Crear un repositorio GitHub privado exclusivo y configurar `origin` sin reutilizar credenciales de RS Medios.
2. Crear una base de datos DEV exclusiva clonando la base preparada de `portal-base`.
3. Crear `admin/config.local.php` y `servicios.local.json` privados para Cardona Hoy.
4. Configurar identidad, logos, SEO, administradores y contenido propios del cliente.

## Pendientes posteriores

1. Validar que Cardona Hoy funcione de forma autónoma sin leer ninguna configuración o dato de RS Medios.
2. Preparar posteriormente sus destinos PROD y el primer despliegue mediante autorización separada.

## Mejoras futuras sin etapa activa

1. Mantener el baseline funcional heredado y aplicar aquí solamente las particularidades aprobadas para Cardona Hoy.
2. Trasladar mejoras comunes desde `portal-base` mediante commits pequeños y revisados.

## Regla de trabajo

- Comenzar por la prioridad activa, salvo indicación expresa del usuario.
- Mantener cambios pequeños, verificables y separados en commits descriptivos.
- Al completar un punto, retirarlo de esta agenda y registrar el cierre técnico en `CONTINUIDAD.md`.
- No desplegar, migrar bases de datos, eliminar archivos ni modificar datos persistentes por inferencia.
