# Registro móvil de ventiladores Z2

La API `/api/mobile/v1/device-onboardings` prepara sesiones de instalación para la app nativa. El teléfono envía el nombre, modo/clase de sincronización y Wi-Fi por TCP local; Laravel no abre sockets a la LAN del instalador y no recibe la contraseña Wi-Fi. Las rutas usan el token móvil, ability `onboarding:create` y permiso vigente de técnico/superadmin. La sesión es privada para su creador; un superadmin autorizado puede supervisarla. Límite: 60 solicitudes por minuto por usuario para el grupo de onboarding.

## Configuración previa

El aprovisionamiento está deshabilitado por defecto. Estas son variables nuevas de despliegue, no valores descubiertos de una sesión remota:

```dotenv
Z2_MOBILE_ONBOARDING_ENABLED=false
Z2_MOBILE_CLOUD_DOMAIN=
Z2_MOBILE_REQUIRES_BIND=false
Z2_MOBILE_ADVERTISERS_CODE=
Z2_MOBILE_PUBLIC_ENROLLMENT_IDENTIFIER=false
```

`Z2_MOBILE_CLOUD_DOMAIN` es el destino **host:puerto del firmware**, validado entre 1–65535. No es la URL HTTPS del dashboard ni `PRIVATE_CLOUD_URL` REST. Un valor de ejemplo sin operación real sería `devices.example.test:9920`. La API responde `503 onboarding_unconfigured` hasta que un administrador configure y valide el perfil.

Para equipos ya vinculados a la nube privada, `REQUIRES_BIND=false` evita volver a enlazarlos y no expone `advertisers_code`. Si la instalación debe cambiar el vínculo, configurar `REQUIRES_BIND=true` requiere verificar primero que el `AdvertisersCode` es un identificador de enrolamiento publicable, sin privilegios administrativos. Solo entonces activar `PUBLIC_ENROLLMENT_IDENTIFIER=true`. No sustituirlo por `PRIVATE_CLOUD_TOKEN`, una contraseña o un token administrativo. La aplicación nunca recibe esos secretos. Si el firmware exige una credencial privilegiada para el bind, este mecanismo no se debe habilitar: necesita otro contrato de enrolamiento.

Validar físicamente IP, identificación local, persistencia, ACK y destino privado antes de habilitar una instalación real. No hay dependencia de endpoints chinos ni comandos remotos de reproducción. Las clases administrativas del dashboard permanecen independientes.

## Contrato

Toda respuesta de sesión usa `{ "data": { ... } }`. El `id` público es un UUID. Errores: `{ "error": { "code", "message", "fields", "request_id" } }`.

| Método y ruta | Entrada / resultado |
|---|---|
| `POST /device-onboardings` | `device_type:"z2"`, `expected_identifier`, `name`, `establishment_id`, `play_mode`, `sync_class`; header `Idempotency-Key` de 8–100 caracteres ASCII alfanuméricos o `._:-`; respuesta201 |
| `PATCH /device-onboardings/{uuid}` | Solo `name`, `establishment_id`, `play_mode`, `sync_class`; permitido antes de enviar configuración |
| `GET /device-onboardings/{uuid}` | Consulta estado y evidencia disponible; no emite comandos al equipo |
| `POST /device-onboardings/{uuid}/events` | `{event:"local_connected"|"configuration_sent", result_code?:"sent"|"readback_verified"|"connection_failed"|"response_unknown"}` |
| `POST /device-onboardings/{uuid}/complete` | Sin cuerpo de configuración; verifica de nuevo y finaliza en transacción |
| `POST /device-onboardings/{uuid}/cancel` | Sin cuerpo; cancela una sesión activa. Si ya expiró o falló, libera/conserva libre su reserva y devuelve `expired`/`failed` idempotentemente para permitir comenzar otra. No cancela una instalación completada ni presume rollback del Wi-Fi físico |

Prefijo común: `/api/mobile/v1`. Los POST de acción no aceptan campos extra, ni `online:true` como prueba. Tampoco se aceptan contraseña, payload TCP o URL/cloud destino del cliente. Los eventos son reportes del teléfono, no pruebas de éxito.

La identidad esperada es una MAC de 12 dígitos hexadecimales, normalizada a mayúsculas; se aceptan separadores `:` o `-` en la entrada. No se deriva del SSID. Nombre: máximo64 caracteres y128 bytes UTF-8, sin `&`, `=`, controles o saltos de línea mientras no se confirme escape en el firmware.

`play_mode=0` exige `sync_class=0`. `play_mode=1` permite clases A–O, índices0–14; G corresponde a6. P/15 está rechazado porque solo se observó en el generador SD, sin validación de cambio remoto/local. El TCP local documentado es opcode`0x17`: consulta sin payload; cambio `PlayMode=1&Class=6` o individual `PlayMode=0&Class=0`. La app debe reconsultar localmente y enviar Wi-Fi después; terminar de escribir en un socket no equivale a aplicar el ajuste.

DTO de sesión:

```json
{
  "data": {
    "id": "uuid-de-ejemplo",
    "status": "prepared",
    "device_type": "z2",
    "expected_identifier": "AABBCCDDEEFF",
    "name": "Entrada principal",
    "establishment_id": 12,
    "play_mode": 1,
    "sync_class": 6,
    "last_result_code": null,
    "expires_at": "2026-10-21T16:00:00Z",
    "configuration_reported_at": null,
    "completed_at": null,
    "device_id": null,
    "verification": null,
    "provisioning_profile": {
      "enabled": true,
      "version": 1,
      "tcp_port": 9910,
      "cloud_domain": "devices.example.test:9920",
      "requires_bind": false
    }
  }
}
```

El perfil se devuelve solo en `prepared`/`local_connected`; después es `null`. El identificador de resultado final usa `z2:<id local>`. `verification` solo aparece al completar y vale `private_cloud_fresh_heartbeat_and_reported_mode`. Describe evidencia de conexión y modo reportado; no garantiza sincronía de fotogramas ni que el nombre físico haya persistido.

Si se elimina el establecimiento mientras la instalación está pendiente, la siguiente consulta devuelve `status:"failed"`, `last_result_code:"establishment_unavailable"`, `establishment_id:null` y perfil `null`. La reserva de MAC queda libre. Completar se rechaza; cancelar permite descartar la recuperación local y preparar una nueva sesión para un establecimiento válido.

En la API de establecimientos, `wifi_ssid` y `wifi_password` son campos sensibles a los espacios: se conservan sus caracteres iniciales/finales al crear o actualizar, con la contraseña cifrada y solo escritura. Se aplica la misma excepción de normalización al formulario web. Otros campos, como nombre y dirección, conservan el recorte ordinario de espacios. Una contraseña vacía en edición mantiene la clave anterior, según el contrato existente. El onboarding nunca guarda esas credenciales de forma implícita.

## Secuencia y evidencia

1. Con Internet, crear sesión para MAC, establecimiento, nombre y modo/clase. Sesión de30 minutos, configurable en `config/mobile_onboarding.php`.
2. Conectar el teléfono al AP del equipo y comprobar su identidad por el método físico/local validado. Enviar comandos solo en la app nativa. Guardar la contraseña únicamente en memoria.
3. Al recuperar Internet, enviar `local_connected` si corresponde y `configuration_sent`. Se permite reportar directamente este último porque la conectividad a la API pudo perderse durante la fase local.
4. El primer `configuration_sent` captura un nuevo contador baseline de heartbeat y hora del servidor. Esto exige esperar **otro heartbeat posterior al regreso a Internet**; una señal antigua recibida durante la preparación no sirve. Repetir el evento no mueve la baseline.
5. Consultar la sesión cada3–5s. El snapshot privado se comparte con caché breve de2s; no se ejecuta el sincronizador de inventario por polling.
6. `ready_to_complete` exige misma MAC, online, `heartbeatCount` entero mayor que baseline, `lastSeenIso` posterior a la baseline y reciente (90s por defecto), sin fechas futuras anómalas, y `playMode`/`deviceClass` iguales a lo solicitado. `lastSeenIso` solo no basta: el store también lo modifica por conexión/aplicación de campos.
7. `complete` consulta el origen de nuevo, bloquea la sesión/dispositivo/establecimiento y guarda nombre local, asociación y resultado en una transacción. Es idempotente; un segundo complete devuelve el resultado sin nueva asignación/auditoría.

El sincronizador puede haber descubierto un registro sin asignación antes de completar; es válido. El onboarding no cambia el nombre solicitado ni la asociación hasta verificar. Una MAC ya asignada, incluso al mismo establecimiento por otra operación, produce409 y no se reclama silenciosamente. Un equipo borrado suavemente también se rechaza. Una reserva única por MAC impide dos instalaciones activas; expiración/cancelación la libera. La restricción única de identidad del inventario y locks resuelven conflictos de creación.

Una caída privada devuelve503, nunca online falso. La telemetría de modo puede estar ausente o no cambiar en determinado firmware: la sesión permanece pendiente y la app debe mostrarlo, sin inventar éxito. Heartbeat no prueba criptográficamente propiedad ni el SSID utilizado. Un reinicio de nube que reduzca el contador puede impedir completar esa sesión; preparar otra tras diagnosticar, sin reenviar comandos automáticamente.

## Verificación local

```text
php artisan test --compact --bootstrap=storage/framework/testing/isolated-suite-bootstrap.php tests/Feature/MobileOnboardingTest.php
```

La suite usa SQLite en memoria y HTTP simulado. Incluye idempotencia, dueño/rol/ability, configuración segura, clases inválidas, secretos rechazados, recuperación, baseline duplicada, señal antigua/incorrecta, caída nube, conflictos, cancelación/expiración y rollback si falla la auditoría. Las pruebas no envían comandos ni se conectan a un ventilador real. El ensayo Android+iPhone físico y la validación del protocolo permanecen pendientes.
