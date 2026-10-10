# Device content preview

## Objective and authorization
Show the locally available video corresponding to the content a holographic fan reports as current on `/dashboard`, Z2 detail, and WL35 detail. The user explicitly authorized implementation on 2026-10-10; frame synchronization and live capture are unnecessary.

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
- Engram mirror: PENDING/unavailable; no Engram tools exposed. Intended project `dashboard-postobon`, topic `odd/device-content-preview/tasks`. Mirror full document and locator when available.

## Tasks and acceptance
- [x] T1 — Resolve reported content and serve its available video through authorized read endpoints.
  - Route: delegated; preparation + 2+ non-trivial source/test files.
  - Exact Z2 filename mapping and device-specific WL35 index mapping; batch dashboard resolution; no desired-content fallback.
  - Auth/policy checks, safe fixed-source media reads, Range handling, no credential exposure or mutating external calls.
  - Checks: focused PHPUnit cases for matching, unavailable states, auth, source validation, local/private bytes and Range. Full suite at closure.
  - Verification: focused tests passed, then full isolated `php artisan test` passed (19 tests, 93 assertions); Pint check passed on 7 changed PHP files; `git diff --check` passed. Parent inspected resolver, source validation, controllers, tests and documentation.
  - Commit: ready to create after observed checks. RDD: disabled/unmanaged. Slice 1 boundary: branch point → T1 commit.
  - Authored implementation/tests/docs: 462 lines before tracking document. Cohesive resolver + authenticated Range delivery + meaningful source/auth tests exceed the advisory task target; retain complete tests and formatting. A future PR may require a size exception for this indivisible unit; no PR is being created.
- [ ] T2 — Add shared previews to dashboard and both device details.
  - Route: delegated; shared component/JS and three views are non-trivial changes.
  - Spanish states; responsive player; polling updates source only when content changes; same video must not restart on each poll; unavailable/offline states remove stale playback.
  - Checks: focused page rendering tests, frontend behavior checks, full PHPUnit suite, Pint on changed PHP, production build; browser verification if local environment supports it without remote operations.
  - Commit: pending. RDD: disabled/unmanaged. Slice 2 boundary: T1 commit → T2 commit.

## Verification and progress
- Read-only discovery completed; documentation and code support preview of a known source file, not live capture.
- Baseline `npm run build`: PASS, 56 modules transformed.
- Baseline `php artisan test`: INTERRUPTED after Example and FleetUploadProgress tests passed. Pre-existing MediaDeleteTest lacked HTTP fakes and could attempt configured private-cloud DELETE calls. Session stopped with Ctrl+C (exit 1); no remote verification attempted. Add HTTP isolation to that existing test before running the full suite again.
- Running authored line count from work-unit commits: 0.
- Runtime/hardware: no remote device checks authorized; use local HTTP fakes and local UI checks.
- Rollback boundary: new preview service/controller/routes/component and their integrations/tests/docs; preserve all prior unrelated changes.
- T1 additional evidence: ambiguous original filenames are rejected; both telemetry providers unavailable preserves saved devices with unavailable state. Local WL35 deletion/format paths already maintain mappings, but external reordering cannot be detected with index/count telemetry; documented this limitation.
- Next step: commit T1, record identity, then implement and verify T2. Parent and writer must read this file before source edits.
