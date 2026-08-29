# Agenda — Portal Cardona Hoy

Última actualización: **29 de agosto de 2026**.

Este archivo es la **única agenda aplicable a `portal-cardonahoy`**. La aplicación nace del baseline aprobado `5ec3e42`, pero no comparte configuración privada, base de datos, uploads, despliegue ni remoto con RS Medios.

## Prioridad activa

1. Crear `servicios.local.json` privado para Cardona Hoy, conservando DEV y dejando PROD pendiente hasta contar con su destino real.
2. Configurar identidad, logos, SEO, administradores y contenido propios del cliente.

## Pendientes posteriores

1. Validar el acceso autenticado y la experiencia visual de Cardona Hoy como instalación autónoma.
2. Preparar posteriormente sus destinos PROD y el primer despliegue mediante autorización separada.

## Mejoras futuras sin etapa activa

1. Mantener el baseline funcional heredado y aplicar aquí solamente las particularidades aprobadas para Cardona Hoy.
2. Trasladar mejoras comunes desde `portal-base` mediante commits pequeños y revisados.

## Regla de trabajo

- Comenzar por la prioridad activa, salvo indicación expresa del usuario.
- Mantener cambios pequeños, verificables y separados en commits descriptivos.
- Al completar un punto, retirarlo de esta agenda y registrar el cierre técnico en `CONTINUIDAD.md`.
- No desplegar, migrar bases de datos, eliminar archivos ni modificar datos persistentes por inferencia.
