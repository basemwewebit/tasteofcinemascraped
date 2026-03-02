# Data Model: Certified Cinematic Translation Quality Engine

**Feature**: `001-translation-quality`
**Date**: 2026-03-02
**Source**: spec.md Key Entities + research.md decisions

---

## Database Tables

### `{prefix}toc_translation_jobs`

Stores every engine invocation. One row per job; multiple rows may share the same `post_id`.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | `BIGINT UNSIGNED` | PK, AUTO_INCREMENT | Unique job identifier |
| `post_id` | `BIGINT UNSIGNED` | NOT NULL, INDEX | WordPress post ID (WP_Post); multiple jobs per post allowed |
| `content_type` | `VARCHAR(20)` | NOT NULL, DEFAULT `'editorial'` | One of: `synopsis`, `dialogue`, `review`, `editorial` |
| `initiating_user_id` | `BIGINT UNSIGNED` | NOT NULL | WordPress user ID who triggered the job |
| `word_count` | `INT UNSIGNED` | NOT NULL | Word count of input text at submission time |
| `input_hash` | `CHAR(64)` | NOT NULL, INDEX | SHA-256 of input text; used for score reproducibility assertion |
| `pre_score` | `TINYINT UNSIGNED` | NOT NULL DEFAULT 0 | Quality score before engine processing (0–100) |
| `post_score` | `TINYINT UNSIGNED` | NOT NULL DEFAULT 0 | Quality score after engine processing (0–100) |
| `change_count` | `SMALLINT UNSIGNED` | NOT NULL DEFAULT 0 | Number of ChangeRecords produced |
| `change_manifest` | `LONGTEXT` | NULL | JSON array of ChangeRecord objects; NULL if no changes made |
| `model_version` | `VARCHAR(80)` | NOT NULL | OpenRouter model identifier used (for score drift tracking) |
| `status` | `VARCHAR(30)` | NOT NULL, INDEX | Lifecycle status (see Status Enum below) |
| `rejection_note` | `TEXT` | NULL | Optional free-text note added by editor on reject action |
| `created_at` | `DATETIME` | NOT NULL, INDEX | UTC timestamp of job creation |
| `updated_at` | `DATETIME` | NOT NULL | UTC timestamp of last status transition |

**Status Enum** (stored as VARCHAR, validated in PHP):
```
pending → processing → auto-approved
                     → flagged-for-review → human-approved (terminal)
                                          → rejected        (terminal)
                     → engine-unavailable → human-approved (terminal)
                                          → rejected        (terminal)
```

**Indexes**:
- PRIMARY KEY (`id`)
- INDEX `idx_post_id` (`post_id`)
- INDEX `idx_status` (`status`)
- INDEX `idx_user_status` (`initiating_user_id`, `status`)
- INDEX `idx_created_at` (`created_at`)
- INDEX `idx_input_hash` (`input_hash`)

---

### `{prefix}toc_audit_log`

Immutable, append-only event log. One row per status transition. Never updated or deleted (within retention window).

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | `BIGINT UNSIGNED` | PK, AUTO_INCREMENT | Unique log entry ID |
| `job_id` | `BIGINT UNSIGNED` | NOT NULL, INDEX | FK → `toc_translation_jobs.id` |
| `post_id` | `BIGINT UNSIGNED` | NOT NULL, INDEX | Denormalized for fast per-article queries |
| `user_id` | `BIGINT UNSIGNED` | NOT NULL | WordPress user ID who triggered the event |
| `event_type` | `VARCHAR(40)` | NOT NULL | e.g. `job_created`, `processing_started`, `score_assigned`, `auto_approved`, `flagged`, `human_approved`, `rejected`, `engine_unavailable` |
| `previous_status` | `VARCHAR(30)` | NULL | Status before this event |
| `new_status` | `VARCHAR(30)` | NULL | Status after this event |
| `metadata` | `TEXT` | NULL | Optional JSON: score values, model version, error message, etc. |
| `created_at` | `DATETIME` | NOT NULL, INDEX | UTC timestamp of event |

**Retention policy**: Rows older than 90 days are eligible for purge via a scheduled WP-Cron action (`toc_quality_audit_purge`). Purge is a soft-limit; administrators may extend retention via a site option.

**Indexes**:
- PRIMARY KEY (`id`)
- INDEX `idx_job_id` (`job_id`)
- INDEX `idx_post_id` (`post_id`)
- INDEX `idx_user_id` (`user_id`)
- INDEX `idx_created_at` (`created_at`)

---

## PHP Entity Classes

### `TOC_Translation_Job`

```
Properties (mapped to DB columns above):
  int         $id
  int         $post_id
  string      $content_type       // 'synopsis'|'dialogue'|'review'|'editorial'
  int         $initiating_user_id
  int         $word_count
  string      $input_hash
  int         $pre_score          // 0-100
  int         $post_score         // 0-100
  int         $change_count
  array|null  $change_manifest    // decoded from JSON; null if no changes
  string      $model_version
  string      $status
  string|null $rejection_note
  DateTime    $created_at
  DateTime    $updated_at

Key validation rules:
  - content_type MUST be one of the four allowed values; defaults to 'editorial'
  - pre_score, post_score: MUST be 0–100 inclusive (validated with min/max clamp)
  - status: MUST match one of the 6 allowed status strings (enforced in transition methods)
  - word_count: MUST be > 0 and ≤ 5000 (rejected client-side before job creation)
  - input_hash: SHA-256 of the raw input text (computed before any processing)
```

### `TOC_Change_Record`

```
Properties (one element of change_manifest JSON array):
  string $id           // 'cr-NNN', sequential within job
  string $original     // original Arabic segment
  string $revised      // replacement Arabic segment
  string $category     // 'cultural'|'tonal'|'grammatical'|'structural'
  string $rationale    // Arabic plain-language explanation

Validation:
  - category MUST be one of the four canonical values
  - original and revised MUST be non-empty strings
  - rationale SHOULD be non-empty; empty rationale is logged as a warning
```

### `TOC_Quality_Threshold`

```
Stored as WordPress site option: 'toc_quality_threshold' (array)

Structure:
  [
    'value'            => int,      // 0-100; default 70
    'last_modified_by' => int,      // WP user ID
    'last_modified_at' => string,   // ISO 8601 UTC datetime
  ]

Validation:
  - value MUST be integer 0–100 (validated via absint + max clamp)
  - Only users with manage_options capability may write this option
```

---

## State Transition Diagram

```
                    ┌─────────┐
                    │ pending │  (job created)
                    └────┬────┘
                         │ engine invoked
                    ┌────▼──────┐
                    │ processing│
                    └─────┬─────┘
           ┌──────────────┼──────────────┐
           │              │              │
    score≥threshold  score<threshold  backend fail
           │              │              │
    ┌──────▼──────┐ ┌─────▼──────────┐ ┌▼──────────────────┐
    │auto-approved│ │flagged-for-    │ │engine-unavailable │
    │  (terminal  │ │review          │ │                   │
    │  if no      │ └──────┬─────────┘ └────────┬──────────┘
    │  review     │        │                    │
    │  needed)    │   editor action        editor action
    └─────────────┘        │                    │
                    ┌──────┴──────┐      ┌──────┴──────┐
                    │             │      │             │
             ┌──────▼──┐  ┌──────▼──┐  ┌▼─────────┐ ┌▼──────┐
             │human-   │  │rejected │  │human-    │ │reject-│
             │approved │  │(terminal│  │approved  │ │ed     │
             │(terminal│  │)        │  │(terminal)│ │(term.)│
             └─────────┘  └─────────┘  └──────────┘ └───────┘
```

---

## WordPress Site Options (non-tabular settings)

| Option Key | Type | Default | Access |
|---|---|---|---|
| `toc_quality_threshold` | serialized array | `['value'=>70,...]` | `manage_options` only |
| `toc_quality_model` | string | `''` (falls back to `OPENROUTER_QUALITY_MODEL` env var, then `OPENROUTER_MODEL`) | `manage_options` only |
| `toc_quality_audit_retention_days` | int | `90` | `manage_options` only |
| `toc_quality_auto_run_on_import` | bool | `true` | `manage_options` only |
