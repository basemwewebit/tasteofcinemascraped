# Tasks: Predefined Content Category Taxonomy

<!-- 
VALIDATION REPORT (2026-03-02):
- [X] Test 1: No New Categories — Implemented via guard in tasteofcinemascraped_get_or_create_terms() and call replacement in import callback.
- [X] Test 2: Predefined Category assigned — Implemented via TOC_Category_Manager::resolve() logic.
- [X] Test 3: Unmatched fallback + Warning — Implemented in resolve() with exact log contract matching.
- [X] Test 4: Seeding on activation — Implemented via TOC_Category_Manager::seed_all() hooked to plugin activation.
- [X] Test 5: Admin UI restricted — Implemented via replacement meta box in TOC_Quality_Admin.
- [X] Test 6: Auto-restore on import — Implemented via seed_all() call at top of import callback.
-->

**Input**: Design documents from `/specs/002-predefined-categories/`
**Branch**: `002-predefined-categories`
**Prerequisites**: plan.md ✅ | spec.md ✅ | research.md ✅ | data-model.md ✅ | contracts/ ✅ | quickstart.md ✅

**Organization**: Tasks are grouped by user story to enable independent implementation and testing of each story.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies on incomplete tasks)
- **[Story]**: `[US1]` = Story 1 (auto-assign on import) | `[US2]` = Story 2 (block dynamic creation) | `[US3]` = Story 3 (seed & protect categories)

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Introduce new PHP class files and update the plugin loader. No behavior changes yet.

- [X] T001 Create `includes/class-toc-category-config.php` with class skeleton, `declare(strict_types=1)`, PSR-12 header, and three empty public constants: `CATEGORIES`, `SOURCE_SLUG_MAP`, `KEYWORD_MAP`
- [X] T002 Create `includes/class-toc-category-manager.php` with class skeleton, `declare(strict_types=1)`, PSR-12 header, and empty method stubs: `seed_all()`, `resolve(array $source_names): int`, `get_predefined_term_ids(): array`
- [X] T003 Register both new classes in `tasteofcinemascraped-wp.php` — add `require_once` statements for `class-toc-category-config.php` and `class-toc-category-manager.php` (after existing requires, before hooks)

**Checkpoint**: Plugin loads without fatal errors after adding the two skeleton files.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Populate config constants and implement `seed_all()` — both are required before any user story can function.

**⚠️ CRITICAL**: No user story work can begin until this phase is complete.

- [X] T004 Populate `TOC_Category_Config::CATEGORIES` in `includes/class-toc-category-config.php` — define all 10 entries (slug, name, description, is_default) exactly as specified in `data-model.md` entity list
- [X] T005 [P] Populate `TOC_Category_Config::SOURCE_SLUG_MAP` in `includes/class-toc-category-config.php` — define all source-slug → local-slug mappings from `data-model.md` Static Slug Map table (direct + derived patterns)
- [X] T006 [P] Populate `TOC_Category_Config::KEYWORD_MAP` in `includes/class-toc-category-config.php` — define per-slug keyword arrays from `data-model.md` Keyword Map table
- [X] T007 Implement `TOC_Category_Manager::seed_all()` in `includes/class-toc-category-manager.php` — for each entry in `CATEGORIES`: call `get_term_by('slug', $slug, 'category')`; if missing call `wp_insert_term($name, 'category', ['slug' => $slug, 'description' => $desc])`. Idempotent; assert exactly one `is_default = true` entry before seeding; assert `count(CATEGORIES) <= 15`
- [X] T008 Implement `TOC_Category_Manager::get_predefined_term_ids()` in `includes/class-toc-category-manager.php` — iterate `CATEGORIES`, call `get_term_by('slug', $slug, 'category')`, collect all non-false term IDs into array and return it
- [X] T009 Hook `seed_all()` to plugin activation in `tasteofcinemascraped-wp.php` — extend the existing `register_activation_hook` callback to also call `TOC_Category_Manager::seed_all()`

**Checkpoint**: Deactivate + reactivate plugin → `wp term list category --fields=slug` shows all 10 predefined slugs.

---

## Phase 3: User Story 1 — تصنيف المادة الجديدة تلقائياً (Priority: P1) 🎯 MVP

**Goal**: Every newly imported article is automatically assigned to exactly one predefined category using the two-phase (static map → keyword fallback → default) algorithm.

**Independent Test**: Import one article via the Python scraper → check assigned category slug is in the 10 predefined slugs using `wp post term list <post_id> category --fields=slug`.

### Implementation for User Story 1

- [X] T010 [US1] Implement Phase 1 (static map matching) inside `TOC_Category_Manager::resolve()` in `includes/class-toc-category-manager.php` — for each source name: derive slug via `sanitize_title()`, look up in `SOURCE_SLUG_MAP`; on first hit call `get_term_by('slug', $local_slug, 'category')` and return `term_id`
- [X] T011 [US1] Implement Phase 2 (keyword matching) inside `TOC_Category_Manager::resolve()` in `includes/class-toc-category-manager.php` — tokenize source name (lowercase, split on spaces/hyphens); count keyword hits per entry in `KEYWORD_MAP`; return `term_id` of the entry with the highest score > 0
- [X] T012 [US1] Implement Default fallback inside `TOC_Category_Manager::resolve()` in `includes/class-toc-category-manager.php` — when both phases yield no result: fetch term where `is_default = true` from `CATEGORIES`, write warning via `error_log()` in the exact format from `contracts/category-config.contract.md` Log Contract section, return that `term_id`
- [X] T013 [US1] Wire `TOC_Category_Manager::resolve()` into the import callback in `tasteofcinemascraped-wp.php` — replace line 183 (`$cat_ids = tasteofcinemascraped_get_or_create_terms(...)` for `'category'`) with `$cat_ids = [TOC_Category_Manager::resolve($request->get_param('categories'))]`; keep the `post_tag` call to `tasteofcinemascraped_get_or_create_terms()` untouched
- [X] T014 [US1] Handle empty `categories` payload edge case in `TOC_Category_Manager::resolve()` in `includes/class-toc-category-manager.php` — if `$source_names` is empty or all entries are empty strings, apply default category silently (no warning log per spec edge case line 76)
- [X] T015 [US1] Seed guard on import: call `TOC_Category_Manager::seed_all()` at the top of `tasteofcinemascraped_import_callback()` in `tasteofcinemascraped-wp.php` — ensures deleted categories are restored before resolution runs (FR-007 coverage for the import path)

**Checkpoint**: Import 3 articles from different source categories → each gets exactly one predefined slug. Check `wp-content/debug.log` for any `[TOC-CATEGORY] WARNING` entries for unmatched categories.

---

## Phase 4: User Story 2 — إيقاف إنشاء التصنيفات الديناميكية (Priority: P1)

**Goal**: The import pipeline can never create new WordPress categories. The total category count in WordPress must remain ≤ 15 after any import run.

**Independent Test**: Run a full import batch → `wp term list category --count` returns the same number before and after.

### Implementation for User Story 2

- [X] T016 [US2] Guard `tasteofcinemascraped_get_or_create_terms()` against category creation in `tasteofcinemascraped-wp.php` — add an early return/skip inside the function when `$taxonomy === 'category'` (the call path for categories is now replaced by T013, but this guard is a safety net to prevent any future accidental re-routing): add `if ( $taxonomy === 'category' ) { return []; }` as the first statement in the function body, with an inline comment explaining why
- [X] T017 [US2] Add the BREAKING CHANGE version bump in `tasteofcinemascraped-wp.php` plugin header — update `Version: 1.0.0` to `Version: 1.1.0` and add a changelog comment block above the header documenting `[BREAKING]` dynamic category creation disabled for the `category` taxonomy
- [X] T018 [US2] Verify `resolve()` never calls `wp_insert_term()` — code review / inline assertion: add `// INVARIANT: this method must never call wp_insert_term()` comment at the top of `TOC_Category_Manager::resolve()` in `includes/class-toc-category-manager.php` to make contract explicit for future maintainers

**Checkpoint**: Manually send a REST import payload with `"categories": [{"name": "RandomNewCat", "slug": "random-new-cat"}]` → verify no new term appears in `wp term list category` and `resolve()` returns `film-lists` with a warning in debug log.

---

## Phase 5: User Story 3 — تهيئة التصنيفات وتثبيتها (Priority: P2)

**Goal**: All 10 predefined categories exist immediately on plugin activation, and any manually deleted category is auto-restored before the next import.

**Independent Test**: Fresh WordPress — activate plugin → `wp term list category --fields=slug` lists all 10 slugs. Then delete one → run any import → the deleted slug reappears.

### Implementation for User Story 3

- [X] T019 [US3] Add public action hook for manual seeding trigger in `tasteofcinemascraped-wp.php` — register `add_action('tasteofcinemascraped_seed_categories', ['TOC_Category_Manager', 'seed_all'])` so the hook documented in `quickstart.md` works for ops/WP-CLI use
- [X] T020 [US3] Validate seed invariants inside `TOC_Category_Manager::seed_all()` in `includes/class-toc-category-manager.php` — before iterating entries: (a) assert `count(CATEGORIES) <= 15` with an `error_log` warning if violated; (b) assert exactly one `is_default = true` entry, throw a `\RuntimeException` if zero or multiple found (fail fast, do not seed partial state)
- [X] T021 [US3] Implement Admin UI category restriction (FR-010) — add a new private static method `register_category_metabox()` in `includes/class-toc-quality-admin.php`: (a) call `remove_meta_box('categorydiv', 'post', 'side')` in an `add_action('add_meta_boxes', ...)` callback; (b) register a replacement meta box with `add_meta_box('toc-category-select', 'التصنيف', [self::class, 'render_category_metabox'], 'post', 'side', 'high')`
- [X] T022 [US3] Implement `TOC_Quality_Admin::render_category_metabox()` in `includes/class-toc-quality-admin.php` — render a `<select name="post_category[]">` populated via `wp_dropdown_categories(['echo' => false, 'hide_empty' => false, 'include' => TOC_Category_Manager::get_predefined_term_ids(), 'name' => 'post_category[]', 'selected' => get_the_category($post->ID)[0]->term_id ?? 0])`. Add a nonce field for the save hook.
- [X] T023 [US3] Implement save hook for restricted category meta box in `includes/class-toc-quality-admin.php` — add `add_action('save_post', [self::class, 'save_category_metabox'])` that: verifies nonce, verifies `current_user_can('edit_post', $post_id)`, validates the submitted `post_category[]` value is in `get_predefined_term_ids()`, then calls `wp_set_post_terms($post_id, [intval($_POST['post_category'][0])], 'category')`
- [X] T024 [US3] Register `register_category_metabox()` hook in `tasteofcinemascraped-wp.php` — add `add_action('add_meta_boxes', ['TOC_Quality_Admin', 'register_category_metabox'])` and `add_action('save_post', ['TOC_Quality_Admin', 'save_category_metabox'])` alongside existing admin hooks

**Checkpoint**: Activate plugin on clean WordPress → all 10 categories present. Open post editor → only 10 Arabic-named options visible in the category meta box, no "Add New Category" input. Delete one term → trigger import → term reappears.

---

## Phase 6: Polish & Cross-Cutting Concerns

**Purpose**: Documentation, code quality, and final validation across all stories.

- [X] T025 [P] Update plugin `README.md` — add a "Category Taxonomy" section documenting the 10 predefined categories, the `[BREAKING]` change notice, and a reference to `quickstart.md`
- [X] T026 [P] Add inline PHPDoc blocks to all public methods in `includes/class-toc-category-config.php` and `includes/class-toc-category-manager.php` — document params, return types, and invariants per constitution Principle II
- [X] T027 Run all 6 manual validation steps from `specs/002-predefined-categories/quickstart.md` — document results as a comment block at the top of `tasks.md` or in a new `validation-report.md`
- [X] T028 [P] Update `specs/002-predefined-categories/spec.md` — change `Status: Draft` to `Status: Implemented`
- [X] T029 Code review pass — verify all PHP files: (a) `declare(strict_types=1)` present, (b) all inputs sanitized/escaped, (c) all `wp_insert_term` / `wp_set_post_terms` calls guarded by `current_user_can()` where applicable, (d) no hardcoded category slugs outside `TOC_Category_Config`

---

## Dependencies & Execution Order

### Phase Dependencies

```
Phase 1: Setup          → No dependencies — start immediately
    │
    ▼
Phase 2: Foundational   → Depends on Phase 1 — BLOCKS all user stories
    │
    ├──▶ Phase 3 (US1 P1): Auto-assignment  ← Parallel with US2/US3 if staffed
    ├──▶ Phase 4 (US2 P1): Block dynamic    ← Parallel with US1/US3 if staffed
    └──▶ Phase 5 (US3 P2): Seed & protect   ← Parallel with US1/US2 if staffed
              │
              ▼
         Phase 6: Polish  → Depends on all desired stories being complete
```

### User Story Dependencies

| Story | Priority | Depends On | Blocks |
|-------|----------|------------|--------|
| US1 — Auto-assign on import | P1 | Phase 2 complete | Nothing |
| US2 — Block dynamic creation | P1 | Phase 2 complete + T013 (US1) done | Nothing |
| US3 — Seed & protect | P2 | Phase 2 complete | Nothing |

> **Note**: US2 has a soft dependency on T013 (US1) because T016 is the safety-net guard
> for the call site that T013 already replaced. Both can be written simultaneously but
> T016 is logically validated after T013.

### Within Each User Story

- Config constants (Phase 2) → before any resolve() logic
- `seed_all()` (Phase 2) → before `resolve()` or admin UI
- `resolve()` phases 1 & 2 → before default fallback (T010 & T011 before T012)
- `resolve()` complete → before wiring into import callback (T010–T012 before T013)

### Parallel Opportunities

- **T005 + T006**: Populate `SOURCE_SLUG_MAP` and `KEYWORD_MAP` simultaneously (different constants, same file but non-overlapping line ranges)
- **T010 + T011**: Phase 1 and Phase 2 of `resolve()` can be written independently as private helper methods; the main `resolve()` orchestrates both
- **T001 + T002**: Two new skeleton files with no shared state
- **T021 + T022**: Metabox registration and rendering are separate methods

---

## Parallel Execution Examples

### Phase 2 Parallel Work

```
Parallel batch:
  Task T005: Populate SOURCE_SLUG_MAP in includes/class-toc-category-config.php
  Task T006: Populate KEYWORD_MAP in includes/class-toc-category-config.php
  (Then sequential: T007 seed_all, T008 get_predefined_term_ids)
```

### Phase 3 + Phase 4 Parallel (if two developers)

```
Developer A — Phase 3 (US1):
  T010 → T011 → T012 → T013 → T014 → T015

Developer B — Phase 4 (US2):
  T016 → T017 → T018
  (T016 can start as soon as Phase 2 completes)
```

### Phase 5 Parallel

```
Parallel batch (US3):
  T021: register_category_metabox() in class-toc-quality-admin.php
  T019: Add action hook in tasteofcinemascraped-wp.php
  (Then sequential: T022 render, T023 save, T024 register save hook)
```

---

## Implementation Strategy

### MVP First (User Stories 1 + 2 Only — both P1)

1. Complete **Phase 1**: Setup (T001–T003)
2. Complete **Phase 2**: Foundational (T004–T009) — **CRITICAL GATE**
3. Complete **Phase 3**: US1 auto-assignment (T010–T015)
4. Complete **Phase 4**: US2 block dynamic creation (T016–T018)
5. **STOP and VALIDATE**: Run quickstart.md tests 1, 2, 3
6. Deploy if tests pass — site is safe from category sprawl

### Full Delivery (All 3 User Stories)

1. MVP above
2. Complete **Phase 5**: US3 seed & protect (T019–T024)
3. Complete **Phase 6**: Polish (T025–T029)
4. Run all 6 quickstart.md tests
5. Final deploy

### Single-Developer Sequential Order

```
T001 → T002 → T003 → T004 → T005 → T006 → T007 → T008 → T009
→ T010 → T011 → T012 → T013 → T014 → T015
→ T016 → T017 → T018
→ T019 → T020 → T021 → T022 → T023 → T024
→ T025 → T026 → T027 → T028 → T029
```

---

## Notes

- `[P]` tasks = different files or non-overlapping code areas, no dependency on incomplete tasks
- `[US#]` label maps each implementation task to its user story for full traceability
- No automated tests in this project — validation follows the 6 manual steps in `quickstart.md`
- Commit after each phase checkpoint (9 logical commits total: Setup, Foundation, US1, US2, US3, Polish)
- Constitution Principle IV: `[BREAKING]` tag + version bump (1.0.0 → 1.1.0) is tracked in T017
- Avoid touching `class-toc-quality-engine.php`, `class-toc-quality-rest.php`, `class-toc-quality-db.php`, `class-toc-quality-scheduler.php` — all out of scope
