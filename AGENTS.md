# 3D Fan Dashboard - Agent Instructions

## Project Overview

**3D Fan Dashboard** is a production-ready Laravel 12 application for managing 3D fan devices, campaigns, media, and analytics.

## Stack

- **Backend**: Laravel 12, PHP 8.4+, MySQL
- **Frontend**: Blade, Tailwind CSS v4, Alpine.js, Chart.js, Leaflet.js
- **Infra**: Laravel Scheduler, Laravel Queues, Laravel Sanctum, Laravel Storage
- **External API**: Z2 FanCloud API (RSA auth, JSESSIONID management)

## Architecture

- **Service Layer**: app/Services/ (business logic)
- **Repository Pattern**: app/Repositories/ (data access)
- **DTOs**: app/DTOs/ (data transfer)
- **Policies**: app/Policies/ (authorization)
- **Form Requests**: app/Http/Requests/ (validation)
- **Events & Listeners**: app/Events/, app/Listeners/
- **Jobs**: app/Jobs/ (background tasks)
- **External Services**: app/Services/Z2/ (REAL Z2 Cloud API integration)
- **API Logging**: app/Models/ApiLog.php (stores all Z2 API calls)

## Key Commands

```bash
# Install dependencies (run in proper environment)
composer install
npm install

# Setup application
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan storage:link

# Run development server
php artisan serve
npm run dev

# Run scheduled tasks
php artisan schedule:run

# Run queue worker
php artisan queue:work

# Run tests
php artisan test
```

## Environment Variables

Required `.env` variables:
- `DB_CONNECTION=mysql`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- `Z2_BASE_URL`, `Z2_USERNAME`, `Z2_PASSWORD`, `Z2_RSA_PUBLIC_KEY`
- `REDIS_HOST`, `REDIS_PASSWORD`, `REDIS_PORT` (for queues/cache)
- `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_FROM_ADDRESS`

## Database

- 14 main tables + Laravel defaults + pivot tables
- Seeders create realistic demo data
- Use `php artisan db:seed` to populate

## Roles

- **administrator**: Full access to all modules
- **operator**: Access to dashboard, devices, groups, campaigns, media, locations

## Scheduling

Defined in `routes/console.php`:
- Device status sync: every minute
- Full device sync: every 10 minutes
- Group sync: every 5 minutes
- Video sync: every 15 minutes
- Heartbeat (keep session alive): every 5 minutes
- Offline detection: every 10 minutes
- Schedule processing: every minute
- Analytics: daily at 00:00
- Subscriptions: daily at 08:00

## API Endpoints

All API routes prefixed with `/api/` and protected by `auth:sanctum`.
See `routes/api.php` for full list.

## UI Design System

- Colors: Primary #FF0000, Secondary #F57CB6, Accent #FFD200, Background #FFFFFF, Text #333333
- Components: resources/views/components/ (card, button, input, modal, table, badge, etc.)
- Layouts: resources/views/layouts/ (app, guest, blank)
- All text in Spanish

## Z2 API Integration (REAL - No Mocks)

Services in `app/Services/Z2/` (connected to real Z2 Cloud):
- `FanCloudService`: Main client with RSA auth, JSESSIONID management, auto-retry
- `Z2AuthService`: Authentication (login, session check)
- `Z2DeviceService`: Real device sync, power on/off, unbind, change video
- `Z2GroupService`: Real group sync, create, delete, assign device
- `Z2VideoService`: Real video sync, upload (3-step FTP flow), delete
- `Z2PlaylistService`: Playlist management, assign video to device/group
- `Z2CampaignSyncService`: Publish campaigns to real devices/groups

API Base URL: `http://www.holographicdisplay.cn:8088`
- Authentication: RSA + JSESSIONID cookie
- All endpoints: `application/x-www-form-urlencoded`
- Logging: Every call stored in `api_logs` table

### ⚠️ Critical: RSA Encryption (NO_PADDING)

The Z2 API uses **textbook RSA with NO padding** (`RSA_NO_PADDING`), NOT PKCS1 v1.5.

To encrypt the password:
1. Get RSA public key from `GET /admin/AdminLoginR` → returns `pubmodules_base64`
2. Build PEM key from base64 string
3. Create a **128-byte block** (zero-filled), place password at the **END** (right-justified)
4. Encrypt with `RSA::ENCRYPTION_NONE` (phpseclib) or `RSA_NO_PADDING` (OpenSSL)
5. Convert ciphertext to hex string

```php
$data = str_repeat("\x00", 128 - strlen($password)) . $password;
$key = PublicKeyLoader::load($pem)->withPadding(RSA::ENCRYPTION_NONE);
$encrypted = bin2hex($key->encrypt($data));
```

### ⚠️ Critical: User-Agent Header

The Z2 API **validates the User-Agent** and rejects commands from unknown clients.
- **Must use:** `User-Agent: okhttp-okgo/jeasonlzy` (same as the official mobile app)
- **Do NOT use:** Custom user agents like `Z2-FanDashboard/1.0` — the server will return success but ignore the command

### ⚠️ Critical: devicePower Endpoint Parameters

The `/User/devicePower` endpoint uses **different parameters** for ON and OFF:
- **Encender (ON):** `devicePowerOn=1` (NOT `devicePower=1`)
- **Apagar (OFF):** `devicePowerOff=1` (NOT `devicePower=0`)

Sending `devicePower=1` returns `result:0` (success) but the device does NOT execute the command.

### ⚠️ Critical: groupDeviceList Endpoint Requires groupID

The `/User/groupDeviceList` endpoint **requires** `groupID` parameter. Sending without it returns HTTP 400 or empty list.
Env: `Z2_BASE_URL`, `Z2_USERNAME`, `Z2_PASSWORD`

### ⚠️ Critical: groupDeviceList Endpoint Requires groupID

The `/User/groupDeviceList` endpoint **requires** `groupID` parameter. Sending without it returns HTTP 400 or empty list.

**Correct flow to get all devices:**
1. Call `POST /User/groupList` to get all group IDs
2. For each group ID (including `groupID=0` for ungrouped), call `POST /User/groupDeviceList` with:
   ```
   userName={username}
   iDisplayStart=0
   iDisplayLength=50
   deviceCode=
   groupID={groupId}
   ```
3. Aggregate results from all groups

**Endpoints updated to use this pattern:**
- `Z2DeviceService::syncDevices()` - syncs all devices across all groups
- `Z2DeviceService::getDeviceDetail()` - searches device across all groups
- `Z2PlaylistService::getDevicePlaylist()` - searches playlist across all groups
- `Z2GroupService::syncGroups()` - uses `/User/groupList` directly
- `Z2Diagnostics::testDeviceList()` - tests all groups
- `FanCloudService::authenticate()` - validates session using `/User/groupList`

## Important Notes

- Use `CheckRole` middleware for role-based access
- All controllers use constructor injection
- Use policies for authorization
- Use Form Requests for validation
- Use `$request->validated()` only
- Use `Cache::remember()` for expensive queries
- Queue heavy operations (email, analytics, sync)
- Log all actions via AuditLogService
- Use DTOs for data transfer between layers

## Code Style

- Follow PSR-12
- Use type hints everywhere
- Use docblocks for complex methods
- Prefer named routes
- Use `route()` helper for URLs
- Follow Laravel 12 conventions in `bootstrap/app.php`

## Testing

- Feature tests in `tests/Feature/`
- Unit tests in `tests/Unit/`
- Use `LazilyRefreshDatabase`
- Use fakes for external APIs
- Use `assertModelExists()` over `assertDatabaseHas()`
