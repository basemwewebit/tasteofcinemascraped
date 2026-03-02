# Quickstart: Predefined Content Category Taxonomy

**Branch**: `002-predefined-categories` | **Date**: 2026-03-02

---

## Overview

This feature locks down the WordPress category taxonomy for imported articles to exactly
10 predefined categories. After deployment:
- No new categories will be created automatically during imports.
- Every imported article is assigned to exactly one of the 10 categories.
- The WordPress admin editor category picker is restricted to those 10 options.

---

## ⚠️ Breaking Change Notice

> **[BREAKING]** This release changes the behavior of `tasteofcinemascraped_get_or_create_terms()`
> for the `category` taxonomy. Previously, any category name from the source site would
> be created automatically in WordPress. After this release, **only the 10 predefined
> categories** will be used. Dynamic creation of categories via the import endpoint is
> permanently disabled.
>
> **Plugin version bumped**: 1.0.0 → **1.1.0** (MINOR, behavior-breaking)
>
> **Who is affected**: Any site that relied on auto-generated WordPress categories from
> the scraper output. The old categories will not be deleted by this feature — only new
> imports will use the restricted set. Old-category migration is deferred to a future
> release.

---

## Setup / Deployment

### 1. Deploy the Plugin Update

```bash
# From the plugin root:
git checkout 002-predefined-categories
# Deploy to your WordPress site as usual (copy plugin files or use your deploy script)
```

### 2. Re-activate the Plugin (or Run Seed Manually)

The 10 predefined categories are seeded automatically on plugin activation.

**Option A — Deactivate + Reactivate in WordPress Admin**:
1. Go to **Plugins → Installed Plugins**
2. Deactivate `Taste of Cinema Scraped Import`
3. Reactivate it immediately

**Option B — Trigger seeding without deactivation** (safe on live sites):
```php
// Add temporarily to functions.php or run via WP-CLI:
do_action('tasteofcinemascraped_seed_categories');
```

Or via WP-CLI:
```bash
wp eval "TOC_Category_Manager::seed_all();"
```

### 3. Verify Categories Were Created

```bash
wp term list category --fields=term_id,name,slug
```

Expected output will include all 10 slugs:
`film-lists`, `features`, `people-lists`, `other-lists`, `reviews`,
`best-of-year`, `by-genre`, `by-country`, `by-decade`, `rankings`

---

## Validation Steps (Manual QA)

### Test 1 — No New Categories Created (SC-003)

1. Note the current number of categories: `wp term list category --count`
2. Run a scraper import: `python -m tasteofcinemascraped <article-url>`
3. Recount: `wp term list category --count`
4. **Expected**: count unchanged.

### Test 2 — Imported Post Has a Predefined Category (SC-002)

1. After the import above, find the new post ID from the REST response.
2. `wp post term list <post_id> category --fields=slug`
3. **Expected**: Output is one of the 10 predefined slugs.

### Test 3 — Unmatched Category Falls Back to Default with Warning (FR-009)

1. Send a test import payload with `"categories": [{"name": "UnknownCat", "slug": "unknowncat"}]`
2. Check `wp-content/debug.log` for a line containing `[TOC-CATEGORY] WARNING`.
3. Verify the post was assigned `film-lists`.

### Test 4 — Plugin Activation Seeds All 10 Categories (SC-004)

1. On a clean WordPress install with no extra categories, activate the plugin.
2. `wp term list category --fields=slug`
3. **Expected**: All 10 slugs present within 5 seconds.

### Test 5 — Admin Editor Shows Only Predefined Categories (FR-010)

1. Edit any post in the WordPress dashboard.
2. Observe the "Category" meta box in the sidebar.
3. **Expected**: Only the 10 predefined Arabic-named categories are visible; no other
   categories appear; creating new ones via the dashboard editor is not possible.

### Test 6 — Deleted Category Auto-Restores on Next Import (FR-007)

1. Delete one predefined category via: `wp term delete category <term_id>`
2. Trigger a new import.
3. `wp term list category --fields=slug`
4. **Expected**: The deleted category reappears.

---

## Configuration

The taxonomy definition lives in a single file:
**`includes/class-toc-category-config.php`**

To update categories in the future (e.g., add an 11th), edit `CATEGORIES`, `SOURCE_SLUG_MAP`,
and `KEYWORD_MAP` in that file, then re-seed via WP-CLI or plugin reactivation.

---

## Debug Logging

Ensure `WP_DEBUG` and `WP_DEBUG_LOG` are enabled in `wp-config.php`:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

Category matching warnings appear in `wp-content/debug.log` prefixed with `[TOC-CATEGORY]`.
