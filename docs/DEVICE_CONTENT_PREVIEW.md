# Preview del contenido reportado

El preview reproduce una copia de biblioteca del video que el ventilador reporta como actual. No captura la imagen física ni sincroniza el fotograma con el rotor.

`GET /device-previews` requiere sesión y permiso de lectura de dispositivos. Devuelve `devices[]` con `key`, `type`, `id`, `name`, `status`, `current_video`, `media_name`, `url`, `last_seen` y `detail_url`.

- Z2: resuelve el filename reportado, sin sustituirlo por el contenido deseado o el primer video de la playlist. Un nombre original ambiguo no identifica un archivo.
- WL35: resuelve el índice reportado mediante la relación local del dispositivo con un medio de biblioteca. Las cargas directas sin fuente retenida o los videos de la app oficial pueden no tener esa relación.
- `ready` incluye una URL del mismo origen. `offline`, `powered_off`, `unknown`, `unavailable`, `no_content`, `missing_mapping` y `missing_file` no incluyen fuente activa.

Los borrados y formatos desde el dashboard mantienen las relaciones WL35. Cambios externos que reordenen su SD pueden invalidarlas: el protocolo actual entrega índice y cantidad, sin identidad de archivo. En ese caso la asociación requiere volver a establecerse desde la biblioteca; el índice por sí solo no demuestra identidad tras un cambio externo.

`GET /media/{media}/content` exige permiso de lectura del medio. Sirve archivos dentro del disco público local, contempla las subidas históricas del disco local, o transmite desde la biblioteca privada configurada. Conserva los rangos HTTP para permitir búsqueda en el reproductor; los tokens permanecen en el servidor. No admite URLs libres, rutas fuera del almacenamiento ni redirecciones de la biblioteca. Si el archivo fue borrado, devuelve 404.

La consulta de estado usa lecturas de la flota y la nube privada. Si ambas fallan, conserva los dispositivos guardados con estado `unavailable`. No envía órdenes ni sincroniza registros durante el polling.

Verificación local: `php artisan test tests/Feature/DevicePreviewTest.php`. Las pruebas usan HTTP simulado y almacenamiento temporal.

Los detalles Z2 y WL35 usan el mismo componente. Cada detalle consulta el estado una vez cada diez segundos; actualiza la fuente solo si cambia el archivo. Al quedar sin estado confirmado, elimina la fuente anterior. El reproductor arranca sin audio, repite el video y permite controlarlo; un error de archivo muestra una opción de reintento. La pestaña oculta pausa reproducción y consultas, y al volver solicita un estado nuevo. Las copias no están sincronizadas fotograma a fotograma con el ventilador. El dashboard no muestra previews ni activa sus consultas periódicas.

En el detalle de campaña, la pestaña Videos muestra el contenido configurado con el mismo preview y duración de la biblioteca, mediante URLs autenticadas del panel. Incluye controles y carga las fuentes al hacerse visibles. Esa vista no afirma que el contenido esté reproduciéndose en un dispositivo; para consultar el contenido reportado se usa el detalle del equipo.

Verificación de reproducción y polling: `node --test tests/js/device-previews.test.mjs`. Build del navegador: `npm run build`.

La biblioteca sólo reconcilia borrados después de un inventario exitoso y completo (`result: 0`, lista `media` con filename y tamaño válidos). Un inventario vacío válido elimina registros privados ausentes; errores o filas incompletas conservan los registros. Se reconocen filenames privados y URLs antiguas que pertenecen exactamente a la biblioteca configurada; fuentes locales y URLs externas se conservan. Las URLs privadas reconocidas se normalizan al filename sin cambiar el ID o la metadata local. Una sincronización no restaura medios eliminados; una nueva subida explícita del mismo filename sí los restaura.

La nube actual no entrega duración ni miniaturas. Sincronizar conserva esos datos, junto con los nombres editados. Los borrados de biblioteca sólo eliminan el registro después de que la nube confirme el éxito o el archivo local se elimine correctamente. Las nuevas subidas locales usan `public`; se contempla el disco local de subidas API antiguas, sin usar un disco predeterminado remoto. Limpiar una miniatura es secundario: su fallo queda registrado y no conserva un registro cuyo video ya se eliminó. Los lotes web informan cuántos borrados fallaron y la API responde 207 con `deleted_count` y `failed_ids` cuando hay fallos parciales.

Los registros locales sin archivo y las URLs externas no se purgan a partir de una lista privada. Los cambios en hardware y en datos reales requieren comprobación posterior; esta reconciliación se verificó con fixtures locales, sin contactar nube ni dispositivos.

En el detalle Z2, quitar un video o formatear SD significa enviar una solicitud aceptada; el mensaje no afirma que el hardware ya completó el borrado. La lista separa los videos solicitados durante diez minutos para evitar que una playlist intermedia los vuelva a mostrar. Esa supresión se conserva hasta el vencimiento aunque una lectura intermedia sea vacía. Un formateo sólo separa los filenames reportados antes de enviar la solicitud: no oculta archivos nuevos ni cruza la lista con la biblioteca, porque la SD puede tener videos propios.

Al vencer la espera, una lista válida sin el archivo permite retirar el recordatorio, sin anunciar confirmación física. Si aún aparece o no se pudo leer la playlist, se informa que el cambio no está confirmado y se muestra lo reportado. Los avisos vencidos se conservan como máximo un día desde la solicitud; consultar no extiende ese plazo. La caché puede perder ese seguimiento si se vacía. La API privada conserva una playlist que también modifica optimistamente: `lastSeen` y `heartbeatCount` no indican que un heartbeat concreto incluía `PlayList`. Por eso ningún avance de esos campos se presenta como prueba del borrado del hardware; confirmar eso necesitaría metadata de recepción por campo que la API actual no entrega.

En `/media`, las vistas de tarjetas y lista muestran un fotograma pausado del video o la imagen disponible. El detalle usa el mismo preview y añade controles para reproducir el video. La fuente se carga al entrar en el área visible; el navegador lee la duración y busca un fotograma a los 0,1 segundos, o antes si el archivo es más corto. Los estados de carga y error son visibles y permiten reintentar. No se genera ni guarda una miniatura y no se necesita FFmpeg en ejecución.

La duración conocida se conserva. Si falta, `loadedmetadata` permite mostrarla en esa página, compartida entre tarjetas y lista, sin escribirla en la base de datos. Se representan también las horas completas y un error de reproducción no elimina una duración conocida. Un video con codec incompatible con el navegador puede producir error aunque su archivo exista.

Las imágenes locales admitidas son JPEG, PNG, GIF, WebP, AVIF y BMP: se comprueba el MIME real y la estructura de imagen antes de servirlas, con `nosniff`. SVG y archivos HTML disfrazados no se sirven como imágenes. Las URLs privadas históricas se reconocen sólo dentro del origen y la ruta configurados, por lo que el detalle puede reproducirlas sin visitar primero la biblioteca. Las fuentes externas no se convierten en un proxy. El preview del dispositivo continúa resolviendo únicamente videos.

Verificación de biblioteca: `php artisan test tests/Feature/MediaLibraryPreviewTest.php` y `node --test tests/js/media-preview.test.mjs`. Los fixtures visuales locales incluyen video, imagen, lista, detalle y error; no comprueban el registro real 56873 ni datos de equipos remotos.
