# Publicación, reproducción y limpieza de campañas

El ciclo de campañas registra cada entrega por dispositivo y archivo. Antes de publicar, prepara copias de los videos anteriores en el servidor y solicita retirarlos de la SD; al pausar o finalizar la última campaña activa, vuelve a cargar los videos respaldados. Los archivos que otra campaña activa necesita se protegen. Se bloquea la publicación cuando no se puede identificar o respaldar con seguridad el contenido anterior.

## Uso del dashboard

1. Selecciona los destinatarios, agrega los videos en el orden deseado y define inicio y fin. Una campaña permanente no tiene vencimiento automático.
2. Activa la campaña. Si falta la duración de un video, el dashboard muestra «Preparando duración de videos» mientras un worker la obtiene del archivo. No se inventa un tiempo; al finalizar la preparación se activa la campaña si sus datos y vigencia no cambiaron.
3. Si la preparación falla, revisa el video indicado y vuelve a activar. La publicación respeta la fecha de inicio y procesa los videos por dispositivo; las órdenes no se envían todas a la vez.
4. Consulta **Publicación y limpieza** en el detalle y usa **Actualizar estado** para obtener información reciente. El estado general «Finalizada» no significa que todas las SD estén limpias.

Los destinatarios quedan fijados al guardar. Las entregas registradas se conservan aunque se quite un video o se elimine lógicamente una campaña, para que la limpieza pueda terminar sin perder el vínculo con los archivos enviados.

## Estados que requieren atención

El aviso **No se pudo preparar o actualizar la campaña** muestra errores de validación o preparación incluso cuando todavía no existe ninguna entrega. Corrige el video, su duración o la selección indicada antes de volver a activarla; no interpretes «Programada» como publicación completada.

| Estado visible | Significado y acción |
|---|---|
| Publicación pendiente | Aún no se ha completado el envío. Revisa el error mostrado y la conexión del equipo. |
| Esperando confirmación de publicación | Se espera evidencia del dispositivo antes de continuar con el siguiente video. |
| Publicado | El archivo está disponible para la secuencia. No equivale a observar su reproducción en ese instante. |
| Borrado pendiente de confirmación | La aceptación de una orden no demuestra la eliminación física. Se espera evidencia posterior. |
| Borrado confirmado | El protocolo aportó la evidencia de retirada exigida para ese modelo. |
| Ausente del reporte; borrado físico sin verificar | El modelo 42 dejó de reportar el archivo. Su protocolo no demuestra que ya no exista físicamente en la SD. |
| Conservado por otra campaña | Otra campaña vigente mantiene una referencia al archivo; se revisará su retirada cuando deje de necesitarlo. |
| Conservado por seguridad | No hay una identidad física suficiente para retirarlo sin arriesgar otro archivo. Revisa el motivo mostrado. |
| Requiere revisión | El resultado es ambiguo y repetir una orden podría borrar otro video. No fuerces reintentos por un índice antiguo. |

En el modelo 42 se utiliza una lista reportada por el dispositivo después de enviar la orden, separada de la playlist que la nube desea aplicar. La ausencia en ese reporte permite verificar que el dispositivo dejó de listar el video, pero no demuestra un inventario completo de la SD; no se presenta como borrado físico confirmado. En el modelo 35, la eliminación exige la reducción de conteo confirmada por la respuesta del firmware. Si se pierde esa respuesta, los índices pueden haber cambiado y el sistema evita repetir el borrado a ciegas.

La secuencia se coordina desde el servidor según la duración de cada video. No ofrece sincronización exacta entre pantallas: la cola, la latencia y la disponibilidad del equipo afectan el momento del cambio. En un dispositivo con varias campañas vigentes, gana la de mayor prioridad; en empate, la de menor ID. Los archivos compartidos siguen protegidos aunque otra campaña controle la reproducción.

Antes del retiro se guarda una copia verificable de cada video anterior identificable en el servidor. Si falta un original o no se puede establecer su identidad, no se procede con el borrado y el dispositivo muestra el motivo. Varias campañas comparten el registro anterior y las copias. Cuando no queda una campaña activa, se libera el cursor sin apagar el equipo y se restauran los videos anteriores desde el servidor; si es posible, también se solicita la selección previa. Las operaciones pendientes se retoman tras una desconexión o reinicio.

En el42 la captura espera a que terminen descargas, retiradas e intenciones de video pendientes. El heartbeat del proveedor despeja `desiredVideo` al confirmar `DisplayImageId`; el dashboard no lo limpia ni considera suficiente que coincida el nombre en un reporte anterior.

Se recupera la selección individual conocida, no un modo o secuencia que el equipo no reporte. En el 42, una orden aceptada puede quedar encolada hasta un heartbeat y no confirma reproducción física. En el 35 se valida conteo e identidad de instalación antes de actuar; una respuesta ambigua requiere revisión. Un estado vacío o una selección anterior desconocida se muestran explícitamente, sin elegir un video arbitrario. La SD del 35 no ofrece identidad estable para archivos históricos desconocidos: cambios externos con idéntico conteo no son detectables.

Las campañas ya publicadas antes de esta migración no tienen un registro anterior recuperable. Se muestra esa limitación y se conservan los archivos existentes cuya propiedad no puede probarse. No se inventa un historial a partir de la selección actual. Mientras haya una sesión abierta, el dashboard bloquea modificaciones manuales de contenido, formato y borrado de originales42 protegidos; finaliza las campañas y espera la recuperación antes de intervenir. Esta protección no cubre aplicaciones externas ni órdenes enviadas directamente a los proveedores.

Si una intervención externa cambia la identidad o el conteo esperado del35, la recuperación queda pendiente y se mantiene el bloqueo de contenido. Reintentar por sí solo no corrige esa incompatibilidad: requiere revisión controlada del estado y del registro fuera del flujo automático. No se ofrece una acción que descarte el registro o seleccione un índice incierto.

La provisión de archivos todavía puede apagar temporalmente el equipo para evitar que una carga interrumpa la secuencia ganadora. El relevo entre campañas ya publicadas no envía una orden de apagado. Si la campaña termina durante esa provisión y no queda otra activa, se solicita el encendido antes de continuar la limpieza. Un fallo de conexión conserva el intento pendiente hasta poder confirmar el encendido.

Si un equipo pierde conexión, el servidor no puede garantizar que deje de reproducir o borre su SD a la hora exacta de fin. Mantiene la operación pendiente y conserva el error, en lugar de presentar una limpieza ficticia. La pausa y la finalización impiden que trabajos obsoletos sigan avanzando la campaña.

## Requisitos de operación y despliegue

Los cambios requieren desplegar versiones compatibles del dashboard, la nube privada del modelo 42 y el gateway del modelo 35, además de aplicar la migración del registro de campañas. No habilites la limpieza automática con una versión antigua que solo devuelve estado optimista.

Aplica las migraciones antes de reiniciar los workers. Para revertir, detén scheduler y workers primero y conserva las copias y los registros de sesiones pendientes: eliminarlos impediría restaurar el contenido anterior. Las pruebas automatizadas usan SQLite y proveedores falsos; MySQL y la reproducción física requieren verificación de despliegue.

Configura `CAMPAIGN_BACKUP_DISK` para un disco local de Laravel que apunte a un volumen durable y compartido por los workers, y `CAMPAIGN_BACKUP_PERSISTENT=true` solo después de verificar que ese volumen sobrevive a recreaciones del contenedor. La aplicación no puede comprobar por sí sola la persistencia real del montaje. Reserva capacidad para todas las copias y consérvalas hasta que las sesiones pendientes se hayan recuperado.

- Usa una cola asíncrona `database` o `redis`; `sync` no sirve para este ciclo.
- Configura `retry_after` de esa conexión por encima de 1900 segundos. La conexión `database` tiene 2100 por defecto; para Redis configura `REDIS_QUEUE_RETRY_AFTER=2100` o un valor mayor que el timeout del trabajo.
- Usa un almacén de caché persistente y compartido por los procesos, como `database` o `redis`, para que los bloqueos por dispositivo coordinen publicación, reproducción y borrado. Una caché privada por proceso no proporciona esa protección.
- Mantén el scheduler activo. `campaigns:process` se agenda cada segundo y encola el trabajo; no debe realizar las transferencias largas en el proceso del scheduler.
- Mantén trabajadores dedicados a la cola `campaigns`. La configuración de Supervisor incluye dos procesos; ajusta su cantidad a la flota y la capacidad del servidor. Una transferencia larga de un dispositivo no debería bloquear el avance de todos los demás.
- La misma cola prepara la duración de medios antiguos. El worker necesita `ffprobe` (incluido en la imagen con FFmpeg), acceso a la biblioteca y almacenamiento temporal local suficiente; la descarga respeta los límites de la preparación de vistas previas. Si el archivo o `ffprobe` no están disponibles, la campaña queda pendiente de corrección y reintento, sin activar.

Comando equivalente del trabajador dedicado:

```bash
php artisan queue:work --queue=campaigns --sleep=1 --tries=1 --timeout=1900 --max-time=3600
```

El scheduler existente ejecuta `schedule:run` cada minuto. Con tareas cada segundo, ese comando sigue activo hasta el final del minuto; el bucle de Supervisor espera a que termine. En un despliegue autorizado, después de actualizar código/configuración, interrumpe el scheduler anterior y reinicia los trabajadores para que carguen la nueva versión:

```bash
php artisan schedule:interrupt
php artisan queue:restart
```

Consulta la [documentación oficial de tareas subminuto de Laravel 12](https://laravel.com/framework/docs/12.x/scheduling#sub-minute-scheduled-tasks). Los comandos anteriores son instrucciones de operación: no se ejecutaron contra producción durante la implementación.

## Comprobación antes de dar el despliegue por terminado

- Comprueba una campaña con dos videos y varios dispositivos: todos deben llegar a «Publicado» y reproducirse en el orden configurado, repitiéndose.
- Comprueba dos campañas que compartan un archivo: al finalizar la primera debe conservarse; al terminar la última referencia debe comenzar la limpieza.
- Desconecta un equipo durante el fin: debe aparecer pendiente, nunca «Borrado confirmado» sin evidencia.
- Comprueba una respuesta ambigua del modelo 35: debe quedar «Requiere revisión», sin repetir automáticamente el índice.
- Verifica que los archivos ajenos a la campaña y los dispositivos no seleccionados permanezcan intactos.
- Inicia con videos anteriores identificables y respaldados: verifica su retiro, la reproducción de campaña y su recarga al pausar o finalizar la última activa, sin apagar el equipo.
- Desconecta al restaurar: la recuperación debe quedar pendiente y reintentarse aunque ya no haya archivos de campaña que retirar. Verifica también el bloqueo si falta un original.

Estas comprobaciones sobre hardware son posteriores a las pruebas automatizadas con fakes; requieren un entorno y dispositivos de prueba autorizados.
