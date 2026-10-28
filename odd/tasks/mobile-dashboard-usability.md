# Mejoras móviles del dashboard

## Objetivo y alcance autorizado
Mejorar los desplazamientos de reproducción individual y el acceso al detalle de dispositivos en móvil; mostrar progreso real al subir videos; optimizar el calendario de Programaciones para móviles, confirmado por el usuario. Conservar funcionalidad, permisos y cambios locales ajenos. Sin despliegue ni operaciones remotas.

## Configuración y entrega
- TDD OFF: decisión previa del usuario documentada en `odd/tasks/device-content-preview.md:15`. Comprobaciones ordinarias: `php artisan test --compact --bootstrap=storage/framework/testing/isolated-suite-bootstrap.php`, `node --test tests/js/*.test.mjs`, `npm run build`, y Pint si cambia PHP.
- RDD: disabled/unmanaged, estado global comprobado. Engram no disponible; copia `odd/mobile-dashboard-usability/tasks` pendiente.
- Estrategia: ask-on-risk; elección previa registrada feature-branch-chain. Estimación 350–650 líneas de código y pruebas; la guía de 400 líneas por tarea es orientativa, no un límite. Sin PR solicitado.
- Exploración delegada: cuatro flujos repartidos entre múltiples vistas, estilos, controladores y pruebas. Implementación delegada por cambios en dos o más archivos no triviales. Cada tarea se verifica y cierra con commit convencional.

## Tareas y criterios de aceptación
- [x] T1 Reproducción: al seleccionar dispositivo individual en móvil desplazar al formulario; al terminar reproducir, mostrar confirmación o fallo desplazando al estado. Conservar otros modos. Ruta delegada; verificar interacción y regresiones.
- [x] T2 Dispositivos: ampliar acceso táctil al detalle sin interferir con selección ni acciones. Ruta delegada; verificar ambos roles y controles.
- [ ] T3 Medios: mostrar progreso de transferencia y distinguir procesamiento del servidor; conservar validación, redirecciones y selección de archivo. Ruta delegada; verificar éxito y fallo.
- [ ] T4 Calendario: optimizar móvil y permitir ver más eventos por día; confirmado por el usuario que se refiere a Programaciones. Ruta delegada; verificar múltiples eventos y navegación.

## Progreso y siguiente paso
Mapa de código completado por exploración delegada. Usuario confirmó que el calendario es el de Programaciones; avanzar con T1–T4. No se ha accedido a servidores ni dispositivos reales. La modificación previa `docs/DESPLIEGUE_LATENCIA.md` queda fuera del alcance.


- T1: commit f6eef2f; Node enfocado 3/3 y PHPUnit DeviceModelDisplayTest 4 pruebas/45 aserciones aprobados; diff --check aprobado. Sin móvil físico ni operaciones remotas. Rollback: vista instant-play y prueba instant-play-scroll. RDD disabled/unmanaged.


- T2: commit 58decf5; PHPUnit focal 7 pruebas/165 aserciones, Pint y comprobación Chrome headless de áreas táctiles aprobados. Sin teléfono físico. Rollback: vista de lista, estilos y pruebas de dispositivos. RDD disabled/unmanaged.

