# Device group filtering and scoped video removal

## Objective and authorization
User explicitly requests a group filter and an action to remove a video from devices in /devices, including a specific group. Implement locally in dashboard-postobon on codex/device-content-preview, starting f3b7a10. Existing authorization permits GitHub push to Osednaca/dashboard-postobon through existing Git Credential Manager. No deployment, real device commands, remote gateway access or production database mutation. Preserve unrelated docs/DESPLIEGUE_LATENCIA.md.

## Evidence and decisions
Delegated map_device_video_removal inspected the list, provider adapters, memberships and existing removal paths. Model42 supports removeVideoFromDevice with confirmed response validation and pending physical confirmation tracking. Its pure cloudFilename resolver avoids uploading media. Model35 supports deleteWl35Video but indices differ per device: resolve each persisted Wl35DeviceMedia by media_id/device_id, validate acknowledgment before shifting indices. Never delete the global library. Unknown mappings are reported rather than guessed. Group filtering must precede42 pagination and apply to persisted35 membership including offline profiles. Hidden selections must never enter removal; backend validates every target and optional group before dispatch.

## Workflow, checks and delivery
- Delegated direct: mapping trigger4+ files completed; both bounded writers touch multiple non-trivial files. Parent owns integration and commits. No SDD.
- TDD OFF: explicit project user decision2026-10-10 retained in odd/tasks/device-content-preview.md. Ordinary regression checks required. PHP runner: php artisan test --compact --bootstrap=storage/framework/testing/isolated-suite-bootstrap.php (SQLite :memory:, blocked stray HTTP). JS: node --test tests/js/*.test.mjs. Vite: npm run build. Pint for changed PHP and git diff --check.
- RDD disabled/unmanaged, fresh globalOFF status. Do not start native reviews.
- Delivery ask-on-risk with cached feature-branch-chain. Forecast T1 backend350-600 authored lines, T2 list/UI350-650 including tests. About400 is advisory; preserve coherent behavior and meaningful tests. Conventional work-unit commits, no PR requested.
- Engram topic odd/device-group-video-removal/tasks, project dashboard-postobon, full mirror plus this repository-relative locator. Initial scoped search found no existing observation. Read back writes before implementation and after each task.

## Tasks
- [x] T1 — Scoped video removal backend for42/35.
  - Route delegated: new request/controller/service, route and regression tests; writer owns these paths, avoids DeviceController/index and list assets.
  - Contract POST devices/bulk-remove-video, name devices.bulk-remove-video, superadmin only. JSON request media_id (local Media id), device_keys (z2:<local id> or wl35:<device_id>), optional group_id. Validate all local identities/group membership before any command. Return results per target with key/name/status/message; status accepted or failed. No physical completion claim. HTTP200 allows partial results; validation errors422.
  - Acceptance: two35 devices resolve different indices independently,42 uses existing cloud identity, mixed targets and independent failures work. No global deletion or upload, strict ACK validation before mapping changes, per-device serialization where supported. Audit accepted/failed dispatch with actual user. Preserve existing roles and provider behavior.
  - Checks: new isolated endpoint tests, existing DeviceContentRemovalTest/Wl35LibraryUploadTest, style and diff.
  - Implemented validated all-target preflight, physical-MAC deduplication, pure42 filename resolution,35 per-device mapping/session/index checks and lock, strict ACK validation, isolated result/audit failure handling. Unknown DELETE outcomes invalidate35 library identities without shifting indices, preventing a repeat on a potentially shifted video. Confirmed ACK with local mapping failure stays accepted with explicit warning; transport details remain private.
  - Observed writer51tests/251assertions; parent integrated83tests/740assertions in12.55s across new endpoint/group filter plus existing removal/upload/layout/roles. Changed9PHP Pint and diff checks pass. Parent inspected request/controller/service/routes/tests and actual provider contract evidence. No external commands. RDD disabled/unmanaged. Work-unit commit follows.
  - Limits: lock serializes this endpoint only; detail/upload flows retain existing concurrency behavior. Only known identities are removable in bulk. If both mapping update and invalidation persistence fail, operator gets an explicit no-repeat warning and error is logged; no physical success is claimed.
- [ ] T2 — Group filter and visible-target removal UI.
  - Route delegated: index controller/query validation, list Blade/partial/JS and regressions; owns no T1 paths/routes.
  - Acceptance: group filter affects42 pagination and all35 profiles, query persists, invalid groups handled. Per-row and bulk Quitar video show exact visible target snapshot/count/names, media selection and confirmation. Submit T1 contract including active group. Hidden selections excluded; pending/results/error states truthful and accessible. Global Medios library remains available. Only superadmin sees controls.
  - Checks: group filter and role HTTP tests; JS target selection/dialog behavior; existing layout/role regressions; build and isolated rendered browser check if available.
  - Commit and evidence pending.

## Progress and next step
Initial file and full Engram observation7 were read back before source implementation. Both delegated writers finished; parent inspected their source/tests. T1 accepted after integrated83PHPtests/740assertions; T2 final evidence will be recorded in its work-unit commit. All71JS tests and production Vite build68modules pass,9PHP Pint and diff checks pass. No remaining failed checks. Initial fixture dropdown selection used a nonexistent fixture label; corrected after reading actual options, no application defect. No need to repeat unrelated full PHP/demo seeding. Physical device verification and deployment remain user-owned. Next commit T1, then record T2 browser evidence and commit, mirror each update, and publish authorized branch.
