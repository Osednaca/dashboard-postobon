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
