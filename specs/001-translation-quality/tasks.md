# Tasks: Certified Cinematic Translation Quality Engine

**Input**: Design documents from `/specs/001-translation-quality/`
**Prerequisites**: plan.md ✅ spec.md ✅ research.md ✅ data-model.md ✅ contracts/rest-api.md ✅ quickstart.md ✅

**Organization**: Tasks are grouped by user story to enable independent implementation and testing of each story. No test tasks generated (not requested in spec).

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies on incomplete tasks)
- **[Story]**: Which user story this task belongs to (US1, US2, US3)
- Exact file paths included in all descriptions

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Plugin extension scaffolding, vendor dependency, and activation hooks

- [x] T001 Add `Action Scheduler` as a Composer dependency and commit `composer.json` + `composer.lock` at `/home/basem/sites/tasteofcinemaarabi/wp-content/plugins/tasteofcinemascraped-wp/composer.json` (require `woocommerce/action-scheduler:^3.6`; run `composer install`)
- [x] T002 Add `require_once` for Composer autoloader and Action Scheduler bootstrap in `tasteofcinemascraped-wp.php` (after `ABSPATH` guard, before any hook registrations)
- [x] T003 [P] Create `includes/` directory and add empty `class-toc-quality-db.php`, `class-toc-quality-engine.php`, `class-toc-quality-scheduler.php`, `class-toc-quality-rest.php`, `class-toc-quality-admin.php` stub files (each with `<?php declare(strict_types=1);` + empty class declaration)
- [x] T004 [P] Create `templates/` directory and add empty `templates/review-queue.php` stub file

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Core infrastructure that MUST be complete before ANY user story can be implemented

**⚠️ CRITICAL**: No user story work can begin until this phase is complete

- [x] T005 Implement `TOC_Quality_DB::install_schema()` in `includes/class-toc-quality-db.php` — create both custom tables (`{prefix}toc_translation_jobs` and `{prefix}toc_audit_log`) using `dbDelta()` with all columns, types, constraints, and indexes exactly as specified in `specs/001-translation-quality/data-model.md`; method must be idempotent (safe to re-run)
- [x] T006 Register plugin activation hook in `tasteofcinemascraped-wp.php` calling `TOC_Quality_DB::install_schema()` via `register_activation_hook( __FILE__, ... )`
- [x] T007 [P] Implement `TOC_Quality_DB::create_job( int $post_id, string $content_type, int $user_id ): int` in `includes/class-toc-quality-db.php` — validates `content_type` against the four allowed values (defaults to `editorial`), computes word count using `mb_str_word_count( wp_strip_all_tags( get_post_field( 'post_content', $post_id ) ) )`, computes `input_hash` via `hash('sha256', ...)`, inserts row with status `pending`, appends `job_created` event to `toc_audit_log`, returns new job ID
- [x] T008 [P] Implement `TOC_Quality_DB::get_job( int $job_id ): ?array` and `TOC_Quality_DB::get_jobs_for_post( int $post_id ): array` in `includes/class-toc-quality-db.php` — raw DB reads; `get_job` returns null if not found; both enforce per-user visibility (non-`manage_options` callers only see their own jobs via `initiating_user_id` filter)
- [x] T009 Implement `TOC_Quality_DB::transition_status( int $job_id, string $new_status, array $data = [] ): bool` in `includes/class-toc-quality-db.php` — enforces the valid transition map from `data-model.md` (rejects invalid transitions), updates `status` + `updated_at` on the job row, appends audit log event with `previous_status`, `new_status`, and optional `metadata` JSON; returns false and logs a warning on invalid transition
- [x] T010 [P] Implement `TOC_Quality_DB::get_audit_log( array $filters ): array` in `includes/class-toc-quality-db.php` — supports all query params from `contracts/rest-api.md` Endpoint 4 (`post_id`, `status`, `content_type`, `from`, `to`, `min_score`, `max_score`, `per_page`, `page`); enforces `manage_options` vs own-only visibility
- [x] T011 [P] Implement `TOC_Quality_DB::schedule_audit_purge()` and `TOC_Quality_DB::run_audit_purge()` in `includes/class-toc-quality-db.php` — register `toc_quality_audit_purge` WP-Cron event (daily); `run_audit_purge()` deletes `toc_audit_log` rows older than `toc_quality_audit_retention_days` site option (default 90)
- [x] T012 Implement quality settings helpers in `includes/class-toc-quality-db.php`: `get_threshold(): int`, `get_model(): string`, `get_auto_run_on_import(): bool`, `update_settings( array $settings ): void` — reads/writes `toc_quality_threshold`, `toc_quality_model`, `toc_quality_audit_retention_days`, `toc_quality_auto_run_on_import` site options; `update_settings` requires `manage_options` capability check
- [x] T013 Register all REST routes in `tasteofcinemascraped-wp.php` by calling `TOC_Quality_REST::register_routes()` inside a new `rest_api_init` action hook; register admin menu pages by calling `TOC_Quality_Admin::register_menus()` inside a new `admin_menu` hook; register the Action Scheduler action `toc_process_quality_job` by calling `TOC_Quality_Scheduler::register_hooks()`

**Checkpoint**: DB schema installs cleanly, jobs can be created/read/transitioned, settings helpers work → user story implementation can now begin

---

## Phase 3: User Story 1 — Editorial Review of a Scraped Translation (Priority: P1) 🎯 MVP

**Goal**: An editor submits any stored article to the Quality Engine and receives a polished Arabic version with a structured change manifest. Articles ≤2,000 words resolve synchronously within 30 seconds; articles 2,001–5,000 words are queued and the editor is notified; articles >5,000 words are immediately rejected.

**Independent Test**: Submit post ID 42 (≤2,000 words) via `POST /wp-json/tasteofcinemascraped/v1/quality/run` with `{"post_id":42,"content_type":"review"}`; assert response `201` with `status: auto-approved`, `pre_score` an integer 0–100, `post_score ≥ pre_score`, and a non-empty `change_manifest` array. Then submit a >5,000-word post; assert `400 toc_word_count_exceeded`.

### Implementation for User Story 1

- [x] T014 [P] [US1] Implement `TOC_Quality_Engine::assess( string $text, string $content_type ): array` in `includes/class-toc-quality-engine.php` — builds the assessment system prompt with the 4-dimension rubric (literal 30%, cultural 30%, register 20%, fluency 20%%) and content-type context; calls OpenRouter via `wp_remote_post()` with `temperature: 0` and `response_format: {"type":"json_object"}`; parses and returns `['pre_score' => int, 'dimension_scores' => array, 'issues' => array]`; uses `get_option('toc_quality_model')` or `OPENROUTER_QUALITY_MODEL` env var, falling back to `OPENROUTER_MODEL`; throws `TOC_Quality_Exception` on API failure
- [x] T015 [P] [US1] Implement `TOC_Quality_Engine::extract_protected_terms( string $text ): array` in `includes/class-toc-quality-engine.php` — ports the `_extract_movie_names_and_years()` heuristic from `tasteofcinemascraped/translator.py` to PHP; returns array of string tokens that must not be altered by the rewrite step
- [x] T016 [US1] Implement `TOC_Quality_Engine::rewrite( string $text, string $content_type, array $issues, array $protected_terms ): array` in `includes/class-toc-quality-engine.php` — builds rewrite prompt injecting the identified issues and protected-terms list; calls OpenRouter (`temperature: 0`); parses returned JSON into `['post_score' => int, 'revised_text' => string, 'change_manifest' => array]`; validates each change record has `id`, `original`, `revised`, `category` (one of four canonical values), `rationale` fields; depends on T014, T015
- [x] T017 [US1] Implement `TOC_Quality_Engine::process_job( int $job_id ): void` in `includes/class-toc-quality-engine.php` — orchestrates the full two-call flow: (1) load job from DB, set status `processing`; (2) call `assess()`; (3) if `pre_score ≥ 85` skip rewrite and set status `auto-approved` with no changes; (4) else call `rewrite()`; (5) save `pre_score`, `post_score`, `change_manifest`, `model_version`, `change_count` to job row; (6) compare `post_score` to `TOC_Quality_DB::get_threshold()` → set status `auto-approved` or `flagged-for-review`; (7) if `auto-approved`, update the WordPress post content to `revised_text`; (8) on any caught exception, call `TOC_Quality_DB::transition_status( $job_id, 'engine-unavailable' )` and leave post as draft; depends on T013–T016
- [x] T018 [US1] Implement `TOC_Quality_Scheduler::enqueue_async_job( int $job_id ): void` and `TOC_Quality_Scheduler::handle_async_job( int $job_id ): void` in `includes/class-toc-quality-scheduler.php` — `enqueue_async_job` calls `as_schedule_single_action( time(), 'toc_process_quality_job', ['job_id' => $job_id] )`; `handle_async_job` calls `TOC_Quality_Engine::process_job( $job_id )`; `register_hooks()` adds `add_action( 'toc_process_quality_job', [self::class, 'handle_async_job'] )`; depends on T017
- [x] T019 [US1] Implement `TOC_Quality_REST::register_routes()` in `includes/class-toc-quality-rest.php` — registers the `POST /quality/run` endpoint (Endpoint 1 from `contracts/rest-api.md`): validates `post_id` exists, checks `edit_posts` capability, counts words, rejects >5,000 with `400 toc_word_count_exceeded`, creates job via `TOC_Quality_DB::create_job()`, then either processes synchronously (≤2,000 words via `TOC_Quality_Engine::process_job()`) or enqueues async (>2,000 via `TOC_Quality_Scheduler::enqueue_async_job()`) and returns `202`; registers `GET /quality/jobs/{id}` endpoint (Endpoint 2): loads job, enforces own-vs-admin visibility, returns full job array; depends on T017, T018
- [x] T020 [US1] Wire auto-run-on-import hook in `tasteofcinemascraped-wp.php`: add `add_action( 'tasteofcinemascraped_post_imported', 'toc_quality_auto_run', 10, 2 )` and implement `toc_quality_auto_run( int $post_id, string $content_type = 'editorial' )` — checks `TOC_Quality_DB::get_auto_run_on_import()` site option; if true, creates a job and enqueues async processing regardless of word count (import path always async to avoid blocking the import REST response); dispatch the `tasteofcinemascraped_post_imported` action from within `tasteofcinemascraped_import_callback()` after successful post creation; depends on T018

**Checkpoint**: An editor can POST to `/quality/run` for any article; ≤2,000-word articles return a corrected version inline within 30 s; larger articles return 202 and are processed in background; >5,000-word articles are rejected with 400; engine failures leave the article as draft with `engine-unavailable` status.

---

## Phase 4: User Story 2 — Cinematic Tone Calibration by Content Type (Priority: P2)

**Goal**: When a `content_type` hint (`synopsis`, `dialogue`, `review`, `editorial`) is supplied to the engine, the output is calibrated to the correct cinematic register. Without a hint, a neutral editorial default is applied and a note is included in the change manifest.

**Independent Test**: Submit a synopsis with `content_type: synopsis`; verify the change manifest includes no register-mismatch ChangeRecords and the `revised_text` uses formal narrative Arabic. Submit a dialogue excerpt with `content_type: dialogue`; verify the output avoids over-formal constructs. Submit with no `content_type`; verify response includes a ChangeRecord with `category: tonal` and `rationale` referencing the missing hint.

### Implementation for User Story 2

- [x] T021 [US2] Extend `TOC_Quality_Engine::assess()` and `TOC_Quality_Engine::rewrite()` prompt builders in `includes/class-toc-quality-engine.php` to embed content-type-specific register instructions in the system prompt — define a `REGISTER_INSTRUCTIONS` map: `synopsis` → "فصيح سردي رسمي مناسب للنقد السينمائي المنشور", `dialogue` → "لهجة حوارية طبيعية تناسب شخصية المتحدث الثقافية دون تكلف", `review` → "أسلوب نقدي أكاديمي رصين", `editorial` → "محرري محايد سليم"; inject selected instruction into both prompt templates; depends on T014, T016
- [x] T022 [US2] Add "missing content-type" ChangeRecord injection in `TOC_Quality_Engine::process_job()` in `includes/class-toc-quality-engine.php`: when `content_type` was not explicitly provided by the caller (detect via a `$hint_provided` boolean parameter threaded from the REST layer), append a synthetic ChangeRecord `{id:"cr-000", original:"", revised:"", category:"tonal", rationale:"لم يُحدَّد نوع المحتوى. تم تطبيق الأسلوب التحريري المحايد افتراضيًا. تحديد نوع المحتوى يُحسِّن دقة المعايرة."}` to the change manifest; depends on T017, T021
- [x] T023 [US2] Update `TOC_Quality_REST` endpoint `POST /quality/run` in `includes/class-toc-quality-rest.php` to track whether `content_type` was explicitly provided vs defaulted, and pass a `$hint_provided` flag through to `TOC_Quality_Engine::process_job()`; no schema change required; depends on T019, T022

**Checkpoint**: All four content types produce register-appropriate output; omitting the hint produces a neutral default and a tonal advisory ChangeRecord.

---

## Phase 5: User Story 3 — Translation Quality Scoring & Audit Trail (Priority: P3)

**Goal**: Every processed TranslationJob produces a numerical quality score and a structured audit trail. Administrators can view, filter, and export the full log; editors see their own. A WP admin review queue lists all flagged jobs with approve/reject actions. Texts below the quality threshold are flagged before publication and the 24-hour SLA is supported by the queue.

**Independent Test**: Process three articles (one that auto-approves, one that flags for review, one that is rejected). Confirm: (a) each has a distinct `pre_score` and `post_score` in the DB; (b) the admin audit endpoint returns all three; (c) the editor audit endpoint returns only their own; (d) the review queue page lists the flagged article with approve/reject controls; (e) approving publishes the post; (f) rejecting returns it to draft.

### Implementation for User Story 3

- [x] T024 [P] [US3] Implement `TOC_Quality_REST::get_audit()` in `includes/class-toc-quality-rest.php` — registers `GET /quality/audit` endpoint (Endpoint 4 from `contracts/rest-api.md`); validates all query params, delegates to `TOC_Quality_DB::get_audit_log()`, returns paginated JSON response with `total`, `page`, `per_page`, `jobs` fields including `post_title` (fetched via `get_the_title()`); enforces admin vs editor visibility split; depends on T010
- [x] T025 [P] [US3] Implement `TOC_Quality_REST::resolve_job()` in `includes/class-toc-quality-rest.php` — registers `POST /quality/jobs/{id}/resolve` endpoint (Endpoint 3): validates `action` is `approve` or `reject`; checks job is in a non-terminal state (returns `409 toc_job_already_resolved` otherwise); on `approve` → calls `wp_update_post(['ID'=>$post_id,'post_status'=>'publish'])` then `TOC_Quality_DB::transition_status($job_id,'human-approved')`; on `reject` → calls `wp_update_post(['ID'=>$post_id,'post_status'=>'draft'])`, saves `rejection_note`, then `TOC_Quality_DB::transition_status($job_id,'rejected')`; enforces own-vs-admin visibility; returns updated job status and `post_status`; depends on T009
- [x] T026 [P] [US3] Implement `TOC_Quality_REST::get_settings()` and `TOC_Quality_REST::update_settings()` in `includes/class-toc-quality-rest.php` — registers `GET` and `PUT /quality/settings` endpoints (Endpoint 5); `GET` returns current options via `TOC_Quality_DB` helpers; `PUT` validates all fields (`quality_threshold` 0–100, `audit_retention_days` 7–365, etc.) then calls `TOC_Quality_DB::update_settings()`; both require `manage_options`; depends on T012
- [x] T027 [US3] Implement `TOC_Quality_Admin::register_menus()` in `includes/class-toc-quality-admin.php` — adds WP admin menu: `add_options_page()` for "TOC Quality Settings" (`manage_options`) and `add_menu_page()` (or `add_posts_page()` submenu) for "Translation Review Queue" (`edit_posts`); settings page renders a form POSTing to `TOC_Quality_REST` settings endpoint via JS fetch; review queue page includes `templates/review-queue.php`; depends on T026
- [x] T028 [US3] Implement `templates/review-queue.php` — renders a filterable HTML table of flagged/engine-unavailable TranslationJobs fetched from `GET /quality/audit?status=flagged-for-review,engine-unavailable`; each row shows post title, content type, pre-score, post-score, created_at, and two action buttons ("Approve" and "Reject") that POST to `POST /quality/jobs/{id}/resolve` via `wp_remote_post()` / JS fetch with WP nonce; displays a success/error notice after each action; if an article has multiple active jobs, shows a "⚠ Multiple active jobs" badge; depends on T024, T025
- [x] T029 [US3] Add WP admin notice in `TOC_Quality_Admin` for editors: when an editor who has pending-review jobs visits any admin page, show a dismissible notice "لديك X مقالات معلّقة بانتظار المراجعة — [عرض قائمة المراجعة]" linking to the review queue page; implement via `admin_notices` action with a transient-based dismiss; depends on T027

**Checkpoint**: All three user stories fully functional. Admins see complete audit history and control settings; editors see their own queue and can resolve flagged articles; review queue surfaces all pending items with approve/reject controls.

---

## Phase 6: Polish & Cross-Cutting Concerns

**Purpose**: Hardening, security sweep, error-message polish, and documentation

- [x] T030 [P] Security audit of all five REST endpoints in `includes/class-toc-quality-rest.php` — verify every callback has an explicit `permission_callback` using `current_user_can()`; verify all DB inputs use `$wpdb->prepare()`; verify all HTML output in admin pages uses `esc_html()` / `esc_attr()` / `wp_kses_post()`; verify the `rejection_note` field is sanitized with `sanitize_textarea_field()` before storage
- [x] T031 [P] Add Arabic word-count helper `toc_count_arabic_words( string $text ): int` in `includes/class-toc-quality-db.php` — uses `preg_match_all('/\p{Arabic}+/u', $text, $m)` to count Arabic tokens separately from Latin tokens; fall back to `str_word_count()` for purely Latin content; use this helper in `TOC_Quality_DB::create_job()` to replace the placeholder word-count logic from T007
- [x] T032 [P] Add `model_version` to the AuditLog metadata JSON in `TOC_Quality_DB::transition_status()` for any event of type `score_assigned` or `auto_approved`; enables post-launch detection of score drift across OpenRouter model upgrades; depends on T009
- [x] T033 Add graceful-degradation test: in the WP admin "TOC Quality Settings" page, add a "Test Engine Connection" button that calls `TOC_Quality_Engine::assess('مرحبا', 'editorial')` and displays either "✓ اتصال ناجح — النموذج: {model_version}" or "✗ تعذّر الاتصال: {error}" via AJAX; depends on T014, T027
- [x] T034 Run all items from the **Testing Checklist** in `specs/001-translation-quality/quickstart.md` manually and document results in a new file `specs/001-translation-quality/checklists/validation.md`; mark each item ✅ or ❌ with a note; any ❌ item creates a follow-up task
- [x] T035 [P] Update `README.md` in plugin root with a "Translation Quality Engine" section: describe the feature, list the five REST endpoints, document the two required env vars (`OPENROUTER_API_KEY`, `OPENROUTER_QUALITY_MODEL`), and link to `specs/001-translation-quality/quickstart.md`

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies — start immediately
- **Foundational (Phase 2)**: Depends on Phase 1 completion — **BLOCKS all user stories**
- **User Story 1 (Phase 3)**: Depends on Phase 2 — implements the core engine + REST run/get + auto-import hook
- **User Story 2 (Phase 4)**: Depends on Phase 3 (T014, T016, T017 must be complete) — extends prompt builders in-place
- **User Story 3 (Phase 5)**: Depends on Phase 2 (T009, T010, T012) and Phase 3 (T017 for full job flow) — can largely run in parallel with Phase 4
- **Polish (Phase 6)**: Depends on all user story phases complete

### User Story Dependencies

- **US1 (P1)**: Depends only on Foundational (Phase 2) — no peer-story dependencies
- **US2 (P2)**: Depends on US1 T014, T016, T017 being complete (extends the engine prompts)
- **US3 (P3)**: Depends on Foundational T009, T010, T012; and US1 T017 for end-to-end job flow — can begin in parallel with US2

### Within Each User Story

- DB layer (T007–T012) before engine (T014–T017) before REST (T019) before admin UI (T027–T029)
- `assess()` (T014) before `rewrite()` (T016) before `process_job()` (T017)
- REST `run` endpoint (T019) before auto-import hook (T020)
- `resolve_job` REST (T025) before review queue UI (T028)

### Parallel Opportunities

- T003 and T004 (Phase 1) can run in parallel
- T007, T008, T010, T011, T012 (Phase 2) can all run in parallel once T005 is done
- T014 and T015 (Phase 3) can run in parallel
- T024, T025, T026 (Phase 5) can run in parallel
- T030, T031, T032, T035 (Phase 6) can all run in parallel

---

## Parallel Example: User Story 1

```
# Once T005 (schema) is done, launch Phase 2 DB helpers in parallel:
T007 create_job()     |  T008 get_job()      |  T010 get_audit_log()
T011 audit_purge()    |  T012 settings()

# Once Phase 2 done, launch US1 engine in parallel:
T014 assess()   |  T015 extract_protected_terms()
        ↓ (both done)
T016 rewrite()
        ↓
T017 process_job()   → T018 scheduler → T019 REST /run → T020 auto-import
```

---

## Implementation Strategy

### MVP First (User Story 1 Only — ~T001–T020)

1. Complete Phase 1: Setup (T001–T004)
2. Complete Phase 2: Foundational (T005–T013) — **do not skip**
3. Complete Phase 3: User Story 1 (T014–T020)
4. **STOP and VALIDATE**: Run the US1 independent test manually
5. Demo: editor submits an article via REST, receives corrected Arabic text inline

### Incremental Delivery

1. Setup + Foundational → plugin activates with new DB tables ✓
2. US1 → quality engine runs, auto-run on import works → **ship MVP**
3. US2 → content-type calibration active → redeploy
4. US3 → review queue + audit log live → redeploy
5. Polish → hardening complete → stable release

### Parallel Team Strategy

With two developers after Foundational (Phase 2) is done:

- **Developer A**: US1 (T014–T020) → then US2 (T021–T023)
- **Developer B**: US3 (T024–T029) in parallel with Developer A on US1/US2

---

## Notes

- `[P]` tasks involve different files with no blocking dependencies — safe to run concurrently
- All PHP files MUST declare `strict_types=1` (PSR-12 + Constitution Principle III)
- All `$wpdb` queries MUST use `$wpdb->prepare()` — no raw query interpolation
- All REST `permission_callback` functions MUST call `current_user_can()` explicitly
- Arabic word counting requires `preg_match_all('/\p{Arabic}+/u', ...)` — not `str_word_count()`
- Commit after each task or logical group; reference this branch (`001-translation-quality`) in commit messages
- Stop at each **Checkpoint** to validate the story independently before continuing
