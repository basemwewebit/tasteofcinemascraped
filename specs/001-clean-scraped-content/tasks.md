---
description: "Task list for Clean Scraped Content"
---

# Tasks: Clean Scraped Content

**Input**: Design documents from `/specs/001-clean-scraped-content/`
**Prerequisites**: plan.md, spec.md, research.md, data-model.md, quickstart.md

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Project initialization and basic structure

- [x] T001 Create `includes/class-toc-content-cleaner.php` class scaffold

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Core infrastructure that MUST be complete before ANY user story can be implemented

- [x] T002 Implement basic `clean($content, $featured_image_url)` static method signature in `includes/class-toc-content-cleaner.php` using `DOMDocument` for robust HTML parsing and reconstruction.
- [x] T003 Ensure `includes/class-toc-content-cleaner.php` is properly included via `require_once` in `tasteofcinemascraped-wp.php`

**Checkpoint**: Foundation ready - basic HTML parsing is functional.

---

## Phase 3: User Story 1 - Remove Redundant Thumbnail Image (Priority: P1) 🎯 MVP

**Goal**: Delete the first duplicate occurrence of the article's featured image inside the scraped HTML, along with any leftover empty wrappers.

**Independent Test**: Provide an HTML string with a leading `<img>` identical to a test `$featured_image_url`, run it through `TOC_Content_Cleaner::clean()`, and verify the output HTML lacks the duplicate image.

### Implementation for User Story 1

- [x] T004 [US1] Implement duplicate `<img src="thumbnail.jpg">` discovery and removal using `DOMXPath` in `includes/class-toc-content-cleaner.php`
- [x] T005 [US1] Implement recursive empty tag (e.g., `<p></p>`, `<figure></figure>`) cleanup after image deletion in `includes/class-toc-content-cleaner.php`
- [x] T006 [US1] Integrate `TOC_Content_Cleaner::clean($content, $featured_thumbnail_url)` directly into `tasteofcinemascraped_import_callback` within `tasteofcinemascraped-wp.php` immediately priorit to `wp_insert_post`

**Checkpoint**: At this point, User Story 1 should be fully functional and testable independently.

---

## Phase 4: User Story 2 - Normalize Content Styling (Priority: P2)

**Goal**: Standardize scraped content layout by stripping all default styles and inserting Tailwind utility classes matching the platform's constraints.

**Independent Test**: Process a deeply nested HTML article with varying inline `style="..."` attributes and verify all output elements only contain standard Tailwind CSS classes matching the predefined type definitions.

### Implementation for User Story 2

- [x] T007 [P] [US2] Define `CLASS_MAP` constant mapping target HTML tags (`h2`, `p`, `blockquote`, `ul`, `ol`, `li`, `a`) to tailored Tailwind typography utility classes in `includes/class-toc-content-cleaner.php`
- [x] T008 [US2] Implement node iteration to execute `removeAttribute('style')` and `removeAttribute('class')` on all nodes within the document instance inside `includes/class-toc-content-cleaner.php`
- [x] T009 [US2] Apply mapped Tailwind classes via `setAttribute('class', ...)` to accurately matched elements during the node iteration in `includes/class-toc-content-cleaner.php`

**Checkpoint**: At this point, User Stories 1 AND 2 should both work seamlessly together.

---

## Phase 5: Polish & Cross-Cutting Concerns

**Purpose**: Improvements that affect multiple user stories

- [x] T010 Test the complete end-to-end import flow with a sample JSON payload using Postman or CURL to verify the integration.
- [x] T011 [P] Code cleanup, standardizing comments, and verifying PHP 8.x types within `includes/class-toc-content-cleaner.php`.

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies - can start immediately.
- **Foundational (Phase 2)**: Depends on Setup completion - BLOCKS all user stories.
- **User Stories (Phase 3+)**: All depend on Foundational phase completion. User Story 2 efficiently builds dynamically on top of User Story 1's `DOMDocument` pass.
- **Polish (Final Phase)**: Depends on all desired user stories being complete.

### User Story Dependencies

- **User Story 1 (P1)**: Can start after Foundational Phase. 
- **User Story 2 (P2)**: Can start after Foundational Phase, but recommended to implement logic iteratively on the same `DOMDocument` instance created for US1 to maximize parsing performance.

### Parallel Opportunities

- T007 can be designed completely apart from DOM manipulation tasks.
- General tests (T010) and documentation reviews (T011) can overlap with development.
