# Device content preview

## Objective and authorization
Show the locally available video corresponding to the content a holographic fan reports as current on `/dashboard`, Z2 detail, and WL35 detail. The user explicitly authorized implementation on 2026-10-10; frame synchronization and live capture are unnecessary.

Follow-up authorization (2026-10-10): correct deleted videos still listed on device detail and `/media`, display an image/video frame in the media library, and show content and duration on media detail (reported example `/media/56873`). That record was not inspected because no isolated local database was available. Fix verified code causes with isolated fixtures; do not claim live-data repair.

## Problem, scope, and constraints
Existing telemetry identifies the active Z2 filename or WL35 video index, but these screens do not show its associated video. Resolve existing library files only; do not infer playback from desired content or playlist order. Preserve offline, powered-off, unknown, missing mapping, and missing file states. Use authenticated same-origin media access, including HTTP Range support, when the source belongs to configured local/private storage. No device commands, remote execution, deployment, push, or PR creation is authorized by this feature.

Preserve pre-existing modifications in `docs/DESPLIEGUE_LATENCIA.md` and the `private-cloud` submodule. Implementation stays in Laravel; no firmware or protocol changes. Existing direct uploads without a retained library source may show an unavailable preview.

## Workflow configuration
- Route: delegated direct. Mapping needed 4+ files; preparation and both implementation tasks touch multiple non-trivial files.
- TDD: OFF, explicit user answer in this chat on 2026-10-10. Ordinary functional checks required. Runner: `php artisan test`.
- RDD: disabled/unmanaged; `gentle-ai review mode status` reported OFF from global preference. Do not start native review.
- Delivery strategy: ask-on-risk; chain strategy: feature-branch-chain, explicitly selected by user. Keep local work-unit slices; create no remote PRs.
- Feature branch: `codex/device-content-preview`, branch point `91694f0` on main.
- Forecast: 500–650 authored additions plus deletions across two coherent work units; generated build outputs excluded. About 400 lines per task is advisory, never a reason to omit tests, compress code, or split artificially.
- Follow-up forecast: 900–1300 additional authored lines across T3–T5, with the existing feature-branch-chain strategy retained. Preserve meaningful unit boundaries and report cohesive overages, without artificial compression or omitted checks.
- Engram mirror: PENDING/unavailable; no Engram tools exposed. Intended project `dashboard-postobon`, topic `odd/device-content-preview/tasks`. Mirror full document and locator when available.

## Tasks and acceptance
- [x] T1 — Resolve reported content and serve its available video through authorized read endpoints.
  - Route: delegated; preparation + 2+ non-trivial source/test files.
  - Exact Z2 filename mapping and device-specific WL35 index mapping; batch dashboard resolution; no desired-content fallback.
  - Auth/policy checks, safe fixed-source media reads, Range handling, no credential exposure or mutating external calls.
  - Checks: focused PHPUnit cases for matching, unavailable states, auth, source validation, local/private bytes and Range. Full suite at closure.
  - Verification: focused tests passed, then full isolated `php artisan test` passed (19 tests, 93 assertions); Pint check passed on 7 changed PHP files; `git diff --check` passed. Parent inspected resolver, source validation, controllers, tests and documentation.
  - Commit: `82fff2cedef7fdc7096b4fbd901f0c758b2308e2`. RDD: disabled/unmanaged. Slice 1 boundary: `91694f0` → `82fff2c`.
  - Authored implementation/tests/docs: 462 lines before tracking document. Cohesive resolver + authenticated Range delivery + meaningful source/auth tests exceed the advisory task target; retain complete tests and formatting. A future PR may require a size exception for this indivisible unit; no PR is being created.
- [x] T2 — Add shared previews to dashboard and both device details.
  - Route: delegated; shared component/JS and three views are non-trivial changes.
  - Spanish states; responsive player; polling updates source only when content changes; same video must not restart on each poll; unavailable/offline states remove stale playback.
  - Checks: focused page rendering tests, frontend behavior checks, full PHPUnit suite, Pint on changed PHP, production build; browser verification if local environment supports it without remote operations.
  - Verification: `node --test tests/js/device-previews.test.mjs` passed (5 tests); full isolated `php artisan test` passed (20 tests, 108 assertions); Pint and `git diff --check` passed; `npm run build` passed (57 modules). Initial render fixture failures were fixed before the final green run.
  - Browser: local standalone Blade fixtures with built assets and simulated data showed dashboard cards, device filtering, missing-file/error retry, offline and missing-mapping states; refresh preserved the same source/error state, with no console warnings/errors. Corrected detail width after visual inspection and rebuilt; confirmed centering in browser. Fixtures and temporary server are ignored test artifacts; server and browser tab closed.
  - Commit: `13c4323840d531007f234b5f1e6f67f35b517379`. RDD: disabled/unmanaged. Slice 2 boundary: `82fff2c` → `13c4323`.
  - Authored implementation/tests/docs: 331 lines before tracking updates. Rollback scope: shared preview component/JS/CSS, its three view integrations and matching render/JS checks.
- [x] T3 — Reconcile media inventory and honor deletion failures.
  - Route: delegated; preparatory mapping covered services, web/API controllers, storage and tests (4+ files; multiple non-trivial writes).
  - Only a valid successful complete inventory can reconcile private-library records, including positively identified legacy paths. Preserve independent local/external media and metadata absent from the provider (especially duration).
  - Web/API deletion must preserve records when remote deletion fails; local public-storage files use the correct disk; batch failures are reported accurately.
  - Checks: valid-empty versus failed/malformed inventory, metadata preservation, legacy identity and storage isolation, failed/partial deletion; focused and full isolated PHPUnit, Pint, diff check.
  - Verified: focused media tests passed (26 tests, 98 assertions), full isolated PHPUnit passed (44 tests, 197 assertions), Pint check passed on 6 affected PHP files, and diff check passed. Parent inspected inventory identity/reconciliation and web/API/storage deletion paths.
  - Additional evidence: explicit reupload restores a deleted filename; metadata is preserved during sync; cloud identity is grouped once per record; thumbnail cleanup failure cannot retain a broken main record; legacy local API uploads are handled without probing a remote default disk.
  - Authored unit: 574 changed lines before tracking, a cohesive excess due to web/API failure, storage and reupload regression coverage. Commit/slice boundary: ready from `0e0adc8`. RDD disabled/unmanaged.
- [ ] T4 — Track accepted device removals until telemetry confirms them.
  - Route: delegated; playlist service/controller/view and regression tests require multiple non-trivial files.
  - An accepted deletion is pending, not confirmed. Keep pending filenames separate from the normal list, reject stale or optimistic snapshots as confirmation, and explain expiry if fresh telemetry still reports the video. Do not filter valid SD-only files against cloud library membership.
  - Checks: stale heartbeat, newer explicit playlist without file, request failure/malformed response, expiration, device/filename scoping and Spanish render state; isolated PHPUnit, Pint, diff check.
  - Commit/slice boundary: pending after T3. RDD disabled/unmanaged.
- [ ] T5 — Show library image/video frame previews and duration.
  - Route: delegated; shared UI, authenticated content source, media views and PHP/JS tests are multiple non-trivial files.
  - Serve known video sources through the authenticated same-origin endpoint; allow safe local raster images; keep device playback resolution video-only. Extract a visible video frame and browser metadata duration, preserve known metadata, and show loading/error states.
  - Checks: library/detail render, video metadata/frame behavior, safe image serving and authorization, unchanged device preview behavior; isolated PHP/JS suites, Pint, production build, local browser fixtures if available.
  - Commit/slice boundary: pending after T4. RDD disabled/unmanaged.

## Verification and progress
- Read-only discovery completed; documentation and code support preview of a known source file, not live capture.
- Baseline `npm run build`: PASS, 56 modules transformed.
- Baseline `php artisan test`: INTERRUPTED after Example and FleetUploadProgress tests passed. Pre-existing MediaDeleteTest lacked HTTP fakes and could attempt configured private-cloud DELETE calls. Session stopped with Ctrl+C (exit 1); no remote verification attempted. Add HTTP isolation to that existing test before running the full suite again.
- Running authored line count: 851 across both implementation commits (505 + 346, including tracking); this final tracking-only update adds 6 changed lines, totaling 857. Generated assets excluded. Local slice boundaries are recorded above; no PRs were created.
- Runtime/hardware: local HTTP fakes and browser fixtures verified; live hardware playback and remote deployment not exercised. Earlier baseline was interrupted and its unsafe existing test was isolated before the passing full suite. Engram remains unavailable/pending.
- Rollback boundary: new preview service/controller/routes/component and their integrations/tests/docs; preserve all prior unrelated changes.
- T1 additional evidence: ambiguous original filenames are rejected; both telemetry providers unavailable preserves saved devices with unavailable state. Local WL35 deletion/format paths already maintain mappings, but external reordering cannot be detected with index/count telemetry; documented this limitation.
- Remote delivery already observed: `0e0adc8` was pushed to the GitHub `origin` with explicit authorization using Git Credential Manager; remote hash matched local. No cloud/device remote session is authorized.
- Follow-up discovery: sync currently accepts incomplete inventories, overwrites duration with zero, and overlooks legacy cloud paths; deletion controllers ignore remote failure. Device heartbeat can reintroduce optimistically removed playlist entries. Media views use direct cloud URLs and do not extract video frames.
- Next step: commit T3 and implement T4, then T5. Engram mirror remains pending/unavailable.
