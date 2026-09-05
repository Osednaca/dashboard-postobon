<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## Dispositivos unificados WL35 + Z2

La pantalla **Dispositivos** (`/devices`) usa `dashboard-fan-wl35` como gateway para agregar los WL35 a los Z2 existentes y controlar ambos protocolos sin abrir una segunda conexión con los ESP32. Configura en producción:

```dotenv
UNIFIED_FLEET_API_URL=http://IP_O_HOST_DEL_GATEWAY:4173
UNIFIED_FLEET_API_TOKEN=el-mismo-valor-de-ADMIN_TOKEN-del-gateway
UNIFIED_FLEET_TIMEOUT=30
UNIFIED_FLEET_UPLOAD_TIMEOUT=900
UNIFIED_FLEET_OPERATION_TIMEOUT=1800
UNIFIED_FLEET_CONNECT_TIMEOUT=10
```

Si Laravel y Node corren directamente en el mismo VPS, `http://127.0.0.1:4173` es válido. Si Laravel está dentro de Docker, `127.0.0.1` apunta al contenedor y debes usar el nombre del servicio o la IP del host. Si se omite la variable, Laravel deriva el host desde `PRIVATE_CLOUD_URL` y usa el puerto `4173`.

Después de modificar el `.env`, limpia únicamente la configuración cacheada de Laravel con `php artisan config:clear` (o vuelve a generar el caché con `php artisan config:cache`). El gateway debe permanecer ejecutándose por separado y conservar la conexión exclusiva con los WL35 y la nube privada Z2.

`/devices` y `/instant-play` consumen la misma flota unificada. En reproducción instantánea,
los Z2 reciben el nombre del archivo de la biblioteca y los WL35 reciben el índice numérico del
video que ya existe en su SD. Una selección masiva mixta envía ambos valores en una sola orden y
el gateway aplica a cada ventilador el valor correspondiente a su protocolo.

Cada WL35 tiene además una página de detalle accesible desde `/devices`. Allí se muestra el estado
en vivo y se pueden ejecutar energía, Bluetooth, reproducción, volumen, carga de video, borrado
individual y formateo de SD. El nombre, establecimiento, dirección, ciudad, país, contacto,
coordenadas, ubicación, grupo y notas se guardan localmente en `wl35_device_profiles`, por lo que
siguen visibles aunque el gateway o el ventilador estén desconectados. Después de desplegar esta
funcionalidad es obligatorio ejecutar `php artisan migrate --force`.

### Operaciones en segundo plano

Las cargas y los formateos iniciados desde `/devices` se entregan a la cola para evitar que Nginx mantenga abierta una petición durante toda la conversión o el borrado. En Z2 el gateway usa el formateo nativo. En WL35 elimina el último índice disponible y espera la lista multimedia actualizada antes de continuar con el siguiente; distintos ventiladores sí pueden avanzar en paralelo. La configuración de producción debe incluir:

```dotenv
QUEUE_CONNECTION=database
DB_QUEUE_RETRY_AFTER=2100
```

Ejecuta las migraciones y mantén un worker persistente. El timeout del worker debe ser menor que `DB_QUEUE_RETRY_AFTER` y mayor que el timeout del job (1900 segundos):

```bash
php artisan migrate --force
php artisan queue:work --queue=default --sleep=2 --tries=1 --timeout=2000
```

El worker debe administrarse con Supervisor o systemd; no debe depender de una terminal SSH abierta. Para aceptar archivos de hasta 250 MB, configura además `upload_max_filesize` y `post_max_size` en PHP, y `client_max_body_size` en Nginx. El `fastcgi_read_timeout` ya no necesita cubrir la conversión porque esa operación ocurre fuera de la petición HTTP.

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
