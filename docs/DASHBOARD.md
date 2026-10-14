# Dashboard: dispositivos y establecimientos

El dashboard consulta conectividad al abrir la página. El total reúne dispositivos Z2 y WL35 guardados y detectados, sin duplicar la identidad de un mismo equipo. Los identificadores Z2 se normalizan en mayúsculas, sin separadores MAC. Los nombres y establecimientos guardados prevalecen sobre la información del proveedor.

«En línea» describe la conexión, independientemente de que el ventilador esté encendido. Un equipo guardado que no aparece en la respuesta, tiene datos inválidos o depende de un proveedor inaccesible figura «sin confirmar». El estado guardado anteriormente no se presenta como una medición actual. Por eso cuatro dispositivos registrados y tres en línea son cifras compatibles; abrir la página no borra inventario ni ejecuta sincronizaciones o comandos.

La consulta usa `/api/fleet` y, cuando no recibe Z2 de una fuente válida, intenta la lectura directa de `/api/devices` en la nube privada configurada. Un fallo de Z2 no elimina el resultado disponible de WL35. No hay sondeo periódico ni reproducción de previews en el dashboard. La API histórica `/api/dashboard` conserva su servicio y formato.

El mapa dibuja un marcador por establecimiento con un par completo de coordenadas válidas. Acepta cero; no combina coordenadas de distintas fuentes. El popup muestra los dispositivos Z2/WL35 asignados mediante `establishment_id`, con nombre local, tipo, conexión y enlace de detalle cuando existe. Un establecimiento sin dispositivos también aparece. Los establecimientos sin coordenadas se contabilizan aparte y no reciben una ubicación ficticia. Los dispositivos sin establecimiento siguen formando parte del total.

Los nombres y direcciones del popup se insertan como texto, y los enlaces se restringen al mismo origen. El payload no contiene claves Wi-Fi ni datos de contacto. Leaflet y las teselas mantienen los proveedores públicos existentes; un fallo de carga de Leaflet muestra un mensaje recuperable. El contenedor del mapa conserva su propio contexto de apilamiento para no cubrir la navegación móvil.

Comprobaciones locales: `php artisan test --filter="DashboardSnapshotTest|DeviceLocationMapTest"`, `node --test tests/js/dashboard-map.test.mjs`, Pint y `npm run build`. Las pruebas usan SQLite en memoria, respuestas HTTP simuladas y un mapa simulado; no verifican el inventario de producción ni envían órdenes físicas.
