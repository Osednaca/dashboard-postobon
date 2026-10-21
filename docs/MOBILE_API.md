# API móvil Postobón

Contrato local de la app React Native. Base prevista: `https://fan.finespublicidad.com/api/mobile/v1`. Estas rutas requieren desplegar la versión que las implementa; no se ejecutó despliegue ni acceso al servidor.

## Sesión

`POST /auth/login` recibe `email`, `password` y `device_name` opcional. Devuelve `{data:{token,token_type:"Bearer",expires_at,user:{id,name,email,role}}}`. Solo técnico y superadmin. El antiguo operator ya corresponde a técnico. Configuración `MOBILE_TOKEN_DAYS` (7 días por defecto).

Enviar `Authorization: Bearer TOKEN` a rutas protegidas. `GET /auth/me` devuelve `{data:user}`; `POST /auth/logout` revoca el token actual y responde 204. `POST /auth/forgot-password` recibe email y responde 202 genérico; usa recuperación web existente. Login: 5 peticiones/minuto por IP, recuperación: 3/minuto. No hay refresh token.

Los tokens móviles tienen abilities explícitas y no pueden llamar a rutas API antiguas. Tokens web/legacy con wildcard y sesiones web no sirven como sesión móvil. Cada petición revalida el rol; el superadmin móvil tampoco puede controlar equipos por rutas antiguas.

La migración aditiva de `personal_access_tokens` crea la tabla si falta y respeta una tabla preexistente. Su rollback conserva la tabla para no borrar tokens de instalaciones anteriores; retirar la funcionalidad no requiere eliminarla. Se consolidó la configuración de middleware en un solo callback para mantener tanto proxies como aislamiento API activos.

Éxito: `{data:...}`. Error: `{error:{code,message,fields,request_id}}`. No se incluyen secretos ni trazas. 401 sesión; 403 permiso; 404 recurso; 409 conflicto; 422 datos; 429 límite (Retry-After); 503 origen no verificable.

Verificación aislada: `php artisan test --compact --bootstrap=storage/framework/testing/isolated-suite-bootstrap.php --filter=MobileAuthTest`.
