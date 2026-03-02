# Research: Certified Cinematic Translation Quality Engine

**Feature**: `001-translation-quality`
**Date**: 2026-03-02
**Status**: Complete — all NEEDS CLARIFICATION resolved

---

## 1. AI/NLP Backend Selection

**Unknown**: Spec Assumption 3 — the AI/NLP backend is deferred to planning.

**Decision**: Reuse the existing **OpenRouter API** (`httpx` + `OPENROUTER_MODEL` env var, default `anthropic/claude-3.5-sonnet`) already used by `translator.py`.

**Rationale**:
- Already integrated, configured, and battle-tested in the pipeline — zero new dependency surface
- OpenRouter gives model-agnostic routing; the quality prompt model can differ from the translation model via a separate env var (`OPENROUTER_QUALITY_MODEL`) without code changes
- Claude-3.5-Sonnet performs exceptionally on Arabic linguistic tasks; its instruction-following is precise enough for structured JSON change-manifest output
- Constitution Principle V (YAGNI): no new external service needed

**Alternatives considered**:
- Dedicated Arabic NLP libraries (CAMeL Tools, Farasa): excellent for morphological analysis but require self-hosting, have no quality-rewriting capability, and add a large dependency
- Google Translate API / DeepL: translation-only; no quality scoring or cultural nuance correction capability
- Fine-tuned local model: no GPU infrastructure on this WordPress hosting context; out of scope

**Integration pattern**: The engine sends a two-step OpenRouter call per job — (1) quality assessment + scoring, (2) rewriting with change manifest — both as structured JSON responses using `response_format: { type: "json_object" }`.

---

## 2. Quality Scoring Rubric

**Unknown**: What does "quality score 0–100" actually measure, and how is it reproducible?

**Decision**: A **weighted rubric with four dimensions**, evaluated by the LLM in a single structured assessment call:

| Dimension | Weight | Description |
|---|---|---|
| Literal Translation Index | 30% | Frequency of Arabic text that follows source-language grammar instead of Arabic-native grammar |
| Cultural Fidelity | 30% | Correct conveyance of cinematic idioms, culturally-loaded terms, and connotation — not just denotation |
| Register Appropriateness | 20% | Match between tone (formal/informal) and declared content type |
| Lexical Fluency | 20% | Natural word choice, absence of calques, smooth sentence flow |

Score = Σ(dimension_score × weight), where each dimension is scored 0–100. Total is rounded to the nearest integer.

**Reproducibility**: The assessment prompt includes the rubric definition verbatim and uses `temperature: 0` for deterministic output. Same input + same model version → same score. Score drift across model upgrades is tracked in the AuditLog (`model_version` field added to schema).

**Alternatives considered**:
- BLEU/METEOR/ChrF metrics against reference translations: requires a ground-truth corpus (not yet built); deferred to SC-006 validation phase post-launch
- Rule-based heuristics (n-gram pattern matching for literal renderings): brittle for Arabic due to morphological richness; insufficient for cultural nuance dimension

---

## 3. Async Queue Architecture for Large Articles (2,000–5,000 words)

**Unknown**: How should async job processing be implemented within the existing WordPress + Python hybrid architecture?

**Decision**: **WordPress Action Scheduler** (bundled with WooCommerce, available standalone) for the PHP/WP side; direct Python invocation for the CLI path.

**Rationale**:
- Action Scheduler is the WordPress-native, battle-tested async job queue already used by major plugins (WooCommerce, Gravity Forms). No external queue infrastructure (Redis, RabbitMQ) needed.
- For the manual editor invocation path: the WP REST endpoint schedules an Action Scheduler job and returns `202 Accepted` immediately with a job ID. The editor polls a status endpoint.
- For the automatic pipeline path (post Python scrape → Python translator → WP import): async jobs above the 2,000-word threshold are scheduled during the import REST callback and processed by WP-CLI cron or WP cron.
- Constitution Principle V: minimal new infrastructure; reuses WP's existing scheduling primitives.

**Alternatives considered**:
- Python-side async (asyncio + httpx): the quality engine call originates from PHP/WP (FR-012a auto mode), so a Python queue would require a separate long-running process → deployment complexity ruled out
- WP transients as a poor-man's queue: not reliable for deferred background execution; jobs can be lost
- External Redis queue: adds an external dependency with no other justification in this project

**Timeout configuration**:
- Synchronous path (≤2,000 words): 45-second PHP `httpx` timeout (30s for API + 15s buffer)
- Async path (>2,000 words): no timeout on the scheduled action; Action Scheduler handles retries (max 3 attempts, 10-minute intervals)

---

## 4. Data Persistence: WordPress Custom Tables vs Post Meta

**Unknown**: Where are TranslationJobs and AuditLog entries stored?

**Decision**: **Two dedicated custom database tables** (`{prefix}toc_translation_jobs` and `{prefix}toc_audit_log`), created on plugin activation via `dbDelta()`.

**Rationale**:
- Post meta is unsuitable for multi-job-per-article history (FR-018), structured querying, and cross-article admin views (FR-017 review queue filtering)
- Custom tables allow proper indexed queries on article ID, status, score, and date — essential for the filterable review queue (FR-017) and audit log export (FR-015)
- `dbDelta()` is the WordPress-idiomatic schema migration mechanism; compatible with multisite and existing deployment patterns
- Constitution Principle II: clear, documented data architecture; no silent data mixing in post meta

**Alternatives considered**:
- WordPress posts custom post type for TranslationJobs: semantically wrong, pollutes the post table, no structured score/manifest columns
- External database: no precedent in this project; overkill for estimated volume

**Estimated volume**: Based on scraper output (assumed 50–200 articles/day), TranslationJobs table will grow at ~200 rows/day max. With 90-day retention, peak table size is ~18,000 rows — trivially small for MySQL/MariaDB.

---

## 5. Change Manifest Format

**Unknown**: Exactly what schema does a "structured change manifest" use?

**Decision**: JSON array stored in the `change_manifest` column (TEXT/LONGTEXT). Each element:

```json
{
  "id": "cr-001",
  "original": "قفز القطة فوق الجدار العالي",
  "revised": "اجتاز القط العقبة بخفة",
  "category": "structural",
  "rationale": "الجملة الأصلية تعكس بنية الجملة الإنجليزية (SVO literal). المراجعة تتبع الأسلوب العربي الطبيعي."
}
```

**Categories** (canonical, as per spec ChangeRecord entity): `cultural`, `tonal`, `grammatical`, `structural`.

**Displayed to**: editors via the WP admin review queue (FR-017) and the editor's per-job audit view (FR-015b). Never displayed publicly.

---

## 6. PHP–Python Integration Boundary

**Unknown**: Does the quality engine live in Python (alongside `translator.py`) or in PHP?

**Decision**: The **orchestration and storage layer lives entirely in PHP** (WordPress plugin). The **LLM API calls are made directly from PHP via `wp_remote_post()`** to OpenRouter — no Python subprocess invocation.

**Rationale**:
- The quality engine runs on already-imported WordPress posts (not on scraped raw data). PHP already has the post content; adding a Python subprocess call would require serializing content out and back with no benefit.
- `wp_remote_post()` with a 45s timeout is sufficient for synchronous calls; Action Scheduler handles async
- Keeps the architecture clean: Python = scraping + translation; PHP = WordPress integration + quality layer
- Constitution Principle II: single stated responsibility per module

**Python CLI path**: When an article is processed via the command-line pipeline, the Python `translator.py` output is POSTed to the WP import endpoint as today. The WP plugin then schedules or runs quality processing inline as part of the import callback (FR-012a).

---

## 7. WordPress Role Capability Mapping

**Decision** (resolved in clarification Q2):

| Action | Required Capability |
|---|---|
| Configure quality threshold | `manage_options` (admins only) |
| View full audit log | `manage_options` (admins only) |
| View own audit entries | `edit_posts` (editors and above) |
| Access review queue | `edit_posts` (editors and above) |
| Approve / reject in queue | `edit_posts` (editors and above) |
| Trigger manual quality run | `edit_posts` (editors and above) |

**Implementation**: All WP REST endpoints use `permission_callback` with `current_user_can()`. Admin-only pages use `add_menu_page()` with `manage_options`. Editor-facing pages use `add_submenu_page()` with `edit_posts`. Consistent with existing `tasteofcinemascraped_save_settings()` pattern in the plugin.
