# Data Model: Predefined Content Category Taxonomy

**Branch**: `002-predefined-categories` | **Date**: 2026-03-02

---

## Entities

### 1. Predefined Category (`TOC_Category_Config::CATEGORIES`)

Defined as a PHP constant array in `class-toc-category-config.php`. This is the **single
source of truth** for the entire feature (FR-008).

| Field | Type | Description |
|-------|------|-------------|
| `slug` | `string` | Latin slug — used as WP term slug (unique, URL-safe) |
| `name` | `string` | Arabic display name — seeded as `wp_terms.name` |
| `description` | `string` | Short Arabic description for the term |
| `is_default` | `bool` | `true` for exactly one entry: "قوائم أفلام" |

**Complete entity list (10 entries)**:

```php
[
  ['slug' => 'film-lists',   'name' => 'قوائم أفلام',            'description' => 'قوائم الأفلام الموضوعية والتحريرية',       'is_default' => true],
  ['slug' => 'features',     'name' => 'مقالات وتحليلات',        'description' => 'مقالات تحليلية وتعمقية في عالم السينما',   'is_default' => false],
  ['slug' => 'people-lists', 'name' => 'قوائم مخرجين وممثلين',   'description' => 'قوائم تتمحور حول صناع ونجوم السينما',      'is_default' => false],
  ['slug' => 'other-lists',  'name' => 'قوائم متنوعة',           'description' => 'قوائم متنوعة تشمل كتباً وموسيقى وتلفزيون', 'is_default' => false],
  ['slug' => 'reviews',      'name' => 'مراجعات أفلام',          'description' => 'مراجعات نقدية للأفلام',                   'is_default' => false],
  ['slug' => 'best-of-year', 'name' => 'أفضل أفلام السنة',       'description' => 'أبرز وأفضل أفلام كل عام',                 'is_default' => false],
  ['slug' => 'by-genre',     'name' => 'أفلام حسب النوع',        'description' => 'تصنيفات الأفلام مقسّمة حسب النوع السينمائي', 'is_default' => false],
  ['slug' => 'by-country',   'name' => 'أفلام حسب البلد',        'description' => 'تصنيفات الأفلام مقسّمة حسب بلد الإنتاج',  'is_default' => false],
  ['slug' => 'by-decade',    'name' => 'أفلام حسب العقد',       'description' => 'تصنيفات الأفلام مقسّمة حسب حقبة زمنية',   'is_default' => false],
  ['slug' => 'rankings',     'name' => 'مقارنات وتصنيفات',       'description' => 'قوائم مقارِنة ومصنِّفة للأفلام والمخرجين', 'is_default' => false],
]
```

**Stored in WordPress as**:
- `wp_terms.name` = Arabic `name`
- `wp_terms.slug` = `slug`
- `wp_term_taxonomy.taxonomy` = `'category'`
- `wp_term_taxonomy.description` = `description`

---

### 2. Static Slug Map (`TOC_Category_Config::SOURCE_SLUG_MAP`)

Lookup table: **source slug → local slug**. Normalized keys (lowercase, hyphenated).

```php
[
  // Direct source categories (tasteofcinema.com)
  'film-lists'   => 'film-lists',
  'lists'        => 'film-lists',
  'features'     => 'features',
  'feature'      => 'features',
  'people-lists' => 'people-lists',
  'other-lists'  => 'other-lists',
  'reviews'      => 'reviews',
  'review'       => 'reviews',

  // Derived patterns (common scraped sub-category names)
  'best-of'      => 'best-of-year',
  'best-films'   => 'best-of-year',
  'best-movies'  => 'best-of-year',
  'by-genre'     => 'by-genre',
  'genre-lists'  => 'by-genre',
  'by-country'   => 'by-country',
  'country-lists'=> 'by-country',
  'by-decade'    => 'by-decade',
  'decade-lists' => 'by-decade',
  'ranked'       => 'rankings',
  'rankings'     => 'rankings',
  'ranking'      => 'rankings',
]
```

---

### 3. Keyword Map (`TOC_Category_Config::KEYWORD_MAP`)

Used in Phase 2 matching. Keys are local slugs; values are arrays of lowercase keywords.

```php
[
  'film-lists'   => ['list', 'films', 'movies', 'cinema', 'ranking', 'top', 'best'],
  'features'     => ['feature', 'article', 'essay', 'analysis', 'guide', 'opinion', 'interview', 'editorial'],
  'people-lists' => ['director', 'actor', 'actress', 'filmmaker', 'people', 'person', 'auteur'],
  'other-lists'  => ['books', 'music', 'tv', 'television', 'series', 'albums', 'songs'],
  'reviews'      => ['review', 'critique', 'rating', 'assessment'],
  'best-of-year' => ['best', 'year', 'annual', '2024', '2025', '2023'],
  'by-genre'     => ['genre', 'horror', 'drama', 'comedy', 'thriller', 'western', 'action', 'sci-fi'],
  'by-country'   => ['country', 'french', 'korean', 'japanese', 'italian', 'american', 'british', 'iranian'],
  'by-decade'    => ['decade', '70s', '80s', '90s', '60s', '50s', 'century'],
  'rankings'     => ['ranked', 'ranking', 'versus', 'comparison', 'vs', 'compared'],
]
```

---

### 4. Category Resolution Result

Internal value object returned by `TOC_Category_Manager::resolve()`:

| Field | Type | Description |
|-------|------|-------------|
| `term_id` | `int` | WordPress term ID of the assigned category |
| `slug` | `string` | Local slug of the assigned category |
| `match_phase` | `int` | `1` = static map hit, `2` = keyword match, `0` = default applied |
| `source_name` | `string` | Original category name from scraper (for logging) |
| `logged_warning` | `bool` | `true` if a warning was written to the debug log |

---

## Entity Relationships

```
┌──────────────────────────────────┐
│  TOC_Category_Config             │
│  ─────────────────────           │
│  CATEGORIES[]      (10 entries)  │
│  SOURCE_SLUG_MAP[] (map)         │
│  KEYWORD_MAP[]     (map)         │
└──────────┬───────────────────────┘
           │ read-only reference
           ▼
┌──────────────────────────────────┐       ┌─────────────────────────────────┐
│  TOC_Category_Manager            │       │  WordPress Term (category)       │
│  ─────────────────────           │       │  ─────────────────────           │
│  seed_all()  ─────────────────── │──────▶│  wp_terms.slug                  │
│  resolve(names[]) ─────────────  │──────▶│  wp_terms.name (Arabic)         │
│    Phase 1: slug map             │       │  wp_term_taxonomy.taxonomy      │
│    Phase 2: keyword match        │       └─────────────────────────────────┘
│    Fallback: default             │
└──────────┬───────────────────────┘
           │ assigns via wp_set_post_terms()
           ▼
┌──────────────────────────────────┐
│  WordPress Post (wp_posts)       │
│  ─────────────────────           │
│  post_id                         │
│  category term_id (via           │
│  wp_term_relationships)          │
└──────────────────────────────────┘
```

---

## State Transitions

### Category Seed Lifecycle

```
[Plugin Inactive]
     │ register_activation_hook fires
     ▼
[seed_all() called]
     │ For each entry in CATEGORIES:
     │   get_term_by('slug', slug, 'category')
     │     ├─ exists → skip (idempotent)  
     │     └─ missing → wp_insert_term(name, 'category', ['slug', 'description'])
     ▼
[All 10 categories present in wp_terms]
     │
     │ (Admin manually deletes one)
     ▼
[< 10 categories present]
     │ Next import run triggers seed_all() guard
     ▼
[All 10 categories present again]  ← FR-007 satisfied
```

### Per-Article Category Assignment

```
[Import starts — categories[] from scraper payload]
     │ tasteofcinemascraped_import_callback()
     │ calls TOC_Category_Manager::resolve(categories)
     ▼
[Phase 1: Static Map]
     │ For each source name → sanitize_title() → lookup SOURCE_SLUG_MAP
     │   ├─ hit  → get_term_by('slug', local_slug) → return term_id  ✅
     │   └─ miss → Phase 2
     ▼
[Phase 2: Keyword Match]
     │ Tokenize source name → count keyword hits per local category
     │   ├─ best_score > 0 → return winning term_id  ✅
     │   └─ score = 0 → Default
     ▼
[Default: "film-lists"]
     │ error_log("[TOC-CATEGORY] WARNING: ...")  ← FR-009
     │ return term_id of "film-lists"  ✅
     ▼
[wp_set_post_terms(post_id, [term_id], 'category')]
```

---

## Validation Rules

| Rule | Enforcement |
|------|-------------|
| Exactly 1 default category (`is_default = true`) | `TOC_Category_Config` assertion checked in `seed_all()` |
| At most 10 categories in `CATEGORIES` | `count(CATEGORIES) <= 15` check in `seed_all()` (spec FR-001) |
| Local slug must match a seeded term before assignment | `get_term_by()` called before `wp_set_post_terms()` |
| Post always receives ≥ 1 category | Default fallback guarantees `term_id` is always non-zero |
| Admin dropdown shows only predefined term IDs | `wp_dropdown_categories(['include' => $predefined_ids])` |
