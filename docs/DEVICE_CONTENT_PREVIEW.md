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

La biblioteca sólo reconcilia borrados después de un inventario exitoso y completo (`result: 0`, lista `media` con filename y tamaño válidos). Un inventario vacío válido elimina registros privados ausentes; errores o filas incompletas conservan los registros. Se reconocen filenames privados y URLs antiguas que pertenecen exactamente a la biblioteca configurada; fuentes locales y URLs externas se conservan. Las URLs privadas reconocidas se normalizan al filename sin cambiar el ID o la metadata local. Una sincronización no restaura medios eliminados; una nueva subida explícita del mismo filename sí los restaura.

La nube actual no entrega duración ni miniaturas. Sincronizar conserva esos datos, junto con los nombres editados. Los borrados de biblioteca sólo eliminan el registro después de que la nube confirme el éxito o el archivo local se elimine correctamente. Las nuevas subidas locales usan `public`; se contempla el disco local de subidas API antiguas, sin usar un disco predeterminado remoto. Limpiar una miniatura es secundario: su fallo queda registrado y no conserva un registro cuyo video ya se eliminó. Los lotes web informan cuántos borrados fallaron y la API responde 207 con `deleted_count` y `failed_ids` cuando hay fallos parciales.

Los registros locales sin archivo y las URLs externas no se purgan a partir de una lista privada. Los cambios en hardware y en datos reales requieren comprobación posterior; esta reconciliación se verificó con fixtures locales, sin contactar nube ni dispositivos.
