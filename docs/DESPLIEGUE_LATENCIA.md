# Despliegue de latencia y progreso de /devices — 9 de septiembre de 2026

Esta entrega modifica tres repositorios: `dashboard-fan-wl35`,
`dashboard-postobon/private-cloud` y `dashboard-postobon`, además del firmware ESP32.
Actualizar solamente el primer dashboard no actualiza la página `/devices` de Postobón.
Los cambios están preparados localmente; no se han publicado ni cargado al ESP32.

La referencia observada en producción fue: 8.923.180 bytes originales,
5.675.892 bytes convertidos, 4.873 ms de conversión y 210.393 ms de transmisión.
Las pruebas locales verifican el protocolo y los errores, no el tiempo físico del equipo.

## 1. Guardar y publicar los tres repositorios desde Windows

Ejecutar en PowerShell. Revisar `git diff` antes de cada commit. No agregar `.env`.

```powershell
Set-Location 'E:\programacion\dashboard-postobon\private-cloud'
git add .env.example README.md src/api/routes.js src/commands.js src/config.js src/store/deviceStore.js src/tcp/server.js tools/unit-push.js tools/smoke-test.js
git commit -m "Deliver native Z2 notifications first and replace stale power commands"
git push origin master

Set-Location 'E:\programacion\dashboard-fan-wl35'
git add .env.example README.md esp32/fan_agent_ws/fan_agent_ws.ino server/index.js server/wl35-relay.js server/wl35-relay.test.mjs server/wl35-upload.test.mjs
git commit -m "Negotiate bulk WL35 relay and report fleet upload progress"
git push origin main

Set-Location 'E:\programacion\dashboard-postobon'
git add app/Http/Controllers/Web/UnifiedFleetController.php app/Jobs/DistributeFleetVideoJob.php app/Services/Fleet/UnifiedFleetClient.php resources/views/devices/index.blade.php tests/Feature/FleetUploadProgressTest.php docs/DESPLIEGUE_LATENCIA.md private-cloud
git commit -m "Show conversion and confirmed transfer progress in devices"
git push origin main
```

`private-cloud` tiene repositorio propio y el proyecto padre guarda una referencia
a su commit. Publicar primero el repositorio interno. En esta copia no existe
`.gitmodules`; actualizar la copia de producción de `private-cloud` por separado.

## 2. Actualizar private-cloud en el VPS

Entrar por SSH y ubicarse en la carpeta real de `private-cloud` de producción.
Esperar a que terminen las cargas activas antes de reiniciar servicios.

```bash
git pull --ff-only origin master
```

Cambiar estas claves en su `.env` existente, conservando los demás valores:

```dotenv
DEVICE_PUSH_NOTIFY=true
DEVICE_SEND_TIME=1
```

Reconstruir el servicio Docker definido en este repositorio:

```bash
docker compose up -d --build fan-cloud
curl -s http://127.0.0.1:8081/api/status
```

La respuesta debe contener `commandTransport: "native-first-v2"`,
`devicePushNotify: true` y `deviceSendTime: 1`. Si continúa apareciendo `60`,
el contenedor conserva configuración anterior. No se instala firmware en el Z2
para esta entrega: sus cambios están en `private-cloud`.

## 3. Actualizar el gateway WL35 en el VPS

Ubicarse en la carpeta real de `dashboard-fan-wl35`:

```bash
git pull --ff-only origin main
```

Actualizar estas claves en su `.env`:

```dotenv
UPLOAD_CHUNK_BYTES=14336
UPLOAD_IN_FLIGHT_CHUNKS=16
UPLOAD_PENDING_WINDOWS=4
```

Para la instalación PM2 documentada por el proyecto:

```bash
UPLOAD_CHUNK_BYTES=14336 UPLOAD_IN_FLIGHT_CHUNKS=16 UPLOAD_PENDING_WINDOWS=4 pm2 restart dashboard-fan-wl35 --update-env
curl -s http://127.0.0.1:4173/api/health
```

Debe aparecer la versión `2026-09-09-wl35-bulk-progress-v21`.
Si este proceso está administrado por otro servicio, reiniciar ese servicio
con las mismas variables. Esta entrega no cambia dependencias ni el bundle React.

## 4. Cargar el firmware ESP32 v7

1. Abrir `E:\programacion\dashboard-fan-wl35\esp32\fan_agent_ws\fan_agent_ws.ino`
   en Arduino IDE.
2. Conservar la configuración de servidor, token e identificador de ese ESP32.
3. Seleccionar la misma placa y puerto que se usaron para v6. Compilar y subir.
   No borrar la memoria de configuración Wi‑Fi.
4. Esperar su reconexión. Consultar:

```bash
curl -s http://127.0.0.1:4173/api/devices/holoscope-wl35/status
```

Debe mostrar `firmware_version: "2026-09-09-upload-bulk-relay-v7"`,
`upload_max_chunk_bytes: 14336` y `session_ready: true`.
Si sigue en v6, el gateway conserva los bloques pequeños por compatibilidad.
La compilación local se verificó con ESP32 core 3.3.11 y WebSockets 2.7.2.

## 5. Actualizar Laravel Postobón

Ubicarse en la carpeta real de `dashboard-postobon` en el VPS:

```bash
git pull --ff-only origin main
php artisan optimize:clear
php artisan optimize
php artisan queue:restart
```

Ejecutar estos comandos dentro del contenedor de Laravel si PHP corre en Docker.
Su supervisor debe volver a iniciar el worker. Mantener `QUEUE_CONNECTION=database`
y `DB_QUEUE_RETRY_AFTER=2100` según la configuración existente del proyecto.
Esta entrega no agrega migraciones ni dependencias. El cambio visual está en Blade;
no requiere reconstruir los assets para mostrar el progreso.

## 6. Comprobar desde /devices

1. Recargar `/devices` en dashboard-postobon.
2. Con el WL35 reproduciendo, seleccionar únicamente ese equipo y subir el mismo
   MP4 usado para la referencia. Durante “Convirtiendo el video” debe seguir funcionando.
3. Durante “Transmitiendo al ventilador”, comprobar que avancen los MB y la velocidad.
   El 100 % requiere que el trabajo termine después de la confirmación del archivo.
4. Revisar `last_upload.relay_chunk_bytes`: debe ser `14336`. Comparar
   `last_upload.timings_ms.relay` con los `210393` ms anteriores.
5. Si sigue lento, guardar el JSON de estado. `relay_diagnostics.fan_write_ms`
   mide la escritura desde ESP32 al WL35; `elapsed_ms` incluye las esperas entre
   bloques. También quedan `max_write_ms`, `wifi_rssi` y `free_heap` para localizar
   el siguiente cuello de botella. Medir aparte cuándo vuelve a reproducir tras el reinicio.
6. En Z2, probar apagar y encender consecutivamente, Bluetooth, volumen y selección
   de un video existente. Medir la respuesta física. No hace falta formatear la SD.
7. Para una orden Z2 que aún tarde, guardar los logs de ese intervalo:

```bash
docker compose logs --since=5m fan-cloud
```

Comprobar cuánto transcurre entre `notificación nativa TCP enviada` y
`heartbeat recibido`, y si este último entrega el estado solicitado. La frecuencia
anunciada de un segundo debe comprobarse en los tiempos reales; no todos los firmwares
necesariamente la respetan. Comparar la app nativa usando nube/datos móviles para
evitar confundir su control local por Wi‑Fi con el canal remoto.

## Comparación y reversión de parámetros

Para comparar bloques anteriores sin cambiar el código, restaurar
`UPLOAD_CHUNK_BYTES=1400` y reiniciar el gateway. Para volver al envío directo Z2
sin el aviso inicial, usar `DEVICE_PUSH_NOTIFY=false` y reconstruir el contenedor.
Mantener los commits anteriores como referencia si fuera necesaria una reversión
completa. No borrar la SD para probar latencia.

La meta de 10 segundos para WL35 y la ejecución inmediata de todos los comandos Z2
quedan pendientes de esta medición física; no se presentan como resultados logrados.
