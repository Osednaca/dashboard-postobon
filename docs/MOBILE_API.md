# API móvil Postobón

Contrato local de la app React Native. Base prevista: `https://fan.finespublicidad.com/api/mobile/v1`. Estas rutas requieren desplegar la versión que las implementa; no se ejecutó despliegue ni acceso al servidor.

## Sesión

`POST /auth/login` recibe `email`, `password` y `device_name` opcional. Devuelve `{data:{token,token_type:"Bearer",expires_at,user:{id,name,email,role}}}`. Solo técnico y superadmin. El antiguo operator ya corresponde a técnico. Configuración `MOBILE_TOKEN_DAYS` (7 días por defecto).

Enviar `Authorization: Bearer TOKEN` a rutas protegidas. `GET /auth/me` devuelve `{data:user}`; `POST /auth/logout` revoca el token actual y responde 204. `POST /auth/forgot-password` recibe email y responde 202 genérico; usa recuperación web existente. Login: 5 peticiones/minuto por IP, recuperación: 3/minuto. No hay refresh token.

Los tokens móviles tienen abilities explícitas y no pueden llamar a rutas API antiguas. Tokens web/legacy con wildcard y sesiones web no sirven como sesión móvil. Cada petición revalida el rol; el superadmin móvil tampoco puede controlar equipos por rutas antiguas.

La migración aditiva de `personal_access_tokens` crea la tabla si falta y respeta una tabla preexistente. Su rollback conserva la tabla para no borrar tokens de instalaciones anteriores; retirar la funcionalidad no requiere eliminarla. Se consolidó la configuración de middleware en un solo callback para mantener tanto proxies como aislamiento API activos.

Éxito: `{data:...}`. Error: `{error:{code,message,fields,request_id}}`. No se incluyen secretos ni trazas. 401 sesión; 403 permiso; 404 recurso; 409 conflicto; 422 datos; 429 límite (Retry-After); 503 origen no verificable.

Verificación aislada: `php artisan test --compact --bootstrap=storage/framework/testing/isolated-suite-bootstrap.php --filter=MobileAuthTest`.

## Dispositivos: consulta de conexión

- `GET /devices`: `search`, `status` (`online|offline|unknown`), `establishment_id`, `page`, `per_page` (20 por defecto, máximo 50).
- `GET /establishments/{id}/devices`: mismo contrato, restringido al establecimiento de la ruta.

Respuesta `{data:[...],meta:{current_page,last_page,per_page,total},links:{first,last,prev,next}}`. Cada elemento: `{id,type,name,identifier,establishment:{id,name}|null,connection_status,last_seen_at,status_observed_at}`. IDs separados por familia, `z2:<id local o MAC descubierta>` y `wl35:<deviceId>`. No exponer IP, configuración, secretos ni detalle operativo. No existen endpoints móviles de editar/borrar/controlar dispositivos.

La lista combina inventario local y fuentes privadas, sin ejecutar sincronización de escritura por petición. Snapshot de fuentes en caché 15 segundos. Una fuente caída, equipo ausente del snapshot o señal online vencida se representa como `unknown`, nunca como apagado. El umbral de señal reciente es 180 segundos, configurable en `config/mobile.php`; ajustar a cadencia real de los equipos. Fechas ISO8601 UTC, nulas si no se conocen. `status_observed_at` es nulo cuando no pudo consultarse la fuente. Una señal vieja se conserva como última observación, sin declararla actual.

## Establecimientos

`GET/POST /establishments`, `GET/PATCH/DELETE /establishments/{id}`. Lecturas/actualizaciones devuelven `{data:objeto}`, creación 201 y borrado 204. Listas paginadas con `search` por nombre/dirección. Respetan Policies de establecimientos y ability `establishments:manage`.

Recurso: `id`, `business_type_id`, `business_type:{id,name}`, `name`, `contact_name`, `contact_phone`, `address`, `latitude`, `longitude`, `wifi_ssid`, `has_wifi_password`, `devices_count`, `wl35_device_profiles_count`. Coordenadas numéricas o nulas. Nunca se devuelve `wifi_password` ni se guarda en auditoría.

Crear requiere `name` (máx.255), `business_type_id` válido y `address` (máx.500). Contacto opcional máx.255, teléfono máx.50, SSID/clave opcionales máx.255. Latitud/longitud se envían juntas dentro de sus rangos. PATCH admite campos parciales; omitir la clave o enviarla vacía conserva la anterior. La clave se cifra mediante el modelo existente. Si hay Z2 o WL35 asociados, DELETE responde 409 `establishment_has_devices` y conserva el registro.

## Tipos de negocio

`GET/POST /business-types`, `PATCH/DELETE /business-types/{id}`; ability `business-types:manage` y Policies existentes. Recurso `{id,name,establishments_count}`. Nombre obligatorio, único y máx.120; lista con `search` y paginación. Borrar un tipo usado responde 409 `business_type_has_establishments`. Las escrituras de ambos catálogos se auditan por actor/entidad sin copiar los campos de entrada.

Verificación de catálogos/lista: `php artisan test --compact --bootstrap=storage/framework/testing/isolated-suite-bootstrap.php --filter='MobileDeviceTest|MobileEstablishmentTest|MobileBusinessTypeTest'`. Los tests simulan las fuentes privadas y no envían órdenes ni peticiones remotas reales.
