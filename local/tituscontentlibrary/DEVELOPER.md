# Developer Notes — local_tituscontentlibrary

This document records the architectural and accessibility decisions for the
Titus Content Library plugin. It is a companion to `README.md` and `CHANGELOG.md`.

---

## Accessibility decisions (WCAG 2.1 AA)

### 1. Live region announcer (`data-region="titus-announcer"`)

The marketplace page renders a single visually-hidden status region:

```html
<div class="sr-only" role="status" aria-live="polite" data-region="titus-announcer"></div>
```

- `role="status"` is an implicit `aria-live="polite"` landmark, but we set both
  explicitly so screen readers that ignore implicit roles still announce.
- `aria-live="polite"` (not `assertive`) — add/queue events are not urgent and
  must not interrupt other speech.
- The `marketplace.js` `announce()` helper writes `''` first and then writes the
  message after a 50 ms timeout. This forces AT (e.g. NVDA, VoiceOver) to re-read
  identical consecutive messages — they otherwise deduplicate.
- All FSM transitions (`announce:queueing`, `announce:queued`, `announce:processing`,
  `announce:completed`, `announce:failed`) and the result-count update
  (`announce:results`) write through this region.

### 2. `data-action` delegation instead of inline handlers

The marketplace, tile, manage, and detail-modal modules attach a **single** `click`
listener at the page root and use `e.target.closest('[data-action="..."]')` to
dispatch. We never inline `onclick`. Reasons:

- **CSP-safe** — Moodle's strict CSP forbids inline event handlers.
- **No re-binding cost** after `Templates.appendNodeContents()` re-renders tiles.
- **Keyboard activation for free** — native `<button>` elements fire `click` on
  `Enter` and `Space`, so keyboard users get the same code path as mouse users.
- **Centralised selectors** — `amd/src/selectors.js` is the single source of truth
  for all `[data-action]` and `[data-region]` strings; Behat steps can reference
  them without coupling to CSS class names.

### 3. WCAG 2.5.5 — Target size (44×44 px)

`styles.css` enforces `min-height: 44px; min-width: 44px` on `.titus-add-btn`,
`.titus-category-btn`, and `.titus-search-clear`. The 44 px threshold is the
WCAG 2.5.5 Level AAA recommendation; 24 px is the Level AA minimum (WCAG 2.2).
We adopt the stricter target because the marketplace is heavily touch-driven on
tablets used by managers in the field.

### 4. Focus management

- The detail modal is created with `returnElement: triggerElement` — Moodle's
  `core/modal` restores focus to the originating "View details" button when the
  modal is dismissed (WCAG 2.4.3, 2.4.7).
- The marketplace root carries `role="main" aria-labelledby="titus-marketplace-heading"`
  to provide a stable landmark target for skip-links provided by the theme.
- No custom skip-link is added at plugin level — themes own page-level skip
  navigation; the explicit `role="main"` is sufficient for assistive navigation.

### 5. Category pills — toggle pattern, not tabs

Pills filter the existing grid in place; they do not switch between distinct panels.
Per WAI-ARIA APG we use `<button aria-pressed="true|false">` (Toggle button pattern)
rather than `role="tab"` + `role="tablist"`. The surrounding `<nav aria-label="Filter
by category">` provides the group label.

`role="tab"` was present in v1.0.0 (`category_pills.mustache`) and removed in v2.0.0
because `aria-pressed` is not a valid state for `role=tab` (axe-core ARIA_REQUIRED_ATTR
violation), and the semantic pattern is wrong — tabs switch panels, these filter a list.

### 6. Reduced motion

`styles.css` includes a `@media (prefers-reduced-motion: reduce)` block that disables
the skeleton shimmer animation and all transitions. Tested with the "Reduce motion"
toggle in macOS / iOS / GNOME settings.

### 7. Colour contrast

Titus pink `#F40067` on white = 4.55:1 (AA pass for non-text UI and large text).
The `:focus` outline uses the same pink at 2 px with 2 px offset, exceeding the
3:1 non-text contrast requirement.

---

## Running PHPUnit tests

```bash
# Initialise (only required after DB schema changes or a version bump).
./run-docker-exec.sh php admin/tool/phpunit/cli/init.php

# Run the full plugin test suite.
./run-docker-exec.sh vendor/bin/phpunit --filter local_tituscontentlibrary

# Run a single test class.
./run-docker-exec.sh vendor/bin/phpunit --filter catalogue_manager_test
```

Current totals (v2.0.0): **125 tests / 313 assertions**.

---

## Running Behat tests

```bash
# Initialise (only required when adding/removing behat_*.php step files).
./run-docker-exec.sh php admin/tool/behat/cli/init.php

# Run all scenarios tagged for this plugin.
./run-docker-exec.sh vendor/bin/behat \
    --config /var/www/html/moodledata/behat/behat.yml \
    --tags=@local_tituscontentlibrary

# Run a single scenario by file:line.
./run-docker-exec.sh vendor/bin/behat \
    --config /var/www/html/moodledata/behat/behat.yml \
    /var/www/html/local/tituscontentlibrary/tests/behat/marketplace.feature:49
```

Scenarios tagged `@javascript` require a running Selenium / Playwright session.
See Moodle's [Acceptance testing](https://moodledev.io/general/development/tools/behat)
documentation for browser driver configuration.

Current totals (v2.0.0): **12 scenarios** (10 non-JS → JS, 2 new a11y JS scenarios).

---

## AMD module conventions

- Compile with `npx grunt amd --root=local/tituscontentlibrary`.  
  **Never run Grunt globally** — it would process the entire Moodle codebase.
- Public exports follow the `init(rootSelector)` pattern; `marketplace.js` is the
  only module loaded directly from PHP (via `$PAGE->requires->js_call_amd()`).
- All DOM lookups go through `amd/src/selectors.js`. Do not hard-code CSS
  class names or data-attribute strings in feature modules.
- Template / string prefetch happens in `marketplace.js init()` to avoid request
  waterfalls on first user interaction.

---

## SCSS compilation

Moodle's Grunt `scss` target only processes theme SCSS. To rebuild the plugin
stylesheet from `scss/styles.scss`:

```bash
node -e "
const sass = require('sass');
const r = sass.compile('scss/styles.scss');
require('fs').writeFileSync('styles.css', r.css);
"
```

Run this from the plugin root (`local/tituscontentlibrary/`).

---

## Test client injection pattern

Unit tests inject a mock API client via `client_factory::set_test_client($mock)`
(or the backward-compatible `add_content_task::set_client_for_testing($mock)`).
Always call `client_factory::reset()` in `tearDown()` to avoid cross-test
contamination, since the factory uses a static field.

A fluent fixture class is provided at `tests/fixtures/mock_titus_api_client.php`.
It is **not autoloaded** — tests must `require_once` it explicitly:

```php
global $CFG;
require_once($CFG->dirroot . '/local/tituscontentlibrary/tests/fixtures/mock_titus_api_client.php');
```
