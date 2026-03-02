# Quickstart: Translation Quality Engine Developer Guide

**Feature**: `001-translation-quality`
**Date**: 2026-03-02
**Branch**: `001-translation-quality`

This guide is for developers implementing or extending the Translation Quality Engine.

---

## Architecture Overview

```
┌─────────────────────────────────────────────────────┐
│  Python Pipeline (existing)                         │
│  scraper.py → translator.py → wordpress_client.py  │
└──────────────────────┬──────────────────────────────┘
                       │ POST /tasteofcinemascraped/v1/import
┌──────────────────────▼──────────────────────────────┐
│  WordPress Plugin (PHP)                             │
│                                                     │
│  tasteofcinemascraped-wp.php  (existing import)     │
│  ├── includes/                                      │
│  │   ├── class-toc-quality-db.php       (DB layer) │
│  │   ├── class-toc-quality-engine.php   (LLM API)  │
│  │   ├── class-toc-quality-scheduler.php (AS jobs) │
│  │   ├── class-toc-quality-rest.php    (endpoints) │
│  │   └── class-toc-quality-admin.php  (WP admin)   │
│  └── templates/                                     │
│      └── review-queue.php              (admin UI)  │
│                                                     │
│  Storage: wp_{prefix}toc_translation_jobs           │
│           wp_{prefix}toc_audit_log                  │
│  Scheduler: Action Scheduler (async jobs only)      │
│  LLM: OpenRouter API (wp_remote_post)               │
└─────────────────────────────────────────────────────┘
```

---

## Environment Variables

Add these to your `.env` (alongside the existing `OPENROUTER_API_KEY` etc.):

```env
# Optional: separate model for quality engine (falls back to OPENROUTER_MODEL)
OPENROUTER_QUALITY_MODEL=anthropic/claude-3.5-sonnet
```

WordPress site options (configured via Settings → TOC Quality, or REST API):

| Option | Default | Description |
|--------|---------|-------------|
| `toc_quality_threshold` | `70` | Minimum post-correction score for auto-publication |
| `toc_quality_model` | `''` | Model override (falls back to env var) |
| `toc_quality_audit_retention_days` | `90` | Audit log retention in days |
| `toc_quality_auto_run_on_import` | `true` | Run quality check automatically after article import |

---

## Running the Quality Engine Manually

### Via WP-CLI (recommended for testing)

```bash
# Trigger quality check on a specific post
wp eval 'TOC_Quality_Engine::process_job( TOC_Quality_DB::create_job( 42, "review", get_current_user_id() ) );'

# Check job status
wp eval 'var_dump( TOC_Quality_DB::get_jobs_for_post( 42 ) );'
```

### Via REST API

```bash
# Manual quality run on post 42
curl -X POST https://yoursite.com/wp-json/tasteofcinemascraped/v1/quality/run \
  -H "X-Tasteofcinema-Secret: your-secret" \
  -H "Content-Type: application/json" \
  -d '{"post_id": 42, "content_type": "review"}'

# Poll async job status
curl https://yoursite.com/wp-json/tasteofcinemascraped/v1/quality/jobs/19 \
  -H "X-Tasteofcinema-Secret: your-secret"
```

---

## Key Implementation Notes

### LLM Prompt Structure

The engine makes **two sequential OpenRouter calls** per job:

1. **Assessment call**: Sends the Arabic text + rubric definition. Returns JSON:
   ```json
   {
     "pre_score": 52,
     "dimension_scores": { "literal": 40, "cultural": 55, "register": 60, "fluency": 58 },
     "issues": [ { "segment": "...", "category": "cultural", "description": "..." } ]
   }
   ```
   Uses `temperature: 0` for deterministic scoring.

2. **Rewrite call** (only if `pre_score < auto_pass_threshold`, default 85): Sends the text + identified issues. Returns JSON:
   ```json
   {
     "post_score": 88,
     "revised_text": "...",
     "change_manifest": [ { "id": "cr-001", "original": "...", "revised": "...", "category": "cultural", "rationale": "..." } ]
   }
   ```

### Proper Noun Protection

Before sending text to the LLM, the engine extracts proper nouns using the same `_extract_movie_names_and_years()` heuristic from `translator.py` and injects them into the system prompt as a protected list. The LLM is instructed not to alter these tokens.

### Word Count Check

Word count is computed using `str_word_count( wp_strip_all_tags( $content ) )` (PHP-side, before creating the job). For RTL Arabic text, the `mb_str_split` pattern is used instead to correctly count Arabic words.

### Action Scheduler Integration

```php
// Register the async processing action (in plugin init)
add_action( 'toc_process_quality_job', [ 'TOC_Quality_Scheduler', 'handle_async_job' ] );

// Schedule an async job (called from REST callback for >2,000-word articles)
as_schedule_single_action( time(), 'toc_process_quality_job', [ 'job_id' => $job->id ] );
```

Requires Action Scheduler ≥ 3.6 (available standalone as Composer package or bundled with WooCommerce).

---

## Testing Checklist

Before submitting a PR, verify:

- [ ] `CREATE TABLE` via `dbDelta()` runs idempotently (test on fresh + existing DB)
- [ ] Score reproducibility: same input → same `pre_score` across 3 consecutive calls (temperature=0)
- [ ] Status transition guards: cannot move a terminal job to any other status
- [ ] `manage_options` gate: non-admin editor cannot access threshold settings endpoint
- [ ] Editor privacy: editor A cannot retrieve editor B's job via the REST API
- [ ] 5,000-word cap: request with 5,001-word post returns `400 toc_word_count_exceeded`
- [ ] Graceful degradation: mock OpenRouter 503 → job created with `engine-unavailable`, article stays draft
- [ ] Proper noun preservation: film titles in input appear verbatim in `revised_text`
- [ ] Re-run creates new job: submitting the same `post_id` twice creates two distinct rows
- [ ] Audit log immutability: no UPDATE or DELETE permitted on `toc_audit_log` rows within retention window
- [ ] Async notification: Action Scheduler job fires within 10 minutes of scheduling

---

## File Structure (to be created)

```
tasteofcinemascraped-wp.php          (existing — add quality hooks here)
includes/
├── class-toc-quality-db.php         (DB CRUD: jobs + audit log + schema install)
├── class-toc-quality-engine.php     (OpenRouter calls: assess + rewrite)
├── class-toc-quality-scheduler.php  (Action Scheduler integration)
├── class-toc-quality-rest.php       (5 REST endpoints)
└── class-toc-quality-admin.php      (WP admin: settings page + review queue)
templates/
└── review-queue.php                 (Review queue admin page HTML)
```
