# Baja y reinstalación de ventiladores 42

Un superadministrador puede retirar un equipo desde el menú de Dispositivos. Se desvincula del establecimiento y grupo y se oculta del inventario mediante una baja recuperable. El historial local se conserva. La nube privada y el ventilador mantienen su configuración: esta acción no cambia su Wi-Fi ni borra datos de nube.

La sincronización de nube no restablece una baja explícita. El onboarding puede registrar esa MAC otra vez y, al recibir una señal nueva y validar el modo y clase, restaura el mismo registro con el nuevo nombre y establecimiento. Otras bajas anteriores, sin marcador de reinstalación, continúan bloqueadas.

Las programaciones y campañas guardadas al hacer la baja quedan excluidas para este equipo, incluso tras reinstalarlo; siguen aplicando a sus otros destinatarios. Para asignarlo de nuevo a una regla, crear una nueva regla después de la baja. Los trabajos de flota anteriores se filtran por destinatario; una orden ya enviada o en tránsito no puede revocarse. No se ha demostrado aislamiento de reglas creadas exactamente al mismo tiempo que la baja ni de solicitudes concurrentes sin registro local; revisar estas operaciones antes de reinstalar.

El despliegue necesita ejecutar `php artisan migrate --force`. La migración conserva las exclusiones por dispositivo y regla. Su rollback se bloquea mientras existan bajas o exclusiones, para evitar restablecer silenciosamente reglas anteriores; conservarla aplicada al revertir código y planificar la reversión antes de eliminar datos.
