# Implementation Plan: Predefined Content Category Taxonomy

**Branch**: `002-predefined-categories` | **Date**: 2026-03-02 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/specs/002-predefined-categories/spec.md`

---

## Summary

Replace the dynamic category-creation logic in the WordPress import plugin with a strictly
controlled, predefined taxonomy of 10 categories (5 sourced directly from tasteofcinema.com,
5 derived from common content patterns). Every imported article is assigned to exactly one
predefined category via a two-phase matching algorithm: (1) a static slug map and (2)
keyword-based fallback. No new WordPress categories may be created by the import pipeline;
any unmatched article falls to the default category "قوائم أفلام" with a warning logged.

---

## Technical Context

**Language/Version**: PHP 8.1+ (WordPress plugin host) + Python 3.11 (scraper client)
**Primary Dependencies**:
- WordPress Core Taxonomy API (`wp_insert_term`, `wp_set_post_terms`, `get_term_by`)
- WordPress Plugin Activation hooks (`register_activation_hook`)
- WordPress Admin Meta-boxes (`add_meta_box`, `wp_dropdown_categories`)
- WordPress Logger (`error_log` / custom plugin log via `WP_DEBUG_LOG`)
- Python `tasteofcinemascraped` package (scraper + import pipeline)

**Storage**: WordPress MySQL database — standard `wp_terms`, `wp_term_taxonomy`,
`wp_term_relationships` tables; no custom DB schema changes.

**Testing**: Manual validation via WordPress admin (no automated test runner currently in
project); PHP code validated via WordPress debug log; Python changes validated via scraper
dry-run mode.

**Target Platform**: WordPress 6.x on Linux server (same environment as existing plugin)

**Project Type**: WordPress plugin extension (modifying existing plugin behavior)

**Performance Goals**: Category matching and assignment per article < 5 ms; full 10-category
seed on plugin activation < 5 s (SC-004).

**Constraints**:
- No new PHP dependencies beyond WordPress core
- No custom taxonomy — must use the standard `category` taxonomy for SEO compatibility (spec Assumption 3)
- Admin UI restriction scoped to the Dashboard editor only; REST API remains unrestricted (FR-010)
- Old posts out of scope (clarification: migration deferred to a future feature)

**Scale/Scope**: 10 predefined categories, thousands of imported posts over plugin lifetime.

---

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principle | Check | Status |
|-----------|-------|--------|
| **I. Quality, Reliability & Security** | Category seeding is idempotent; `get_term_by` used before any insert; sanitized inputs. Default fallback ensures 100% coverage (FR-004). | ✅ PASS |
| **II. Clear Architecture & Editorial Integrity** | Single `TOC_Category_Manager` class with one stated responsibility; taxonomy defined in one central config (FR-008); no silent data loss — fallback logs a warning (FR-009). | ✅ PASS |
| **III. Code Standards, Review & Validation** | PSR-12, `declare(strict_types=1)`, WordPress coding standards; manual validation steps documented in `quickstart.md`. | ✅ PASS |
| **IV. Breaking-Change Policy** | Removing `tasteofcinemascraped_get_or_create_terms()` dynamic-creation behavior for `category` taxonomy is a **breaking change** in plugin behavior. Requires: (a) rationale in this plan, (b) migration note in `quickstart.md`, (c) `MINOR` version bump in plugin header. | ⚠️ JUSTIFIED — see Complexity Tracking below |
| **V. Long-Term Maintainability** | No speculative capability; taxonomy list centralized; no new external dependencies. | ✅ PASS |

---

## Project Structure

### Documentation (this feature)

```text
specs/002-predefined-categories/
├── plan.md              # This file (/speckit.plan command output)
├── research.md          # Phase 0 output (/speckit.plan command)
├── data-model.md        # Phase 1 output (/speckit.plan command)
├── quickstart.md        # Phase 1 output (/speckit.plan command)
├── contracts/           # Phase 1 output (/speckit.plan command)
│   └── category-config.contract.md
└── tasks.md             # Phase 2 output (/speckit.tasks command)
```

### Source Code (repository root)

```text
includes/
├── class-toc-category-config.php         # NEW — Central taxonomy definition (10 categories + keyword sets)
├── class-toc-category-manager.php        # NEW — Seeding, enforcement, two-phase matching
├── class-toc-quality-admin.php           # MODIFIED — Category dropdown restriction (FR-010)
├── class-toc-quality-db.php              # UNCHANGED
├── class-toc-quality-engine.php          # UNCHANGED
├── class-toc-quality-rest.php            # UNCHANGED
└── class-toc-quality-scheduler.php       # UNCHANGED

tasteofcinemascraped-wp.php               # MODIFIED — load new classes, remove dynamic cat creation

tasteofcinemascraped/
├── config.py                             # UNCHANGED
├── models.py                             # UNCHANGED (categories field already present)
├── scraper.py                            # UNCHANGED (categories still scraped & forwarded)
└── wordpress_client.py                   # UNCHANGED (categories forwarded as-is to REST endpoint)
```

**Structure Decision**: Single WordPress plugin project. Two new PHP classes added under
`includes/` following the existing class-per-file naming convention
(`class-toc-{module}.php`). No new Python files required — category resolution happens
server-side in the WordPress plugin as the canonical authority.

---

## Complexity Tracking

> **Fill ONLY if Constitution Check has violations that must be justified**

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|--------------------------------------|
| Breaking change: disabling dynamic category creation in `tasteofcinemascraped_get_or_create_terms()` for `category` taxonomy | The entire purpose of FR-003 is to prevent uncontrolled category growth; it cannot be implemented without removing the creation path | Soft deprecation (log-only) would still allow new categories to be written to the DB, defeating SC-001/SC-003 |
