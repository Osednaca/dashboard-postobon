# Devices UI refresh

## Objective, authorization, and scope
User explicitly requested on 2026-10-14 that `/devices` follow the attached desktop/mobile reference, adapting controls to existing supported operations (including Bluetooth power and SD formatting). The route belongs to `dashboard-postobon`, not the adjacent gateway workspace. Improve the existing Laravel/Blade screen without firmware, protocol, remote operations, or fabricated controls/data.

Problem: the current nine-column table requires horizontal scrolling and exposes a dense collection of actions. Preserve real device state, pagination, filtering, selection, bulk playback/upload/group/location operations, permissions, confirmations and asynchronous command semantics.

Design: existing Postobon palette and shell; compact counters, aligned search/filters, readable device/status/establishment/connection/content fields, clearly labelled icon actions with tooltips, secondary More menu, compact mobile list and accessible mobile action sheet. Display only reliably known content, supported operations and real counts; do not copy invented sample devices or reference-only controls.

## Workflow and constraints
- Route: delegated direct. Mapping required more than four files; implementation spans non-trivial Blade/interaction styling plus behavior checks. Parent mapped through `explore_devices`; one bounded writer owns implementation.
- TDD: OFF; existing explicit project user decision recorded in `odd/tasks/device-content-preview.md`, Workflow configuration (2026-10-10). Runner `php artisan test`, with isolated SQLite memory and fake external services. Ordinary functional checks remain required.
- RDD: disabled/unmanaged; freshly observed `gentle-ai review mode status` OFF, global. No native review.
- Delivery strategy: ask-on-risk; retain user-selected feature-branch-chain recorded in the existing feature document. Local work units only; no push, PR, merge or deployment requested.
- Current feature branch: `codex/device-content-preview`; starting boundary `5e41c98d48e22922bfca164b9d0fad8a07d5ae35`. Preserve the existing branch and unrelated changes to `docs/DESPLIEGUE_LATENCIA.md` and `private-cloud`.
- Forecast: 650–1100 authored additions plus deletions, one cohesive responsive screen unit with regression checks. The approximately 400-line heuristic is advisory; never compress code, omit tests or split artificially. Record actual count and cohesive overage at closure.
- Engram mirror: PENDING/unavailable, no memory tools exposed. Intended project `dashboard-postobon`, topic `odd/devices-ui-refresh/tasks`; mirror this complete document and repository-relative locator when available.

## Task and acceptance
- [x] T1 — Adapt the devices screen and actions to the provided responsive reference.
  - Route: delegated; mapping/preparation and multiple non-trivial files trigger delegation.
  - Acceptance: readable desktop table; compact mobile layout without page-level horizontal scroll; search, type/status/establishment filters and reset/empty states work; existing selection/bulk controls retained; device actions reach the same supported handlers with accurate Bluetooth labels; destructive confirmations retained; keyboard focus, Escape/backdrop dismissal and visible menu labels; no fabricated current content or reference-only operations.
  - Checks: focused render/interaction regressions, full isolated PHP suite, existing JS tests where applicable, production asset build, relevant formatting and diff checks; local rendered fixtures at desktop/mobile with browser interaction if available. Never contact live hardware or reuse production sessions.
  - Rollback: only this screen's presentation/interaction changes and associated regression checks/docs; retain prior device APIs and unrelated work.
  - Commit/slice: `2569cc7` — `feat(devices): adapt responsive device list and action menus`; boundary `5e41c98` → `2569cc7`, 661 authored additions plus deletions including tracking (623 implementation/tests). RDD disabled/unmanaged. One cohesive responsive-screen work unit; local slice only, no PR.

## Progress and next step
T1 completed and committed; outcome and applicable checks observed by writer and parent.

- Replaced the nine-column layout with seven combined columns and one shared set of rows that becomes a compact list below 1280 px. Added actual status capsules, page-scoped counters, clear search/filter labels and no-match recovery. Mobile filters expand on demand; search and active filter count remain visible.
- Added per-device play/power/Bluetooth shortcuts and a labelled secondary menu that becomes a bottom sheet on small screens. Existing Z2 endpoints, WL35 commands, bulk group/location/media operations, upload/format progress and destructive confirmations remain. No brightness, Wi-Fi, pairing, restart or invented content mappings were added.
- Playback now snapshots its own targets; single-device playback leaves the bulk selection intact. Filter counts remain reactive after Alpine object composition. Menu/modal focus waits for the visible render frame; Escape, Tab boundaries, backdrop dismissal and return focus are handled.
- Observed checks: isolated full `php artisan test --compact` passed 150 tests / 831 assertions before final presentational refinements; final focused `DeviceListLayoutTest` passed 2 tests / 35 assertions (including live-vs-local state and disconnected WL35 handling). Full JS suite passed 32 tests, including filtering, selection isolation and focus timing. Production `npm run build`, targeted Pint and `git diff --check` passed. No failed check remains; CRLF normalization warnings are informational.
- Parent browser checks on local fixture: no page overflow at 320/390 px including bulk controls and modal; search counters, empty/reset, combined type/status, filtered select-all and preserved bulk selection during individual playback observed. Initial focus verified on the second row menu and SD confirmation; no destructive submit performed. Desktop at 1536 and 1280 px passed without overflow; content columns measured 184 px / 92 px. Mobile 390 px first row moved from about 780 px to 546 px after compact counters and filter disclosure. Browser console warnings/errors were empty. Screenshots saved in the ignored fixture directory as `devices-desktop.jpg` and `devices-mobile.jpg`.
- Local visual fixture: `storage/framework/testing/device-list-visual/render.php`, static `server.cjs`, URL `http://127.0.0.1:8766/devices`; generated files remain ignored. Renderer uses isolated in-memory SQLite and fake HTTP; server rejects mutations and serves only local assets. No live index request or device operation used.
- Current authored implementation/test count: approximately 623 additions plus deletions, excluding the tracking document and ignored generated assets. One cohesive screen unit keeps shared selection, menus, responsive rows and checks together; no artificial split. Retained feature-branch-chain strategy, no remote delivery.
- Browser tab closed and viewport override reset after verification. Initial focus timing and reactive-counter defects found during local verification were corrected and rechecked; no unresolved functional failure remains. Hardware execution and production deployment were intentionally not performed.
- Local preview server stopped. Engram full-document mirror remains pending/unavailable. Next: user can review the local change and decide production delivery; no implementation work remains.
