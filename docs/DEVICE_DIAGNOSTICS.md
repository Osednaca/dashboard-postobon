# Diagnóstico del detalle de dispositivos

El caso reportado es `/devices/1`. No se ha inspeccionado su base de datos ni su excepción en producción. Los casos reproducibles en pruebas locales son distintos de una confirmación de la causa real.

El detalle ahora rechaza respuestas de telemetría sin un objeto de dispositivo válido y resultado exitoso; conserva los datos locales cuando la nube no responde. Un valor escalar antes provocaba un `TypeError`. El historial carga únicamente los diez registros más recientes: antes recuperaba todo el historial para después mostrar diez, aumentando el consumo de memoria según la antigüedad del dispositivo. Los demás registros permanecen en la base de datos.

Las excepciones inesperadas, incluso durante el renderizado de Blade, conservan la respuesta HTTP 500 del framework. Se registra un diagnóstico adicional en `stderr`, independiente de `LOG_CHANNEL` y `LOG_STACK`, para que un despliegue con `LOG_STACK=single` también lo muestre en los logs del contenedor. El Dockerfile asegura la captura de stderr de PHP-FPM; Supervisor ya lo reenvía al contenedor. Hay que desplegar la imagen actualizada para activar estos cambios.

Con `APP_DEBUG=false`, la respuesta pública sigue siendo genérica. La cabecera `X-Error-Reference` permite correlacionar el fallo con el campo `reference` del diagnóstico. Este contiene clase de excepción, archivo, línea, ruta nombrada y hasta ocho marcos de llamada sin argumentos. No incluye mensajes de excepción, cabeceras, query strings, cuerpos ni datos de modelos. Laravel conserva además su registro normal completo en el canal configurado; hay que tratar ese registro como información restringida.

Los errores 404, autorización y validación mantienen su tratamiento habitual. Este diagnóstico no alcanza fallos anteriores al arranque de Laravel o agotamiento fatal de memoria; en esos casos también se necesitan los logs de PHP-FPM/contenedor. Para investigar un próximo fallo, recoger la hora y `X-Error-Reference` de la respuesta y localizar esa referencia en EasyPanel. No activar `APP_DEBUG` en producción.

Validación local: `php artisan test --filter=DeviceDiagnosticsTest`, con SQLite en memoria y HTTP simulado, sin consultas a equipos reales. La integración de reportes usa la API `withExceptions()->report/respond` de Laravel; comportamiento contrastado con el Handler instalado de Laravel 12. Referencia: [manejo de errores de Laravel](https://laravel.com/docs/12.x/errors).
