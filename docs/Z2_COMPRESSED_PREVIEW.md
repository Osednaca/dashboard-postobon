# Preview comprimido Z2

El contenido actual de Z2 puede usar una copia privada H264 MP4: dimensión máxima de 448 px, 15 fps, CRF 28, sin audio y con `faststart`. Mantiene toda la duración y el aspecto dentro del reproductor cuadrado 1:1 existente. WL35, biblioteca, campañas y envíos al hardware conservan la fuente original. No se modifica Media.duration ni el archivo original.

La primera consulta muestra el original y encola `GenerateMediaPreviewJob` en `previews`. La URL cambia a `/media/{id}/content?preview={hash}` sólo al publicar una copia válida y menor; permanece estable después. Descarga, conversión y validación ocurren en el worker, nunca durante el polling. Si falla, falta FFmpeg, la copia no reduce bytes o el formato no es elegible, sigue disponible el original. El siguiente polling puede actualizar la fuente cuando termina la preparación.

## Despliegue

Docker instala el paquete FFmpeg (incluye ffprobe) y Supervisor inicia un worker separado para `previews`, con un proceso. Configuración: `config/mediapreview.php`; se puede desactivar con `MEDIA_PREVIEW_ENABLED=false`, o indicar binarios locales con `MEDIA_PREVIEW_FFMPEG` y `MEDIA_PREVIEW_FFPROBE`.

Sólo se encola con conexión `database` o `redis`, y `retry_after` estrictamente mayor a 360 segundos. Database usa 2100 por defecto; para Redis configurar `REDIS_QUEUE_RETRY_AFTER` por encima de 360. Los drivers sync, deferred, background, null y failover conservan el original sin ejecutar conversión en HTTP. Se requiere cache compartida con locks para deduplicación entre instancias y almacenamiento privado compartido entre web/worker si están en contenedores distintos. El worker habitual continúa atendiendo la cola default.

El job tiene un intento, timeout de 360 segundos y exclusión de 600 segundos; descarga hasta 120 segundos, conversión hasta 120 y cada probe 20. Fuente y salida tienen límite de 250 MiB; el progreso de descarga detiene una fuente que exceda el límite. FFmpeg usa un límite de salida `-fs`; una salida truncada se rechaza mediante comparación de duración (tolerancia de 0.2 segundos). Los temporales se limpian al terminar; el scheduler elimina archivos propios sin uso de más de 7 días. El acceso a una copia renueva su mtime. Un worker terminado abruptamente puede dejar temporales hasta esa limpieza.

## Fuentes y seguridad

Sólo `video/mp4` proveniente del resolver local o del origen/ruta privados configurados. No se pasan URLs al proceso. FFmpeg/ffprobe reciben argumentos separados, demuxer mov, protocolos file/pipe y referencias externas mov desactivadas (`enable_drefs=0`, `use_absolute_path=0`). MP4 con pixel aspect explícito distinto de 1:1 usa el original para evitar distorsión; otros formatos también. La copia se valida como H264, dimensiones máximas de 448 px y duración completa antes del rename; nunca se publica un parcial.

La variante utiliza la autorización Media existente, headers private/no-store/nosniff y BinaryFileResponse para HEAD/Range. El hash consultado debe ser la variante vigente del medio, sin rutas suministradas por el usuario. Los errores sólo registran media_id y categorías; no se muestran tokens/URLs al usuario.

La identidad incluye medio, perfil de conversión, fuente y tamaño; en fuentes locales además tamaño/mtime real. `updated_at` de sync no invalida una copia. Cada hora se relee la fuente remota completa y se verifica SHA256: el mismo contenido reutiliza la copia; bytes diferentes generan otro hash. No se considera ETag ni Last-Modified como prueba suficiente de identidad (el proveedor puede omitirlos o reutilizarlos). Una sustitución remota con mismo nombre/tamaño puede conservar la copia anterior hasta esa hora; durante revalidación se muestra el original. Un reemplazo local del mismo tamaño y mtime requiere también la revalidación periódica. Los fallos tienen cooldown de 10 minutos. Borrar cache o perder una copia permite regenerarla.

## Verificación local

`CompressedMediaPreviewTest` usa SQLite memory, Storage/Queue/Process fakes y Http::preventStrayRequests: deduplicación, drivers inseguros, fuente reemplazada, auth/Range, variante malformada, fallos/salida mayor/truncada, original intacto, limpieza y Z2 frente a WL35.

Compresión real aislada con FFmpeg existente del repositorio vecino, sin descargas ni hardware: video sintético original de 15,445,255 bytes, 1280x720/30 fps → 325,855 bytes, 448x252/15 fps (97.89% menos). Ambos reportaron 00:28.27 y DAR 16:9/SAR 1:1. SHA256 original antes/después: `0309A8BA00B3E69B8C7A99FB4465EEEE822A411E8499F723CD6E393CCD4D0249`. Fixture ignorado: `storage/framework/testing/z2-compressed-preview/{original,compressed}.mp4`. Se verificaron conversión y decode reales con FFmpeg; no había ffprobe local, por lo que su integración se verificó mediante Process fake. Browser local reprodujo el derivado: duración 28.266667, dimensiones 448x252, readyState4, sin error de media ni consola. No se ejecutó Docker ni un worker de producción.

Referencias primarias: [FFmpeg scale](https://www.ffmpeg.org/ffmpeg-filters.html#scale-1), [libx264/CRF](https://www.ffmpeg.org/ffmpeg-codecs.html#libx264_002c-libx264rgb), [MP4 faststart](https://www.ffmpeg.org/ffmpeg-formats.html#Options-9), [mov data references](https://www.ffmpeg.org/ffmpeg-formats.html#Options-8), [ffprobe](https://ffmpeg.org/ffprobe.html), [paquete Alpine con ffprobe](https://pkgs.alpinelinux.org/contents?arch=aarch64&branch=v3.21&name=ffmpeg&repo=community), [Laravel unique jobs](https://laravel.com/docs/12.x/queues#unique-jobs).
