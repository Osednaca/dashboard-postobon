# Preview del contenido reportado

El preview reproduce una copia de biblioteca del video que el ventilador reporta como actual. No captura la imagen física ni sincroniza el fotograma con el rotor.

`GET /device-previews` requiere sesión y permiso de lectura de dispositivos. Devuelve `devices[]` con `key`, `type`, `id`, `name`, `status`, `current_video`, `media_name`, `url`, `last_seen` y `detail_url`.

- Z2: resuelve el filename reportado, sin sustituirlo por el contenido deseado o el primer video de la playlist. Un nombre original ambiguo no identifica un archivo.
- WL35: resuelve el índice reportado mediante la relación local del dispositivo con un medio de biblioteca. Las cargas directas sin fuente retenida o los videos de la app oficial pueden no tener esa relación.
- `ready` incluye una URL del mismo origen. `offline`, `powered_off`, `unknown`, `unavailable`, `no_content`, `missing_mapping` y `missing_file` no incluyen fuente activa.

Los borrados y formatos desde el dashboard mantienen las relaciones WL35. Cambios externos que reordenen su SD pueden invalidarlas: el protocolo actual entrega índice y cantidad, sin identidad de archivo. En ese caso la asociación requiere volver a establecerse desde la biblioteca; el índice por sí solo no demuestra identidad tras un cambio externo.

`GET /media/{media}/content` exige permiso de lectura del medio. Sirve archivos dentro del disco público local o transmite desde la biblioteca privada configurada. Conserva los rangos HTTP para permitir búsqueda en el reproductor; los tokens permanecen en el servidor. No admite URLs libres, rutas fuera del almacenamiento ni redirecciones de la biblioteca. Si el archivo fue borrado, devuelve 404.

La consulta de estado usa lecturas de la flota y la nube privada. Si ambas fallan, conserva los dispositivos guardados con estado `unavailable`. No envía órdenes ni sincroniza registros durante el polling.

Verificación local: `php artisan test tests/Feature/DevicePreviewTest.php`. Las pruebas usan HTTP simulado y almacenamiento temporal.

El dashboard y ambos detalles usan el mismo componente. Cada página consulta el estado una vez cada diez segundos; actualiza la fuente solo si cambia el archivo. Al quedar sin estado confirmado, elimina la fuente anterior. El reproductor arranca sin audio, repite el video y permite controlarlo; un error de archivo muestra una opción de reintento. La pestaña oculta pausa reproducción y consultas, y al volver solicita un estado nuevo. Las copias no están sincronizadas fotograma a fotograma con el ventilador.

Verificación de reproducción y polling: `node --test tests/js/device-previews.test.mjs`. Build del navegador: `npm run build`.
