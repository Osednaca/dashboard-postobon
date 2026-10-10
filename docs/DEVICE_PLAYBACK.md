# Reproducción desde la biblioteca

Asignar Video Directamente en el detalle Z2 y Reproducir en `/devices` envían el `media_id` seleccionado a la misma operación que `/instant-play`: `POST /instant-play/media`. Un trabajo `PlayFleetMediaJob` conserva la identidad del medio y los targets. El formulario muestra progreso y los conteos de órdenes enviadas y fallidas; enviar una orden no demuestra cuánto reprodujo físicamente el ventilador. Requiere una cola de fondo `database` o `redis`.

La selección de biblioteca conserva el archivo completo, sin introducir límite de diez segundos ni modificar su duración guardada. Si el archivo pertenece a la biblioteca privada, se usa su filename identificado, incluidas las URLs históricas dentro de la ruta configurada. Las fuentes locales, también las subidas API antiguas del disco local, se copian completas y se distribuyen. No se descarga una URL externa libre ni se siguen redirecciones al materializar una fuente privada.

El control explícito de un índice WL35 sigue disponible. El modal de `/devices` conserva además la opción «Reproducir contenido ya almacenado», para órdenes por índice o filename sin seleccionar un medio de biblioteca. No se cruza la SD con la biblioteca para ocultar archivos propios del dispositivo.

Los consumidores antiguos de `changeVideo` también resuelven el archivo real. Si falta una fuente local o falla su subida, se aborta sin reproducir el basename de otro archivo existente. Una subida exitosa conserva el archivo y su registro local para próximos previews y reproducciones.

La subida multipart cierra siempre el handle del archivo, incluso ante rechazo: no deja un archivo local bloqueado por la petición anterior. Guardar el identificador en almacenamiento del navegador permite continuar desde reproducción instantánea; si ese almacenamiento está bloqueado, el seguimiento de la operación aceptada continúa en la página.

Si la sesión caduca durante el seguimiento, se detienen las consultas y se conserva el identificador aceptado. El enlace a reproducción instantánea permite recuperar esa operación después del login, incluso sin almacenamiento del navegador. El formulario evita enviar una orden duplicada mientras solicita esa consulta.

La comparación del código muestra que las órdenes Z2 terminan en `/api/devices/{mac}/play` con `filename`; no transmiten una duración de diez segundos. `VIDEO_BURST_MS` controla la repetición de metadata de inyección, mientras las descargas HTTP sirven archivos mediante `sendFile` con Range. Por tanto, esta corrección elimina divergencias de selección y fallos que podían reproducir un archivo anterior, pero no atribuye ni certifica la observación de 28 segundos frente a 10 segundos en hardware.

En una lectura de producción autorizada, `/media/58702` mostró `Gaseosas_girando` con fotograma en 0.1 segundos y duración de 28.281995 segundos mediante `/media/58702/content`: `readyState = 4`, `error = null` y duración visible `00:28`. `/devices/1` abrió sin error 500, con playlist vacía y sin contenido actual. La inspección no envió órdenes al ventilador; sigue pendiente comprobar la duración de su reproducción física.

Verificación local: `php artisan test tests/Feature/DevicePlaybackTest.php`, `node --test tests/js/library-playback.test.mjs`, Pint y build. Las pruebas usan HTTP simulado, almacenamiento temporal y un medio fixture con duración guardada de 28 segundos; comprueban identidad, payload y bytes completos, no decodificación física del video.
