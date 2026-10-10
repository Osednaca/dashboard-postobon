# Desplegar gateway v23 y Z2 native-settings-v3 — 9 de septiembre de 2026

Esta corrección cambia el gateway WL35 y private-cloud. El ESP32 v7 ya desplegado
es compatible: **no hay que volver a flashearlo**. No hay migraciones ni nuevas
dependencias. Los cambios de Blade para mostrar errores de carga siguen pendientes
en el workspace de Laravel y se incluyen abajo.

La consulta de producción previa mostró gateway v22, ESP32 v7 y Z2 native-first-v2
con push activo e intervalo anunciado de un segundo. Esas versiones sí estaban
desplegadas. El nuevo cambio corrige el transporte; requiere una nueva publicación.

## 1. Guardar y publicar desde Windows

Revisar cada diff antes del commit. No agregar archivos .env ni tokens.

```powershell
Set-Location 'E:\programacion\dashboard-postobon\private-cloud'
git add README.md src/api/routes.js src/http/deviceServer.js src/tcp/server.js src/commandInjection.js tools/unit-push.js
git commit -m "Send complete desired Z2 settings over TCP"
git push origin master

Set-Location 'E:\programacion\dashboard-fan-wl35'
git add .env.example README.md PERFORMANCE.md server/index.js server/wl35-upload.test.mjs
git commit -m "Restore short WL35 relay messages with bounded pipelining"
git push origin main

Set-Location 'E:\programacion\dashboard-postobon'
git add private-cloud docs/DESPLIEGUE_LATENCIA.md resources/views/devices/index.blade.php tests/Feature/FleetUploadProgressTest.php
git commit -m "Document latency deployment and display per-device upload failures"
git push origin main
```

**Incluir el archivo nuevo `private-cloud/src/commandInjection.js`.** Los módulos
HTTP y TCP lo requieren. Private-cloud tiene Git propio; publicar el repositorio
padre no publica su código. Como esta copia no tiene .gitmodules, actualizar
private-cloud por separado también en producción.

## 2. Actualizar private-cloud en el VPS

Dentro de la carpeta real de private-cloud:

```bash
git pull --ff-only origin master
```

Conservar estas variables en su .env:

```dotenv
DEVICE_PUSH_NOTIFY=true
DEVICE_SEND_TIME=1
```

Reconstruir el contenedor para que copie los archivos nuevos:

```bash
docker compose up -d --build fan-cloud
curl -s http://127.0.0.1:8081/api/status
```

Verificar `commandTransport: "native-settings-v3"`, `tcpSettingsPush: true`,
`devicePushNotify: true` y `deviceSendTime: 1`.
No se instala firmware en el Z2.

## 3. Actualizar el gateway WL35

Dentro de la carpeta real de dashboard-fan-wl35:

```bash
git pull --ff-only origin main
```

En su .env:

```dotenv
UPLOAD_CHUNK_BYTES=1400
UPLOAD_IN_FLIGHT_CHUNKS=16
UPLOAD_PENDING_WINDOWS=4
```

Reiniciar al terminar las cargas activas:

```bash
UPLOAD_CHUNK_BYTES=1400 UPLOAD_IN_FLIGHT_CHUNKS=16 UPLOAD_PENDING_WINDOWS=4 pm2 restart dashboard-fan-wl35 --update-env
curl -s http://127.0.0.1:4173/api/health
```

Debe mostrar `2026-09-09-wl35-short-pipeline-v23`. No hace falta npm run build
para este cambio de servidor. Si PM2 usa otro nombre, aplicar el reinicio a ese
proceso. El gateway limita valores antiguos de bloque mayores que 1400.

## 4. Actualizar los mensajes de error en Laravel

Dentro de la carpeta de dashboard-postobon:

```bash
git pull --ff-only origin main
php artisan optimize:clear
php artisan optimize
php artisan queue:restart
```

El supervisor existente debe volver a iniciar el worker. No cambiar APP_KEY,
credenciales ni la configuración de colas. El cambio visual es Blade; no requiere
recompilar assets.

## 5. Medir desde /devices

1. Recargar /devices y subir el mismo MP4 únicamente al WL35, sin formatear.
   La conversión debe ocurrir mientras sigue reproduciendo.
2. Consultar:

```bash
curl -s http://127.0.0.1:4173/api/devices/holoscope-wl35/status
```

La nueva carga debe terminar `complete`, con `relay_chunk_bytes: 1400`,
`relay_window: 16`, `relay_pending_windows: 4`. El ESP32 puede seguir anunciando
`upload_max_chunk_bytes: 14336`: es su capacidad máxima, no el bloque seleccionado.
Comparar `timings_ms.relay` con los 205509 ms de la última carga de referencia.
La conversión de aquella carga duró 4520 ms y el archivo convertido pesó 5586028 bytes.

3. En Z2 probar OFF, ON, Bluetooth, volumen y reproducción. Medir la acción física,
   no solo el spinner ni `delivered: tcp`. Revisar:

```bash
docker compose logs --since=5m fan-cloud
```

Aparecerán `notificación nativa TCP enviada` y, si sigue pendiente, después de
unos 250 ms `ajustes completos enviados por TCP sin esperar heartbeat`. Para
encendido este último debe incluir `POWER`. El heartbeat posterior confirma
si el equipo ejecutó la orden. Si solo actúa al recibir ese heartbeat, hace falta
capturar el protocolo del firmware; no se debe declarar ejecución instantánea
solo por haber escrito al socket.

Las pruebas locales verifican entrega de campos, integridad y confirmaciones.
Los 30 segundos del WL35 y la ejecución física inmediata del Z2 siguen pendientes
de la medición con estas versiones. No formatear ni borrar videos para probar latencia.
