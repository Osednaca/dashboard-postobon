# Detalle de dispositivo Z2

El detalle usa una sola columna, con el mismo orden en escritorio y móvil: Contenido actual, Campañas Asignadas, Videos del Dispositivo y Asignar Video Directamente. Después aparece Información del Dispositivo, con volumen, Bluetooth, establecimiento, ubicación y datos administrativos. Los controles del encabezado y los diálogos de confirmación permanecen disponibles.

La página no muestra el historial de heartbeats, RPM ni el JSON de Z2 Cloud. Tampoco carga la relación de heartbeats ni hace la consulta adicional dedicada a ese JSON; conserva la lectura necesaria para playlist, volumen y Bluetooth. El historial almacenado, la sincronización y el diagnóstico de errores permanecen intactos.

`DeviceDetailLayoutTest` comprueba el orden, las secciones retiradas y los controles con campañas y videos presentes o ausentes. `DeviceDiagnosticsTest` comprueba que los registros históricos se conservan sin cargarse para esta vista y que las excepciones siguen siendo diagnosticables. Estas pruebas usan SQLite y HTTP simulado.
