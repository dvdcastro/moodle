# Changelog

## v2.0.0 — 2026-05-21

Second stable release. Adds manage-page lifecycle, detail modal, monitoring,
and a full WCAG 2.1 AA audit pass.

### Added — Manage page (MLFR-163, 164)
- `manage.php` admin page with `table_sql`-backed grid of added content
- Per-row actions: View course, Re-sync (queues `resync_content_task`), Remove
- New capability `local/tituscontentlibrary:manage`
- `resync_content_task` adhoc task — re-downloads SCORM, replaces package in
  existing course (enrolments and grades preserved, no course deletion)
- 2 new external WS functions: `resync_course`, extended `get_add_status`

### Added — Detail modal (MLFR-165)
- `detail_modal.mustache` template + `detail_modal.js` AMD module
- "View details" trigger on every tile opens a Bootstrap modal with full
  description, tags, badges, and a wired Add button
- Focus returns to the originating trigger on close (WCAG 2.4.3)

### Added — Catalogue sort (MLFR-162)
- 7 sort options: Title A–Z / Z–A, Newest, Duration, Category,
  Featured first, New first
- Selection persisted per user via `core_user_set_user_preferences`
- PHP `catalogue_sort` mirror used on initial server-rendered page

### Added — Content badges (MLFR-166)
- `New` and `Featured` badges on tiles and in the detail modal
- Driven by `is_new` / `is_featured` DTO fields

### Added — Primary navigation hook (MLFR-167)
- `extend_primary_nav` hook callback adds a "Titus Content Library" primary
  nav entry visible to users with the `:view` capability

### Added — Monitoring (MLFR-173)
- `monitoring` class tracks consecutive success / failure streaks in plugin
  config (no DB schema change)
- New `messageprovider:refreshfailure` notification — fires when the failure
  streak reaches the admin-configurable `failurestreakthreshold` (default 3)
- API Health badge on the settings page

### Added — Test infrastructure (MLFR-171)
- `client_factory` centralises API client injection for all tasks and managers
- `tests/fixtures/mock_titus_api_client.php` — fluent, call-tracking test double

### Changed — Security hardening (MLFR-172)
- `log_sanitizer` masks the licence key in all error/log strings
- `add_content_task`: 429 rate-limit responses reset the row to PENDING and
  reschedule the task (instead of marking as FAILED)
- `add_content_task`: error messages stored without stack traces
- `titus_api_client::download_to()` enforces HTTPS scheme on the download URL

### Changed — Accessibility (MLFR-170)
- `category_pills.mustache`: drop `role="tab"` — WAI-ARIA Toggle button pattern
  (`aria-pressed`) is the correct pattern for filtering in place (not tab switching)
- `DEVELOPER.md` (new): documents all a11y decisions, Behat/PHPUnit workflows,
  AMD/SCSS conventions, and test client injection pattern

### Tests
- PHPUnit: 125 tests / 313 assertions (was 35 / 109 in v1.0.0)
- Behat: 12 scenarios (was 10) — adds keyboard-activation and aria-live
  announcer coverage

---

## v1.0.0 — 2026-05-20

Initial stable release. Full V1 feature set:

### Backend
- `titus_api_client` — HTTP client for the Titus Catalogue API (SSRF-safe, encrypted licence key, 7 typed exception classes)
- `catalogue_manager` — MUC-cached catalogue with degraded mode (stale data on API failure)
- `add_content_task` — async adhoc task: downloads SCORM ZIP, validates, creates Moodle course + SCORM activity
- `refresh_catalogue_task` — hourly scheduled task to keep catalogue cache warm
- 3 external WS functions: `add_course`, `get_catalogue`, `get_add_status`
- Privacy provider: user data export/deletion for `local_tituscontentlibrary_added` table
- 3 capabilities: `view`, `addcontent`, `manageintegration`

### Frontend
- 4 Mustache templates: `marketplace`, `tile`, `category_pills`, `empty_state`
- 6 AMD modules: `repository`, `marketplace`, `tile`, `search`, `category_filter`, `selectors`
- 6-state tile FSM (IDLE → QUEUEING → QUEUED → PROCESSING → COMPLETED | FAILED)
- Exponential backoff polling (3/3/5/8/13/21/30s cap), 60s client-side timeout
- Client-side AND search+category filter (300ms debounce)
- WCAG 2.1 AA: `aria-live` announcements, `role=tab` pills, `aria-label` on add buttons
- Titus pink (#F40067) SCSS, 5-column responsive grid, skeleton shimmer, `prefers-reduced-motion`

### Tests
- PHPUnit: 35 tests, 109 assertions
- Behat: 10 scenarios (3 non-JS + 7 JS), 93 steps — all green
