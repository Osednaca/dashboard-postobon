# Nombres de dispositivos

El nombre Z2 se administra en el panel. Al editarlo se guarda localmente; abrir la lista, recibir telemetría o ejecutar la sincronización periódica no vuelve a reemplazarlo con el nombre de la nube. La lista, el detalle y la etiqueta del preview usan ese nombre local.

Para un equipo nuevo, la sincronización toma el nombre informado por la nube, o `Device <MAC>` si falta o es igual a la MAC. En sincronizaciones posteriores siguen actualizándose firmware, hardware, RPM, estado, última conexión, encendido y Bluetooth. Un registro restaurado también conserva su nombre local. No se envía una orden de renombrado al ventilador ni se modifica el nombre de la app del fabricante.

No se requiere migración. Los nombres locales existentes se conservan; un nombre que ya hubiera sido reemplazado antes de esta corrección debe editarse otra vez. Las pruebas de `DeviceNameTest` usan SQLite y respuestas HTTP simuladas, sin acceder a equipos reales.
