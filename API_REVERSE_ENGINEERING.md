# 3D LED FAN Z2 — Documentación Técnica de API

> Ingeniería inversa del protocolo HTTP — `holographicdisplay.cn:8088`  
> Fecha: Junio 2026 | Dispositivo: Z2PRUEBA | MAC: `9097D5E4EC13`

---

## 1. Arquitectura del Sistema

El fan LED Z2 se comunica con el servidor a través de **HTTP plano en el puerto 8088**. No hay TLS — todo el tráfico es texto claro y reproducible.

```
App (móvil/escritorio)  →  HTTP POST  →  www.holographicdisplay.cn:8088  →  Fan Z2
```

**Servidor:** AWS us-west-2 Oregon · IP: `35.162.199.143` · Tomcat 8.5.88

**Excepción — Power ON/OFF por red local:**
```
Dashboard  →  TCP directo  →  Fan Z2
Comando encender: Power=1
Comando apagar:   Power=0
```
> También disponible vía nube en `/User/devicePower` (solo app móvil, no escritorio).

---

## 2. Autenticación

Sesiones por cookie `JSESSIONID`. La contraseña se encripta con RSA antes de enviarse. Requiere 2 llamadas.

---

### `GET /admin/AdminLoginR`

Obtiene la llave pública RSA. Genera el `JSESSIONID` inicial vía `Set-Cookie`.

**No requiere parámetros ni autenticación.**

**Respuesta:**
```json
{
  "pubexponent": "10001",
  "pubmodules": "bd2cd917...",
  "pubmodules_base64": "MIGfMA0GCSqGSIb3..."
}
```

| Campo | Descripción |
|---|---|
| `pubexponent` | Exponente público RSA en hex |
| `pubmodules` | Módulo RSA en hex |
| `pubmodules_base64` | Llave pública PEM base64 — **usar este campo para encriptar** |

> ⚠️ La cookie `JSESSIONID` se establece aquí. Guardarla para todas las llamadas siguientes.

**Implementación en Node.js:**
```javascript
const forge = require('node-forge');

async function login(userName, plainPassword) {
  // Paso 1: obtener llave pública + JSESSIONID
  const keyRes = await fetch('http://www.holographicdisplay.cn:8088/admin/AdminLoginR');
  const cookies = keyRes.headers.get('set-cookie'); // guardar JSESSIONID
  const { pubmodules_base64 } = await keyRes.json();

  // Paso 2: encriptar contraseña
  const pubKey = forge.pki.publicKeyFromPem(
    `-----BEGIN PUBLIC KEY-----\n${pubmodules_base64}\n-----END PUBLIC KEY-----`
  );
  const encrypted = pubKey.encrypt(plainPassword, 'RSAES-PKCS1-V1_5');
  const password = forge.util.bytesToHex(encrypted);

  // Paso 3: login
  const res = await fetch('http://www.holographicdisplay.cn:8088/User/loginR', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
      'Cookie': cookies
    },
    body: `userName=${userName}&password=${password}&loginType=1`
  });
  return res.json();
}
```

---

### `POST /User/loginR`

Autentica al usuario con la contraseña encriptada RSA.

**Body** `application/x-www-form-urlencoded`:

| Campo | Tipo | Requerido | Descripción |
|---|---|---|---|
| `userName` | string | ✅ | Nombre de usuario |
| `password` | string | ✅ | Contraseña encriptada con RSA en hex |
| `loginType` | integer | ✅ | Siempre enviar `1` |

**Respuesta:**
```json
{
  "result": 0,
  "role": 1,
  "adVertisers": {
    "idAdvertiser": "60973",
    "name": "Oscarleon210",
    "email": "finesxpress@gmail.com",
    "userStorageSpaceSizeMAX": "1"
  }
}
```

| Campo | Descripción |
|---|---|
| `result` | `0` = éxito |
| `adVertisers.idAdvertiser` | ID del anunciante — **usar en uploads** (`advertisersCode`) |
| `adVertisers.userStorageSpaceSizeMAX` | Espacio máximo en GB |

---

## 3. Gestión de Dispositivos

> Todas las llamadas requieren `Cookie: JSESSIONID={valor}`

---

### `POST /User/groupDeviceList`

Lista dispositivos con su estado completo en tiempo real.

**Body:**

| Campo | Tipo | Requerido | Descripción |
|---|---|---|---|
| `userName` | string | ✅ | Nombre de usuario |
| `iDisplayStart` | integer | ✅ | Offset de paginación (iniciar en `0`) |
| `iDisplayLength` | integer | ✅ | Máx resultados (ej: `50`) |
| `deviceCode` | string | ❌ | MAC para filtrar un device específico. Vacío = todos |
| `groupID` | integer | ❌ | ID de grupo. `0` = todos |

**Respuesta — campos clave de `aaData[]`:**

| Campo | Tipo | Descripción |
|---|---|---|
| `macIpAddress` | string | MAC address — ID único del fan |
| `deviceName` | string | Nombre asignado al dispositivo |
| `devicePower` | integer | `0` = apagado · `1` = encendido |
| `runStatus` | integer | `10` = activo/normal |
| `playList` | string | Archivos en SD separados por coma |
| `currentShowImageIndex` | integer | Índice del video reproduciéndose |
| `displayImageId` | string | `uiCode` del video activo. `"null"` si ninguno |
| `progress` | string | `"100"` = descarga completada |
| `speed` | string | RPM de rotación |
| `workTime` | integer | Minutos de uso total |
| `lastHeartDate` | string | Último heartbeat del dispositivo |
| `idDevice` | integer | ID interno en la BD |
| `advertisersCode` | integer | ID del anunciante propietario |
| `needFormatSd` | integer | `1` = SD necesita formatearse |
| `hardVersion` | integer | Versión de hardware (ej: `2020`) |
| `sysVersion` | integer | Versión de firmware (ej: `120`) |

---

### `POST /User/devicePower`

Enciende o apaga el fan vía nube.

**Body:**

| Campo | Tipo | Requerido | Descripción |
|---|---|---|---|
| `userName` | string | ✅ | Nombre de usuario |
| `deviceId` | string | ✅ | MAC del fan (ej: `9097D5E4EC13`) |
| `devicePower` | integer | ✅ | `1` = encender · `0` = apagar |

**Respuesta:**
```json
{
  "result": 0,
  "DeviceResult": { "9097D5E4EC13": 82 }
}
```

> ⚠️ Solo disponible en app móvil modo nube. La app de escritorio solo lo expone en modo WiFi local.

---

### `POST /User/unbindDevice`

Desvincula uno o más fans de la cuenta.

**Body:**

| Campo | Tipo | Requerido | Descripción |
|---|---|---|---|
| `userName` | string | ✅ | Nombre de usuario |
| `deviceId` | string | ✅ | MAC del fan. Múltiples separados por coma: `MAC1,MAC2` |

**Respuesta:**
```json
{ "result": 1 }
```

> ⚠️ Este endpoint devuelve `result: 1` (a diferencia de los demás que usan `0`). Puede indicar éxito con convención diferente, o error si el device ya estaba desvinculado. La app envía una coma residual al final del `deviceId` — evitar en implementación propia.

---

### `POST /User/needFormatSd`

Formatea la tarjeta SD del fan, eliminando **todos los videos almacenados**. El dispositivo **permanece vinculado** a la cuenta — no requiere re-binding físico.

**Body:**

| Campo | Tipo | Requerido | Descripción |
|---|---|---|---|
| `userName` | string | ✅ | Nombre de usuario |
| `deviceId` | string | ✅ | MAC del fan (ej: `9097D5E4EC13`) |
| `needFormatSd` | integer | ✅ | `1` = formatear SD |

**Respuesta:**
```json
{
  "result": 0,
  "DeviceResult": { "9097D5E4EC13": 66 }
}
```

> ⚠️ **Importante:** Después de formatear, se deben re-asignar los videos deseados usando `upgradeDeviceUi`. El dispositivo los descargará nuevamente del servidor.
> 
> **Caso de uso principal:** Eliminar un video individual de la playlist del dispositivo. No existe endpoint para eliminar un solo video — la estrategia es: formatear SD → re-asignar solo los videos deseados.

---

## 4. Gestión de Videos

---

### `POST /Effect/getUiListIsVersion`

Lista todos los videos subidos a la cuenta.

**Body:**

| Campo | Tipo | Requerido | Descripción |
|---|---|---|---|
| `userName` | string | ✅ | Nombre de usuario |
| `isVersion` | string | ✅ | Enviar `""` vacío para obtener todos |
| `effGroupID` | integer | ✅ | `0` = todos los grupos (**requerido**, da HTTP 400 si falta) |
| `order` | integer | ✅ | `0` = orden predeterminado |
| `iDisplayStart` | integer | ✅ | Offset de paginación |
| `iDisplayLength` | integer | ✅ | Máx resultados |

**Respuesta — campos de `aaData[]`:**

| Campo | Descripción |
|---|---|
| `uiCode` | ID único del video — **usar en `upgradeDeviceUi`** |
| `resourcesName` | Nombre del archivo (ej: `pie.mp4`) |
| `binUrl` | URL directa al MP4 |
| `imgsUrl[]` | URL del thumbnail JPG (auto-generado) |
| `videoSize` | Tamaño en KB |
| `videoTime` | Duración en milisegundos |
| `uplodDate` | Fecha y hora de subida |

**URL patrón de archivos:**
```
http://www.holographicdisplay.cn:8088/ui/{advertisersCode}/{uiCode}/{nombre}.mp4
http://www.holographicdisplay.cn:8088/ui/{advertisersCode}/{uiCode}/{nombre}.jpg  ← thumbnail
```

---

### `POST /User/upgradeDeviceUi`

Ordena al fan descargar y reproducir un video. El fan baja el MP4 del servidor a su SD card.

**Body:**

| Campo | Tipo | Requerido | Descripción |
|---|---|---|---|
| `userName` | string | ✅ | Nombre de usuario |
| `deviceId` | string | ✅ | MAC del fan |
| `displayImageId` | string | ✅ | `uiCode` del video a reproducir |

**Respuesta:**
```json
{
  "result": 0,
  "DeviceResult": { "9097D5E4EC13": 66 }
}
```

> El campo `progress` en `groupDeviceList` refleja el porcentaje de descarga (0–100). El fan reproduce cuando llega a `100`.

---

## 5. Flujo de Subida de Videos

La subida requiere **3 pasos en secuencia**. El archivo va por FTP al servidor, el fan lo descarga después.

```
[Tu app]  →  Paso 1: pedir slot  →  [Servidor]
[Tu app]  →  Paso 2: subir FTP  →  [Servidor FTP]
[Tu app]  →  Paso 3: confirmar  →  [Servidor]
[Fan]     ←  upgradeDeviceUi   ←  [Tu app]
```

---

### Paso 1 — `POST /User/uploadMediaFile`

Solicita credenciales FTP y un `uiCode` único.

**Body:**

| Campo | Tipo | Requerido | Descripción |
|---|---|---|---|
| `userName` | string | ✅ | Nombre de usuario |

**Respuesta:**
```json
{
  "result": 0,
  "uiCode": "20260610101827604626",
  "ftpUrl": "www.holographicdisplay.cn",
  "ftpUploadUrl": "/60973/20260610101827604626/",
  "ftpUsername": "wiikkui",
  "ftpPassword": "wiikkui",
  "TcpServerIp": "35.162.199.143",
  "TcpPort": 19999,
  "binUrl": "ui/60973/20260610101827604626/"
}
```

> Guardar `uiCode` — se necesita en los pasos 2 y 3.

---

### Paso 2 — Subida FTP

```javascript
const ftp = require('basic-ftp');

async function uploadFile(ftpData, localFilePath, fileName) {
  const client = new ftp.Client();
  await client.access({
    host: ftpData.ftpUrl,
    user: ftpData.ftpUsername,
    password: ftpData.ftpPassword,
  });
  await client.ensureDir(ftpData.ftpUploadUrl);
  await client.uploadFrom(localFilePath, ftpData.ftpUploadUrl + fileName);
  client.close();
}
```

---

### Paso 3 — `POST /User/uploadMediaSuccessIsVersion`

Confirma la subida y registra el video en la cuenta.

**Body:**

| Campo | Tipo | Requerido | Descripción |
|---|---|---|---|
| `userName` | string | ✅ | Nombre de usuario |
| `uiCode` | string | ✅ | El `uiCode` del Paso 1 |
| `fileName` | string | ✅ | Nombre del archivo (ej: `video.mp4`) |
| `resourcesName` | string | ✅ | Nombre a mostrar — puede ser igual a `fileName` |
| `isVersion` | integer | ✅ | `0` = video estándar |
| `mediaGroup` | integer | ✅ | `0` = sin grupo |

**Respuesta:**
```json
{ "result": 0 }
```

---

## 6. Gestión de Grupos

---

### `POST /User/addGroup`

Crea un grupo de dispositivos.

**Body:**

| Campo | Tipo | Requerido | Descripción |
|---|---|---|---|
| `userName` | string | ✅ | Nombre de usuario |
| `groupName` | string | ✅ | Nombre del grupo |

**Respuesta:**
```json
{ "result": 0, "idGroup": 11677 }
```

---

### `POST /User/updateDeviceGroup`

Mueve un fan a un grupo.

**Body:**

| Campo | Tipo | Requerido | Descripción |
|---|---|---|---|
| `userName` | string | ✅ | Nombre de usuario |
| `deviceId` | string | ✅ | MAC del fan |
| `groupId` | integer | ✅ | ID del grupo destino |

**Respuesta:**
```json
{ "result": 0, "DeviceResult": {}, "advertisersCode": 60973 }
```

---

## 7. Resumen de Endpoints

| Método | Endpoint | Función | Auth |
|---|---|---|---|
| `GET` | `/admin/AdminLoginR` | Obtener llave RSA pública | No |
| `POST` | `/User/loginR` | Login → JSESSIONID | No |
| `POST` | `/User/groupDeviceList` | Estado completo del fan | Cookie |
| `POST` | `/User/devicePower` | Encender / Apagar | Cookie |
| `POST` | `/User/unbindDevice` | Desvincular fan | Cookie |
| `POST` | `/User/needFormatSd` | Formatear SD (borrar videos) | Cookie |
| `POST` | `/Effect/getUiListIsVersion` | Listar videos | Cookie |
| `POST` | `/User/upgradeDeviceUi` | Cambiar video activo | Cookie |
| `POST` | `/User/uploadMediaFile` | Obtener slot FTP | Cookie |
| `POST` | `/User/uploadMediaSuccessIsVersion` | Confirmar subida | Cookie |
| `POST` | `/User/addGroup` | Crear grupo | Cookie |
| `POST` | `/User/updateDeviceGroup` | Mover fan a grupo | Cookie |

---

## 8. Notas Técnicas

### Servidor
- **Host:** `www.holographicdisplay.cn` · **Puerto:** `8088` · **Protocolo:** HTTP plano (sin TLS)
- **IP directa:** `35.162.199.143` (AWS us-west-2, Oregon)
- **App server:** Apache Tomcat 8.5.88
- **User-Agent app Android:** `okhttp-okgo/jeasonlzy`

### Sesión
- Todas las llamadas autenticadas requieren: `Cookie: JSESSIONID={valor}`
- El `JSESSIONID` se obtiene en `GET /admin/AdminLoginR` vía `Set-Cookie`
- No hay endpoint de logout documentado — sesión expira por timeout

### Códigos de respuesta `DeviceResult`
- `66` — estado tras recibir orden de cambio de video (`upgradeDeviceUi`)
- `82` — estado tras comando de power (`devicePower`)
- Significado exacto de los valores no está documentado oficialmente

### Dispositivo de prueba
| Campo | Valor |
|---|---|
| Nombre | Z2PRUEBA |
| MAC / deviceId | `9097D5E4EC13` |
| idDevice interno | `14250` |
| advertisersCode | `60973` |
| Hardware version | `2020` |
| Firmware version | `120` |
| RPM promedio | `264.14` |
