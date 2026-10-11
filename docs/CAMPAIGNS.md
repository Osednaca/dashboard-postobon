# Campañas

Crear y editar permiten marcar **Campaña permanente**: se conserva la fecha de inicio y se guarda `end_date=null`, sin límite temporal. Desmarcarla requiere una fecha de fin válida, igual o posterior al inicio. La lista y el detalle muestran “Permanente · Sin fecha de fin” para registros sin fecha final, incluidos los existentes.

La casilla desactiva el calendario final. El servidor también normaliza la fecha final, aunque se envíe una fecha antigua al marcar permanente. El indicador `is_permanent` es una opción del formulario; no añade columna ni requiere migración. Actualizaciones parciales sin este indicador preservan las fechas omitidas y validan el rango contra las fechas guardadas. Fechas malformadas generan errores de validación.

Mantiene las acciones actuales de activación, pausa y finalización. Esta opción no añade programación ni manda instrucciones físicas al ventilador. El scheduler existente activa campañas mediante tareas explícitas; no hay finalización automática basada únicamente en `end_date`.

Verificación: `PermanentCampaignTest`, SQLite memory y HTTP aislado, cubre creación, cambios entre permanente/temporal, fechas inválidas, actualizaciones parciales y presentación de registros anteriores. No se ejecutan dispositivos ni servicios remotos.

## Selección de medios

Crear y editar comparten la biblioteca con vistas previas cuadradas, fotograma inicial de los videos, controles de reproducción e imágenes. **Agregar** y **Quitar** cambian la selección; **Subir** y **Bajar** establecen su orden. Reproducir una vista previa no selecciona ni envía ese medio a un ventilador. Los previews reutilizan la carga al entrar en pantalla y la pausa al ocultarse.

Los formularios restauran la selección después de un error. Un medio eliminado se muestra como no disponible para poder quitarlo, y el servidor impide guardar IDs inexistentes, borrados o repetidos. Campos y relaciones se guardan juntos en una transacción.

Web y API aceptan `media_ids` como arreglo ordenado; el CSV histórico `videos` continúa funcionando. En una actualización, omitir ambos conserva los medios actuales; enviar una selección vacía los quita. Guardar una campaña, incluso activa, conserva el comportamiento existente y no publica órdenes físicas.

Verificación: `CampaignMediaSelectionTest` cubre renderizado, orden, validación, compatibilidad de API, restauración y rollback ante un fallo real de escritura del pivote; las pruebas de Node comprueban los controles y la independencia del ciclo de vida del preview.

## Destinatarios individuales

La pestaña Segmentación permite elegir equipos Z2 y WL35 de distintas ciudades. Ciudad y grupo son filtros de la lista, no destinatarios adicionales. Cambiar un filtro conserva los equipos ya seleccionados, visibles en la lista de destinatarios. Los equipos sin ciudad también pueden seleccionarse. La información proviene de registros locales; abrir o guardar el formulario no consulta ni envía órdenes a la nube.

`target_devices` guarda claves normalizadas `z2:MAC` y `wl35:device_id`. Z2 omite separadores y utiliza mayúsculas; WL35 conserva su identidad exacta. El catálogo deduplica identidades físicas, conserva la primera fila local por ID y no expone contactos ni credenciales. La ciudad propia tiene prioridad sobre la ubicación vinculada. IDs desconocidos, eliminados o repetidos se rechazan antes de guardar; después de un error la selección sigue editable.

`null` conserva la asignación histórica Z2 mediante `device_campaign`; `[]` significa que no hay destinatarios y nunca vuelve al pivote anterior. PATCH sin el campo conserva la selección. Los aliases históricos `cities` y `groups` siguen aceptándose con validación de sus elementos y vaciado explícito, pero no amplían una selección individual. El detalle de la campaña y las campañas asignadas en detalles Z2/WL35 reconocen las claves explícitas. Las estadísticas históricas siguen basándose en registros anteriores Z2; seleccionar un destinatario no inventa estadísticas ni confirma reproducción actual.

Guardar, activar y el scheduler mantienen sus acciones existentes, sin nuevas órdenes físicas. Los callbacks de agregar/quitar medios a una campaña activa publican sólo a sus destinatarios explícitos mediante `FleetUpload` y el job existente `PlayFleetMediaJob`. Envían el primer video según el orden guardado, no una playlist de múltiples videos. Una selección vacía o una campaña sin medios no manda órdenes y no detiene físicamente un ventilador. La rama histórica Z2 permanece compatible.

Esta publicación requiere driver de cola `database` o `redis` y `retry_after > 1900`, superior al timeout del job. Database ya usa 2100; Redis debe configurarse expresamente. Drivers inline o failover se rechazan. El video debe tener una fuente local o privada reconocida; la disponibilidad remota definitiva se comprueba en el job. Si falla el encolado, el callback informa un error y elimina su registro de operación; el cambio local de medios puede estar guardado, como en el flujo anterior. Una orden en cola no confirma reproducción: su progreso se consulta en Reproducción. No se ha probado hardware real.

La migración añade una columna JSON nullable y no cambia el pivote anterior. Su rollback se bloquea mientras existan selecciones explícitas, incluso vacías o en campañas borradas, porque el esquema anterior no puede representarlas. Antes de retirarla, exporta y resuelve esas selecciones; no conviertas automáticamente WL35 ni `[]` en el legado. Sólo se verificó el roundtrip en SQLite aislada; no se migró ninguna base real.

Verificación: `CampaignTargetSelectionTest` comprueba catálogo, selección entre ciudades/tipos, aliases y PATCH, validación, detalles, publicación exacta, callbacks, colas no aptas, selección vacía, legado y rollback seguro. Node verifica que filtros, restauración y eliminación conservan los destinatarios exactos.
