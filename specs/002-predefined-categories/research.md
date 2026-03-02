# Research: Predefined Content Category Taxonomy

**Branch**: `002-predefined-categories` | **Date**: 2026-03-02
**Status**: Complete — all NEEDS CLARIFICATION resolved

---

## 1. WordPress Category Seeding (Idempotent Activation)

**Decision**: Use `register_activation_hook` + a separate guard check on `init` (for
re-seeding after manual deletion per FR-007).

**Rationale**:
- `register_activation_hook` is the canonical WordPress pattern for one-time setup.
- FR-007 requires automatic re-creation of deleted predefined categories on next import
  run, so `init` hook or a guard inside the import pipeline is also needed.
- `get_term_by('slug', $slug, 'category')` is the correct existence check before calling
  `wp_insert_term()` to guarantee idempotency.

**Alternatives considered**:
- Using a transient cache flag → rejected: transients can expire, leading to missed seeds.
- Using `wp_insert_term` unconditionally → rejected: produces `WP_Error` on duplicate
  slug, requiring extra error handling.

---

## 2. Blocking Dynamic Category Creation

**Decision**: Wrap the existing `tasteofcinemascraped_get_or_create_terms()` call for the
`category` taxonomy so that only term IDs from the predefined list are ever written via
`wp_set_post_terms()`. The function will still be called for `post_tag` (tags remain
dynamic); only the `category` branch is replaced by `TOC_Category_Manager::resolve()`.

**Rationale**:
- Minimal surgery to the main plugin file; risk of regression is confined to one call site
  (line 183 of `tasteofcinemascraped-wp.php`).
- `TOC_Category_Manager::resolve()` encapsulates both matching phases and the default
  fallback, returning only IDs that already exist in `wp_terms`.

**Alternatives considered**:
- Removing `tasteofcinemascraped_get_or_create_terms()` entirely → rejected: function is
  still needed for `post_tag`.
- Adding a WordPress filter `pre_insert_term` → rejected: too broad, would also block
  manual category creation by administrators.

---

## 3. Two-Phase Category Matching Algorithm

**Decision**: Implement as a pure PHP method with no external dependencies.

### Phase 1 — Static Slug Map

Direct lookup: compare the incoming slug (or `sanitize_title($name)`) against a hardcoded
associative array `SOURCE_SLUG → LOCAL_SLUG` defined in `TOC_Category_Config`.

Known mappings sourced from tasteofcinema.com structure:

| Source slug (scraper) | Local slug |
|-----------------------|------------|
| `film-lists` / `lists` | `film-lists` |
| `features` | `features` |
| `people-lists` | `people-lists` |
| `other-lists` | `other-lists` |
| `reviews` | `reviews` |

Derived slugs from common content patterns (will be added as additional map entries for
scraped sub-category names observed on the site):

| Source pattern | Local slug |
|----------------|------------|
| `best-of`, `best-films`, `year` | `best-of-year` |
| `genre`, `by-genre`, `horror`, `drama`, `comedy` etc. | `by-genre` |
| `country`, `by-country`, `french`, `korean`, `japanese` etc. | `by-country` |
| `decade`, `by-decade`, `70s`, `80s`, `90s` etc. | `by-decade` |
| `ranked`, `ranking`, `top-10`, `ranking` | `rankings` |

### Phase 2 — Keyword Matching

If Phase 1 yields no result, compare the source category name against a keyword set per
local category. Scoring: count of keyword hits / total keywords. Apply if score > 0.
Highest score wins. If no category scores > 0, apply default.

**Keyword sets** (defined in `TOC_Category_Config::KEYWORD_MAP`):

| Local slug | Keywords |
|------------|----------|
| `film-lists` | list, films, movies, cinema, ranking, top |
| `features` | feature, article, essay, analysis, guide, opinion, interview |
| `people-lists` | director, actor, actress, filmmaker, people, person |
| `other-lists` | books, music, tv, television, series, albums |
| `reviews` | review, critique, rating |
| `best-of-year` | best, year, annual, 2024, 2025 |
| `by-genre` | genre, horror, drama, comedy, thriller, western, action |
| `by-country` | country, french, korean, japanese, italian, american, british |
| `by-decade` | decade, 70s, 80s, 90s, 60s, century |
| `rankings` | ranked, ranking, versus, comparison, vs |

**Rationale**: Pure string matching is deterministic, requires no ML/AI, adds zero latency
overhead, and is fully auditable by inspecting the config file (V. Long-Term Maintainability).

**Alternatives considered**:
- OpenRouter/LLM for semantic matching → rejected: adds external dependency, latency, and
  cost for a classification problem that can be solved with keyword scoring.
- WordPress taxonomy term meta (storing match source) → deferred: useful for debugging
  but not required by spec; can be added later as enhancement.

---

## 4. Admin UI Restriction (FR-010)

**Decision**: Override the category meta box in the WordPress post editor using
`add_meta_box()` with a custom callback that renders a `<select>` limited to the 10
predefined categories. The native category meta box is **removed** for the `post` post
type when a predefined category set is active.

**Rationale**:
- The spec explicitly scopes restriction to "واجهة التحرير في لوحة التحكم فقط" (dashboard
  edit interface only), excluding REST API — so a server-side query filter would be
  over-engineered.
- WordPress `wp_dropdown_categories()` with `include` parameter cleanly limits the visible
  terms to a specified list of term IDs.
- The native built-in category meta box is removed via `remove_meta_box('categorydiv', 'post', 'side')`
  and replaced with a custom one.

**Alternatives considered**:
- Filtering `get_terms()` globally → rejected: would affect the REST API and potentially
  break imports that resolve IDs.
- JavaScript-based category removal from the Gutenberg sidebar → rejected: fragile,
  JS-reliant, breaks on REST API changes.

---

## 5. Warning / Logging Strategy (FR-009)

**Decision**: Use `error_log()` with a structured prefix `[TOC-CATEGORY]` to write to the
WordPress debug log (`wp-content/debug.log`). This mirrors the existing plugin's logging
approach and requires no new infrastructure.

**Log format**:
```
[TOC-CATEGORY] WARNING: post={post_id} unmatched source_category="{source_name}" → applied default "قوائم أفلام" (film-lists)
```

**Rationale**: Aligns with clarification Q3 (spec line 16): "يُسجَّل تحذير (warning) في ملف
سجل الإضافة". WordPress debug log is already the standard for this plugin.

**Alternatives considered**:
- Custom DB log table → rejected: over-engineered for a warning; no spec requirement for
  log persistence or UI display.
- WP admin notices → rejected: imports run as REST API calls, not browser page loads.

---

## All NEEDS CLARIFICATION Resolved

All items from the spec's Clarifications section (spec.md lines 14–18) are fully resolved:

| Clarification | Resolution |
|---------------|------------|
| هل نُبقي التصنيفات المشتقة؟ | ✅ Keep all 10; 5 direct + 5 derived |
| ترحيل المواد القديمة؟ | ✅ Deferred — not in scope |
| فشل مطابقة التصنيف؟ | ✅ Warn + apply default "film-lists" |
| القيد على التغيير اليدوي؟ | ✅ Dashboard only; REST excluded |
| كيف تحديد التصنيف؟ | ✅ Static map first, then keyword fallback |
