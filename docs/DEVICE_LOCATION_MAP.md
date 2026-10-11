# Mapas de dispositivos

El detalle WL35 muestra la ubicación guardada aunque el equipo esté desconectado. Usa el primer par de coordenadas completo y válido: establecimiento asociado, perfil antiguo del dispositivo, ubicación antigua. Acepta latitud o longitud cero y nunca combina valores de fuentes distintas. Si faltan coordenadas, permite ir a editar el establecimiento o indica dónde asociarlo.

El mapa reutiliza el iframe de Google Maps existente en establecimientos; su contenido necesita conexión a Google. El vínculo para abrir la ubicación permanece disponible junto al mapa. No utiliza la posición del navegador ni comandos del ventilador.

El contenedor Leaflet del dashboard tiene un contexto de apilamiento propio (`isolate`, `z-0`), de modo que sus paneles y controles quedan debajo del menú móvil. No se cambia el inventario del mapa del dashboard.

Pruebas locales: `php artisan test --filter=DeviceLocationMapTest`. Verificar también en un viewport móvil que el menú y su fondo reciben los clics cuando cubren el mapa.
