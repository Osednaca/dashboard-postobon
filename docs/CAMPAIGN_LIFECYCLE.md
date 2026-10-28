# Publicación, reproducción y limpieza de campañas

El ciclo de campañas registra cada entrega por dispositivo y archivo. Publica todos los videos, los reproduce en secuencia repetida durante la vigencia y retira los archivos al finalizar, incluso si se reutilizaron archivos ya instalados. La limpieza conserva los que todavía necesita otra campaña y evita borrar archivos ajenos o cuya identidad no pueda determinarse con seguridad.

## Uso del dashboard

1. Selecciona los destinatarios, agrega los videos en el orden deseado y define inicio y fin. Una campaña permanente no tiene vencimiento automático.
2. Verifica que cada video tenga una duración conocida y mayor que cero. La activación rechaza duraciones desconocidas: el servidor necesita ese dato para avanzar al siguiente video sin inventar un tiempo.
3. Activa la campaña. La publicación respeta su fecha de inicio y procesa los videos por dispositivo; las órdenes no se envían todas a la vez.
4. Consulta **Publicación y limpieza** en el detalle y usa **Actualizar estado** para obtener información reciente. El estado general «Finalizada» no significa que todas las SD estén limpias.

Los destinatarios quedan fijados al guardar. Las entregas registradas se conservan aunque se quite un video o se elimine lógicamente una campaña, para que la limpieza pueda terminar sin perder el vínculo con los archivos enviados.

## Estados que requieren atención

El aviso **No se pudo preparar o actualizar la campaña** muestra errores de validación o preparación incluso cuando todavía no existe ninguna entrega. Corrige el video, su duración o la selección indicada antes de volver a activarla; no interpretes «Programada» como publicación completada.

| Estado visible | Significado y acción |
|---|---|
| Publicación pendiente | Aún no se ha completado el envío. Revisa el error mostrado y la conexión del equipo. |
| Esperando confirmación de publicación | Se espera evidencia del dispositivo antes de continuar con el siguiente video. |
| Publicado | El archivo está disponible para la secuencia. No equivale a observar su reproducción en ese instante. |
| Borrado pendiente de confirmación | La aceptación de una orden no demuestra la eliminación física. Se espera evidencia posterior. |
| Borrado confirmado | El protocolo aportó la evidencia de retirada exigida para ese modelo. |
| Ausente del reporte; borrado físico sin verificar | El modelo 42 dejó de reportar el archivo. Su protocolo no demuestra que ya no exista físicamente en la SD. |
| Conservado por otra campaña | Otra campaña vigente mantiene una referencia al archivo; se revisará su retirada cuando deje de necesitarlo. |
| Conservado por seguridad | No hay una identidad física suficiente para retirarlo sin arriesgar otro archivo. Revisa el motivo mostrado. |
| Requiere revisión | El resultado es ambiguo y repetir una orden podría borrar otro video. No fuerces reintentos por un índice antiguo. |

En el modelo 42 se utiliza una lista reportada por el dispositivo después de enviar la orden, separada de la playlist que la nube desea aplicar. La ausencia en ese reporte permite verificar que el dispositivo dejó de listar el video, pero no demuestra un inventario completo de la SD; no se presenta como borrado físico confirmado. En el modelo 35, la eliminación exige la reducción de conteo confirmada por la respuesta del firmware. Si se pierde esa respuesta, los índices pueden haber cambiado y el sistema evita repetir el borrado a ciegas.

La secuencia se coordina desde el servidor según la duración de cada video. No ofrece sincronización exacta entre pantallas: la cola, la latencia y la disponibilidad del equipo afectan el momento del cambio. En un dispositivo con varias campañas vigentes, gana la de mayor prioridad; en empate, la de menor ID. Los archivos compartidos siguen protegidos aunque otra campaña controle la reproducción.

Cuando se pausa o finaliza la campaña que controla un dispositivo y no hay otra campaña que pueda tomar su lugar, se envía una orden de apagado para detener la reproducción administrada. Al reanudar una secuencia, se envía la orden de encendido. Este es el mecanismo de parada disponible en ambos modelos; no borra archivos por sí mismo.

Si un equipo pierde conexión, el servidor no puede garantizar que deje de reproducir o borre su SD a la hora exacta de fin. Mantiene la operación pendiente y conserva el error, en lugar de presentar una limpieza ficticia. La pausa y la finalización impiden que trabajos obsoletos sigan avanzando la campaña.

## Requisitos de operación y despliegue

Los cambios requieren desplegar versiones compatibles del dashboard, la nube privada del modelo 42 y el gateway del modelo 35, además de aplicar la migración del registro de campañas. No habilites la limpieza automática con una versión antigua que solo devuelve estado optimista.

- Usa una cola asíncrona `database` o `redis`; `sync` no sirve para este ciclo.
- Configura `retry_after` de esa conexión por encima de 1900 segundos. La conexión `database` tiene 2100 por defecto; para Redis configura `REDIS_QUEUE_RETRY_AFTER=2100` o un valor mayor que el timeout del trabajo.
- Usa un almacén de caché persistente y compartido por los procesos, como `database` o `redis`, para que los bloqueos por dispositivo coordinen publicación, reproducción y borrado. Una caché privada por proceso no proporciona esa protección.
- Mantén el scheduler activo. `campaigns:process` se agenda cada segundo y encola el trabajo; no debe realizar las transferencias largas en el proceso del scheduler.
- Mantén trabajadores dedicados a la cola `campaigns`. La configuración de Supervisor incluye dos procesos; ajusta su cantidad a la flota y la capacidad del servidor. Una transferencia larga de un dispositivo no debería bloquear el avance de todos los demás.

Comando equivalente del trabajador dedicado:

```bash
php artisan queue:work --queue=campaigns --sleep=1 --tries=1 --timeout=1900 --max-time=3600
```

El scheduler existente ejecuta `schedule:run` cada minuto. Con tareas cada segundo, ese comando sigue activo hasta el final del minuto; el bucle de Supervisor espera a que termine. En un despliegue autorizado, después de actualizar código/configuración, interrumpe el scheduler anterior y reinicia los trabajadores para que carguen la nueva versión:

```bash
php artisan schedule:interrupt
php artisan queue:restart
```

Consulta la [documentación oficial de tareas subminuto de Laravel 12](https://laravel.com/framework/docs/12.x/scheduling#sub-minute-scheduled-tasks). Los comandos anteriores son instrucciones de operación: no se ejecutaron contra producción durante la implementación.

## Comprobación antes de dar el despliegue por terminado

- Comprueba una campaña con dos videos y varios dispositivos: todos deben llegar a «Publicado» y reproducirse en el orden configurado, repitiéndose.
- Comprueba dos campañas que compartan un archivo: al finalizar la primera debe conservarse; al terminar la última referencia debe comenzar la limpieza.
- Desconecta un equipo durante el fin: debe aparecer pendiente, nunca «Borrado confirmado» sin evidencia.
- Comprueba una respuesta ambigua del modelo 35: debe quedar «Requiere revisión», sin repetir automáticamente el índice.
- Verifica que los archivos ajenos a la campaña y los dispositivos no seleccionados permanezcan intactos.

Estas comprobaciones sobre hardware son posteriores a las pruebas automatizadas con fakes; requieren un entorno y dispositivos de prueba autorizados.
