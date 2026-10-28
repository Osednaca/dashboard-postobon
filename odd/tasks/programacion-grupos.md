# Programación y grupos

## Objetivo y alcance autorizado
Permitir seleccionar y eliminar varias programaciones, dirigir una programación a varios dispositivos, quitar dispositivos de un grupo al editarlo y reutilizar el nombre de un grupo eliminado. Conservar permisos, historial, compatibilidad de programaciones existentes y comportamiento de dispositivos WL35/Z2. No se autorizan operaciones remotas, despliegues ni comandos a dispositivos reales.

## Evidencia y configuración
- Repositorio `dashboard-postobon`, rama `codex/programacion-grupos`; base: HEAD inicial de `codex/postobon-mobile-api`.
- Exploración delegada: programación guarda un único `device_id`; ejecución y presentación dependen de él. La lista se reemplaza periódicamente. Grupos usa borrado lógico, validación de nombre y un índice único global; edición agrega miembros pero no los retira.
- TDD OFF por decisión explícita registrada en `odd/tasks/device-content-preview.md`; runner `php artisan test`. Verificación adicional: `node --test tests/js/*.test.mjs`, `npm run build`, Pint y pruebas enfocadas cuando correspondan.
- RDD disabled/unmanaged: estado global OFF comprobado. Engram no está disponible; espejo completo pendiente en `odd/programacion-grupos/tasks`, proyecto `dashboard-postobon`.
- Estrategia `ask-on-risk`: usuario eligió `feature-branch-chain`. Pronóstico 800–1600 líneas de autor; 400 por tarea es orientativo, sin sacrificar claridad o pruebas. Registrar commits y límites de entregas; no crear PR ni publicar sin autorización.
- Conservar modificación preexistente en `docs/DESPLIEGUE_LATENCIA.md`.

## Tareas y criterios
- [ ] T1 — Permitir quitar miembros al editar grupos, preservando dispositivos reasignados concurrentemente y la coherencia de proveedores. Ruta delegada: preparación y múltiples archivos no triviales. Verificar permisos y pertenencia con pruebas aisladas.
- [ ] T2 — Permitir crear un grupo con el nombre de uno eliminado, conservando historial y unicidad entre activos también en base de datos. Ruta delegada: validación, migración y pruebas. Verificar creación, duplicados activos y migración.
- [x] T3 — Seleccionar y eliminar varias programaciones desde la lista, con permisos y selección coherente durante actualizaciones. Ruta delegada: interfaz, servidor y pruebas en múltiples archivos. Verificar selección y eliminación.
- [ ] T4 — Seleccionar varios dispositivos para una programación y ejecutarla solo sobre ellos, manteniendo compatibilidad con registros existentes. Ruta delegada: persistencia, solicitudes, ejecución, interfaz y pruebas. Verificar control y contenido, edición, validación y compatibilidad.

## Progreso y siguiente paso
Exploración terminada. Implementar y verificar cada unidad; registrar commit, resultado, límites de rollback y cantidad de líneas. La copia Engram está pendiente. No se ha ejecutado una migración en producción ni interactuado con equipos reales.

- T3: commit `6d89a44` (215 líneas incluyendo documento); PHP 4 pruebas/43 aserciones, Node 8 pruebas y Pint aprobados. Refresco pausado durante selección; lote autorizado y transaccional. Rollback: commit T3, manteniendo otras unidades. RDD disabled/unmanaged.
