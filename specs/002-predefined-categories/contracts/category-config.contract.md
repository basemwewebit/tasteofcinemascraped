# Contract: Category Configuration

**Format**: PHP class constants (internal)
**Consumers**: `TOC_Category_Manager`, WordPress admin UI, import callback
**Produced by**: `class-toc-category-config.php`
**Branch**: `002-predefined-categories` | **Date**: 2026-03-02

---

## Contract Description

`TOC_Category_Config` is the **single authoritative source** for:
1. The predefined category list `CATEGORIES`
2. The source-to-local slug static map `SOURCE_SLUG_MAP`
3. The per-category keyword sets `KEYWORD_MAP`

No other file may hardcode category slugs, names, or mappings. All reads must go through
this class.

---

## Interface: `TOC_Category_Config`

### Constant: `CATEGORIES`

```php
/** @var array<int, array{slug: string, name: string, description: string, is_default: bool}> */
const CATEGORIES = [ ... ];  // 10 entries, exactly 1 with is_default = true
```

**Invariants**:
- `count(CATEGORIES)` MUST be ≤ 15 (spec FR-001)
- Exactly **one** entry MUST have `is_default = true`
- All `slug` values MUST be unique, lowercase, hyphenated Latin strings
- All `name` values MUST be non-empty UTF-8 Arabic strings

---

### Constant: `SOURCE_SLUG_MAP`

```php
/** @var array<string, string>  source_slug => local_slug */
const SOURCE_SLUG_MAP = [ ... ];
```

**Invariants**:
- All keys: lowercase, hyphenated, derived by `sanitize_title()` on the source name
- All values: MUST exist as a `slug` in `CATEGORIES`
- Map is additive — new source slugs may be added; existing values must not change
  without updating the spec

---

### Constant: `KEYWORD_MAP`

```php
/** @var array<string, list<string>>  local_slug => list of lowercase keywords */
const KEYWORD_MAP = [ ... ];
```

**Invariants**:
- All keys: MUST exist as a `slug` in `CATEGORIES`
- All keyword strings: lowercase, single words or hyphenated tokens
- A local slug with no keywords MUST still exist as a key (empty array is allowed)

---

## Interface: `TOC_Category_Manager`

### `seed_all(): void`

Seeds all entries in `TOC_Category_Config::CATEGORIES` into WordPress as standard
`category` terms. Idempotent — safe to call multiple times.

**Side effects**:
- Calls `wp_insert_term()` for any slug not yet in `wp_terms` under `category`
- No side effects if all terms already exist

---

### `resolve(array $source_names): int`

Resolves a list of raw source category names to a **single** local WordPress term ID.

**Input**: `$source_names` — `list<string|array{name: string, slug?: string}>` — the
`categories` payload from the scraper (same format as existing `get_or_create_terms`).

**Output**: `int` — WordPress term ID, always ≥ 1. Never 0; never creates new terms.

**Algorithm**:
1. Phase 1: For each source entry, derive slug via `sanitize_title()`, look up in `SOURCE_SLUG_MAP`. On first hit: return `get_term_by('slug', $local_slug, 'category')->term_id`.
2. Phase 2: For each source entry, score against `KEYWORD_MAP`. Return term_id of highest-scoring local category (score > 0).
3. Default: Return term_id of the entry with `is_default = true`. Log a `[TOC-CATEGORY] WARNING` via `error_log()` (FR-009).

**Guarantees**:
- Always returns a valid term_id for a predefined category
- Never calls `wp_insert_term()`
- If `$source_names` is empty or all empty strings → applies default without warning

---

### `get_predefined_term_ids(): list<int>`

Returns the list of WordPress term IDs for all seeded predefined categories.

Used by the admin UI to build the restricted category picker.

**Output**: `list<int>` — all term IDs currently in the DB that match the predefined slugs.

---

## Log Contract

Any warning emitted by `TOC_Category_Manager::resolve()` MUST follow this exact format:

```
[TOC-CATEGORY] WARNING: post={post_id} unmatched source_category="{source_name}" → applied default "{default_name}" ({default_slug})
```

Where:
- `{post_id}` = the WordPress post ID (integer, or `0` if not yet known at resolve time)
- `{source_name}` = the raw source category name(s) that failed matching (comma-separated if multiple)
- `{default_name}` = Arabic name of the default category
- `{default_slug}` = slug of the default category (always `film-lists`)

---

## Admin UI Contract (FR-010)

The replacement category meta box MUST:
- Use `wp_dropdown_categories()` with `include` set to `get_predefined_term_ids()`
- Be named identically to the original (`categorydiv` equivalent) so it appears in the
  same sidebar position
- Show the Arabic category names
- Allow selecting exactly one category (single select for simplicity)
- **Not** show a "Add New Category" link or input

The native WordPress category meta box MUST be removed from the `post` editor screen via
`remove_meta_box('categorydiv', 'post', 'side')`.
