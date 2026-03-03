# Implementation Tasks: Run Scraper From WP Admin

**Branch**: `001-wp-admin-scraper`
**Plan**: [plan.md](plan.md)
**Status**: Completed

### Implementation Strategy

- MVP Focus: Ensure we can gracefully invoke a single URL scrape via AJAX and stream output back.
- Incremental Delivery: First, build the environment validation UI to handle missing dependencies gracefully. Second, implement single URL tracking. Finally, extend the UI and endpoints for Year/Month batching.

## Phase 1: Setup

- [x] T001 Create `src/Admin/ScraperSettingsPage.php` with basic hooks for `admin_menu` to register a new admin page under 'Taste of Cinema Settings'.
- [x] T002 Create `src/Admin/ScraperAjaxHandler.php` with basic WordPress AJAX hooks setup.
- [x] T003 Create `src/Core/ScraperRunner.php` class stub for encapsulating shell execution logic.
- [x] T004 Create empty files `assets/admin/js/scraper-admin.js` and `assets/admin/css/scraper-admin.css`.
- [x] T005 Wire the newly created `ScraperSettingsPage`, `ScraperAjaxHandler` and `ScraperRunner` classes defensively in the root plugin file `tasteofcinemascraped.php`.

## Phase 2: Foundational

Goal: Implement the core Python `.venv` verification system to adhere to the fail-fast rule and create the raw UI container.

- [x] T006 Implement `checkEnvironment()` cacheable method inside `src/Core/ScraperRunner.php` to verify the `.venv` directory and required python/pip binaries.
- [x] T007 Implement the `toc_validate_env` AJAX handler inside `src/Admin/ScraperAjaxHandler.php` mapping to the `ScraperRunner` check.
- [x] T008 [P] Render the base HTML forms in `src/Admin/ScraperSettingsPage.php` containing inputs for Single URL, Year, Month, action buttons, and a `<pre id="toc-scraper-logs">` area for logs.
- [x] T009 [P] Implement `assets/admin/css/scraper-admin.css` to visually lay out the form cards and style the terminal-like output window.
- [x] T010 Implement the initialization sequence in `assets/admin/js/scraper-admin.js` to hit `toc_validate_env` on page load and lock/disable UI forms if validation fails.

## Phase 3: User Story 1 (Scrape by Specific URL)

Goal: Allow an Administrator to input a specific article URL, start the python scraper asynchronously, and watch the output logs update in real-time.

**Independent Test**: Entering a URL and triggering the scrap starts the UI polling loop; UI reveals progress lines dynamically; backend spawns `.venv` process without blocking PHP.

- [x] T011 [US1] Implement `start(array $args)` method in `src/Core/ScraperRunner.php` using `proc_open` to execute the Python script in the background (`> /tmp/toc_run_xyz.log 2>&1 &`) and return a `run_id`.
- [x] T012 [US1] Implement `poll(string $run_id)` method in `src/Core/ScraperRunner.php` to read the specific `/tmp/log` file contents and determine if the process is still running.
- [x] T013 [US1] Implement `toc_start_scraper` AJAX handler inside `src/Admin/ScraperAjaxHandler.php` parsing single URL parameter.
- [x] T014 [US1] Implement `toc_poll_progress` AJAX handler inside `src/Admin/ScraperAjaxHandler.php` delegating to `poll()` and returning log lines.
- [x] T015 [P] [US1] Attach "Run Scraper" button click action in `assets/admin/js/scraper-admin.js` to call `toc_start_scraper` API logic.
- [x] T016 [P] [US1] Implement recursive polling function `pollScraperProgress(run_id)` in `assets/admin/js/scraper-admin.js` that loops hitting `toc_poll_progress` and appending new text until `status == 'completed'`.

## Phase 4: User Story 2 (Scrape by Year/Month)

Goal: Allow an Administrator to select a specific year (and optional month) to trigger the scraper for batch importing over time.

**Independent Test**: Provide Year 2014 and Month 02; UI triggers execution accurately passing the year/month flags natively to python.

- [x] T017 [US2] Update `start()` in `src/Core/ScraperRunner.php` to detect `year` and `month` input arguments, adjust the CLI flag generation (handling either URL or Year arguments mutually exclusively).
- [x] T018 [US2] Update `toc_start_scraper` in `src/Admin/ScraperAjaxHandler.php` to accept standard Year/Month parameters.
- [x] T019 [P] [US2] Attach "Import Batch" button click action in `assets/admin/js/scraper-admin.js` to dispatch form state to `toc_start_scraper` and trigger the polling mechanism.
- [x] T020 [P] [US2] Expand UI logic in `assets/admin/js/scraper-admin.js` to clear previous logs on new execution and visually switch UI into a "Running/Locked" state.

## Phase 5: Polish

- [x] T021 Implement process cleanup in `src/Core/ScraperRunner.php` to delete temporary `/tmp` log files once the async execution is finalized and pulled successfully by the UI.
- [x] T022 Ensure Nonce checks exist on all three `wp_ajax_` actions in `src/Admin/ScraperAjaxHandler.php` and verified before acting.
- [x] T023 Implement user-facing feedback errors (e.g. Toast messages or warnings) in `assets/admin/js/scraper-admin.js` for execution timeouts mapping back to Python failure outputs.

=== DEPENDENCIES ===
Phase 1 (Setup) -> Phase 2 (Foundational) -> Phase 3 (US1) -> Phase 4 (US2) -> Phase 5 (Polish)

Parallel Opportunities: JS and CSS styling tasks in Phase 2 can be developed independently of the backend AJAX endpoints. UI binding tasks inside US1/US2 can happen simultaneously with `ScraperRunner.php` underlying mechanics as the AJAX contracts `ajax.md` are documented.
