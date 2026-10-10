# Instalar el panel

El panel puede abrirse como aplicación independiente, con inicio en `/dashboard` y el mismo inicio de sesión que la web. Se necesita HTTPS en producción; `localhost` y `127.0.0.1` sirven para comprobarlo localmente.

En navegadores compatibles aparece **Instalar aplicación** al final del menú lateral cuando el navegador ofrece la instalación. También se puede usar la opción de instalación del navegador. En iPhone/iPad se utiliza **Compartir → Agregar a pantalla de inicio**; no hay un diálogo programático equivalente. La disponibilidad depende del navegador y sus criterios, y el botón no promete una instalación ya realizada.

## Sin conexión y privacidad

El service worker conserva únicamente `/offline.html`, una página pública sin datos personales. Las navegaciones del panel consultan siempre la red; si no hay conexión muestran ese aviso. No se guardan páginas de sesión, respuestas de API, videos, imágenes de la biblioteca ni comandos. No hay cola de acciones sin conexión. Las respuestas de error del servidor, incluido HTTP 500, conservan su estado y contenido.

Las rutas `/api`, `/media`, `/storage` y `/build`, sus subrutas, recursos y peticiones distintas de GET pasan directamente al navegador. La página offline no depende de fuentes, imágenes ni scripts externos.

## Archivos y actualización

El servidor debe servir `public/manifest.webmanifest`, `public/sw.js`, `public/offline.html` y `public/pwa/*.png` como archivos públicos estáticos. El worker debe conservar su URL `/sw.js` y un tipo JavaScript válido; el manifest debe servirse como JSON/manifest. No aplicar a `/sw.js` una política de cache inmutable de larga duración. Los enlaces relativos al origen evitan depender de la URL de assets o del esquema detectado por el proxy.

El registro usa `updateViaCache: 'none'`. Al cambiar la página offline se incrementa `CACHE_NAME` en `public/sw.js`; el nuevo worker espera el cierre de las pestañas anteriores antes de activarse. Al activarse elimina únicamente caches antiguos con su propio prefijo. No se fuerzan recargas de formularios ni se eliminan caches de otras aplicaciones.

Los iconos PNG se generaron a partir de `public/logo_postobon.png`, conservando sus proporciones y centrando el logo sobre fondo blanco. Son iconos de propósito `any`, sin prometer recorte maskable.

## Comprobación

Ejecutar `php artisan test --filter=PwaTest`, `node --test tests/js/pwa.test.mjs` y `npm run build`. En un navegador sobre HTTPS/localhost: comprobar el manifest y worker en herramientas de desarrollo, recargar una página, desconectar la red y abrir `/dashboard`; debe aparecer únicamente el aviso público. Reconectar permite volver a la pantalla autenticada o al login según la sesión. La instalación real y sus menús requieren comprobar el navegador/plataforma de destino.

Fuentes consultadas: [requisitos de instalación de MDN](https://developer.mozilla.org/en-US/docs/Web/Progressive_web_apps/Guides/Making_PWAs_installable), [service workers de MDN](https://developer.mozilla.org/en-US/docs/Web/API/Service_Worker_API/Using_Service_Workers) y [diálogo de instalación de web.dev](https://web.dev/learn/pwa/installation-prompt). Un service worker aporta el aviso offline; el manifest y HTTPS son los requisitos centrales de instalación.
