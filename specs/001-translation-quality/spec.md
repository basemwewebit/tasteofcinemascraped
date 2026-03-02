# Feature Specification: Certified Cinematic Translation Quality Engine

**Feature Branch**: `001-translation-quality`
**Created**: 2026-03-01
**Status**: Draft
**Input**: User description: "خبير ترجمة معتمد (Certified Translation Expert) يتمتع بخبرة واسعة في الترجمة السينمائية والثقافية المتخصصة. مهمتك هي رفع جودة أي نص يتم تقديمه لك ليتطابق مع المعايير الأعلى لجودة الترجمة الاحترافية، مع التركيز على الدقة الثقافية والسياقية."

---

## Overview

This feature introduces a **Cinematic Translation Quality Engine** into the content pipeline of the plugin. The engine acts as a post-processing expert layer that reviews, scores, and rewrites automatically-generated Arabic translations of cinematic content (reviews, synopses, metadata, and editorial copy) to reach **superlative quality** — the standard expected from a certified, domain-specialized translation bureau.

The engine targets three quality dimensions explicitly stated in the feature description:
1. **Cultural Nuance** — correct rendering of implicit meanings, idiomatic expressions, and cinematic/regional cultural references.
2. **Superlative Quality** — fluent, natural prose free of literal-translation artifacts and linguistic errors.
3. **Cinematic Specialization** — tone and register appropriate to visual narrative contexts (dialogue, voice-over, editorial, synopsis).

---

## Clarifications

### Session 2026-03-01

- Q: If the AI backend is unavailable during automatic pipeline execution, should the pipeline block, degrade gracefully, or skip silently? → A: Degrade gracefully — store the article as a draft, automatically flag it for human review, and allow the pipeline to continue without quality correction. No data is lost; no article is silently published without correction.
- Q: Who can configure the publication quality threshold and view audit logs? → A: Split access — administrators configure the threshold and view all audit logs; editors can view only audit log entries for articles they personally submitted.
- Q: What is the maximum article size and how should the engine handle inputs above the synchronous 30-second budget? → A: Tiered model — articles ≤2,000 words are processed synchronously with a 30-second response; articles >2,000 and ≤5,000 words are queued asynchronously and the editor is notified on completion; articles exceeding 5,000 words are rejected with a clear error message.
- Q: How does a flagged article surface to the editor, and what actions can they take? → A: Dedicated WP admin review queue — a filterable admin page lists all flagged TranslationJobs; editors can approve-as-is, edit-then-approve, or reject (article returns to draft). No separate email notification required.
- Q: What happens when an editor re-submits an article that already has a completed TranslationJob? → A: New job always — each submission creates a fresh TranslationJob; all prior jobs for the same article are retained in the audit log, linked by article ID, forming a complete revision history.

---

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Editorial Review of a Scraped Translation (Priority: P1)

A content editor opens a scraped article that has been automatically translated from English to Arabic. The translation contains awkward literal renderings and culturally inappropriate expressions. The editor submits the text to the Quality Engine and receives a polished, publication-ready Arabic version with an explanation of the changes made.

**Why this priority**: This is the core, day-to-day editorial use case. Without this working reliably, no other quality dimension has value. It directly removes the manual re-translation burden from editors and is the primary ROI of the feature.

**Independent Test**: Can be fully tested by submitting a scraped translation to the engine and verifying the output prose is fluent, culturally appropriate, and free of literal-translation artifacts — without any other sub-feature being active.

**Acceptance Scenarios**:

1. **Given** a raw Arabic translation with at least one literal-translation artifact, **When** the editor submits it to the Quality Engine, **Then** the engine returns a revised version where the artifact is replaced by a natural Arabic equivalent, along with a structured list of changes made.
2. **Given** a translation containing a culturally foreign idiom rendered word-for-word, **When** the engine processes it, **Then** the output replaces the idiom with an equivalent Arabic expression that carries the same connotation.
3. **Given** a translation that is already fluent and accurate, **When** passed through the engine, **Then** the engine confirms quality meets the standard and returns the original text unchanged (no unnecessary modifications).
4. **Given** a processed translation has been flagged for human review (score below threshold or engine error), **When** the responsible editor opens the WP admin review queue, **Then** they see the flagged article listed with its pre- and post-correction text, quality score, and change manifest, and can choose to (a) **approve-as-is** (publishes the corrected version), (b) **edit-then-approve** (opens the article editor pre-loaded with the corrected text for further manual edits before publishing), or (c) **reject** (returns the article to draft status with a rejection note).

---

### User Story 2 — Cinematic Tone Calibration for Synopsis & Dialogue Content (Priority: P2)

A content manager is publishing a film synopsis alongside a dialogue excerpt. The automatically translated text mixes formal and colloquial registers inappropriately. The Quality Engine is invoked with a **content-type hint** (e.g., `synopsis`, `dialogue`, `review`, `editorial`) and returns a version calibrated to the correct cinematic register.

**Why this priority**: Cinematic text is uniquely tone-sensitive; a synopsis reads very differently from a character's spoken line. Tone errors visibly harm site credibility. This story delivers the "cinematic specialization" dimension of the feature.

**Independent Test**: Can be tested by submitting a synopsis and a dialogue excerpt with an appropriate content-type hint each, and verifying that the synopsis uses formal narrative tone while the dialogue uses natural spoken register.

**Acceptance Scenarios**:

1. **Given** a film synopsis submitted with the `synopsis` hint, **When** processed by the engine, **Then** the output uses a formal narrative register consistent with published Arabic film criticism.
2. **Given** a character dialogue excerpt submitted with the `dialogue` hint, **When** processed, **Then** the output uses a natural spoken tone that matches the character's apparent cultural context without over-formalizing.
3. **Given** no content-type hint is provided, **When** the engine processes the text, **Then** it applies a neutral editorial default and includes a note that a content-type hint would improve accuracy.

---

### User Story 3 — Translation Quality Scoring & Audit Trail (Priority: P3)

A site administrator wants to audit the quality of translations produced over a given period. The Quality Engine surface provides a **quality score** for each translation it processes, along with a structured log of issues found and corrections applied. The administrator can review these logs to understand which content types produce the most errors and where the pipeline needs improvement.

**Why this priority**: Scoring and audit trails are not required for individual translation quality but are essential for pipeline-level quality management and for justifying the value of the engine to stakeholders. They also support continuous improvement of the upstream translation step.

**Independent Test**: Can be tested by running several translations through the engine and verifying that each produces a numerical quality score, a before/after diff, and a categorized list of issue types (cultural, tonal, grammatical).

**Acceptance Scenarios**:

1. **Given** a translation is processed by the engine, **When** the result is returned, **Then** it includes a quality score on a 0–100 scale and a breakdown of issue categories identified.
2. **Given** multiple translations have been processed, **When** the administrator views the audit log, **Then** they can filter by content type, date range, and quality score threshold.
3. **Given** a translation scores below a configurable quality threshold, **When** saved to the system, **Then** it is flagged for mandatory human review before publication.

---

### Edge Cases

- **Engine unavailability (resolved)**: If the AI backend is unreachable or times out, the article is stored as a WordPress draft and flagged for human review; the ingestion pipeline continues. No article is silently published without quality correction.
- What happens when the input text contains inline segments in a language other than Arabic or English (e.g., a French film title embedded mid-paragraph)?
- How does the engine handle very short inputs (fewer than 10 words) where context is insufficient for cultural calibration?
- The target register is Modern Standard Arabic (Fusha) by default (see Assumptions). If the text contains strong dialectal markers, the engine flags this in the change manifest rather than altering the dialect silently.
- How does the system behave when the source language of the original article is ambiguous?
- What happens when a proper noun (film title, director name, character name) is mistakenly "translated" by the upstream system — the engine flags it in the change manifest (see FR-007); it does not silently restore or alter it.
- **Article exceeds 5,000 words (resolved)**: The engine rejects the input with a user-facing error message specifying the word count and the 5,000-word limit. The article is not stored as a job; the editor must split it before resubmission.

---

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The engine MUST accept Arabic text input (a translated article, synopsis, dialogue excerpt, or editorial copy) and return a quality-improved version of that text.
- **FR-002**: The engine MUST support an optional `content_type` parameter accepting values: `synopsis`, `dialogue`, `review`, `editorial`. When omitted, a neutral editorial default MUST be applied.
- **FR-003**: The engine MUST detect and correct literal-translation artifacts — phrases that are grammatically correct Arabic but read as word-for-word renderings from a foreign language structure.
- **FR-004**: The engine MUST detect and adapt culturally foreign idioms, expressions, and references to their closest Arabic cinematic or cultural equivalents, annotating each change.
- **FR-005**: The engine MUST produce a **change manifest** alongside every revised text — a structured list of (original segment → revised segment, change category, rationale).
- **FR-006**: The engine MUST assign a **quality score** (0–100) to each input text before processing, reflecting the degree of quality issues detected. The score MUST be reproducible for the same input.
- **FR-007**: The engine MUST preserve all proper nouns — film titles (original and transliterated), director names, character names, and place names — exactly as they appear in the original, unless they are demonstrably mistranslated (in which case they MUST be flagged in the change manifest, not silently altered).
- **FR-008**: The engine MUST preserve all factual claims (dates, awards, box-office figures, cast lists) without modification; factual correction is explicitly out of scope.
- **FR-009**: The engine MUST NOT alter text that already meets the quality threshold (score ≥ 85); in such cases it MUST return the original text with a confirmation status.
- **FR-010**: The engine MUST log every processing event (input hash, content type, score before, score after, number of changes, timestamp) to a persistent audit trail accessible to administrators.
- **FR-011**: The system MUST allow administrators to configure a **publication quality threshold** (default: 70). Processed texts scoring below this threshold after correction MUST be flagged for human review before being written to the WordPress database.
- **FR-012**: The engine MUST be invokable both (a) automatically as part of the content ingestion pipeline (post-scrape, post-translation) and (b) manually by editors on demand for any stored article.
- **FR-013**: If the engine's backend is unavailable or fails to respond within the defined timeout during automatic pipeline execution, the system MUST (a) store the article as a WordPress draft, (b) flag the corresponding TranslationJob with status `engine-unavailable`, (c) notify the responsible editor for manual review, and (d) allow the remainder of the ingestion pipeline to continue uninterrupted. No article may be auto-published without a completed quality processing step.
- **FR-014**: Access to the **publication quality threshold** configuration MUST be restricted to users holding the WordPress `manage_options` capability (administrators). Editors MUST NOT be able to view or modify the threshold setting.
- **FR-015**: Access to the **audit log** MUST be split by role: (a) administrators can view, filter, and export the complete audit log across all articles and all users; (b) editors can view only audit log entries associated with TranslationJobs they personally initiated. No cross-editor visibility is permitted for non-administrators.
- **FR-016**: The engine MUST apply a tiered processing model based on input word count: (a) inputs ≤2,000 words MUST be processed synchronously and return a result within 30 seconds; (b) inputs >2,000 words and ≤5,000 words MUST be queued for asynchronous processing, with the initiating editor notified upon completion; (c) inputs exceeding 5,000 words MUST be rejected immediately with a clear, user-facing error message stating the word count and the limit — no TranslationJob is created for rejected inputs.
- **FR-017**: The system MUST provide a dedicated **WP admin review queue** page, accessible to editors and administrators, listing all TranslationJobs with status `flagged-for-review` or `engine-unavailable`. The queue MUST be filterable by status, content type, date range, and quality score. For each listed job, the editor MUST be able to: (a) **approve-as-is** — publishes the engine-corrected version and sets job status to `human-approved`; (b) **edit-then-approve** — opens the WP article editor pre-loaded with the corrected text, allowing manual edits before publishing; (c) **reject** — returns the article to WordPress draft status and sets job status to `rejected`, with an optional free-text rejection note.
- **FR-018**: Every manual or automatic engine invocation MUST create a **new, independent TranslationJob** regardless of whether prior jobs exist for the same article. Prior jobs MUST NOT be overwritten or deleted. All jobs for a given article MUST be retrievable together via the shared article ID, forming an immutable revision history. Re-submission MUST be permitted at any time, including when a prior job is in a non-terminal state (`pending` or `processing`), but the review queue MUST display all active (non-terminal) jobs for an article with a visible indicator that multiple jobs exist.

### Key Entities

- **TranslationJob**: Represents a single engine invocation. Attributes: job ID, **article ID** (WordPress post ID; multiple jobs may share the same article ID), input text, content type, initiating user ID, pre-score, post-score, change manifest, processing timestamp, status. **Status lifecycle**: `pending` → `processing` → [`auto-approved` | `flagged-for-review` | `engine-unavailable`] → (if flagged) [`human-approved` | `rejected`]. Transitions to `auto-approved` occur when post-score ≥ publication threshold; transitions to `flagged-for-review` when post-score < threshold; `engine-unavailable` when backend fails. `human-approved` and `rejected` are terminal states set via the review queue. Multiple jobs may exist per article; all are retained immutably.
- **ChangeRecord**: A single detected and corrected item within a TranslationJob. Attributes: original segment, revised segment, change category (cultural / tonal / grammatical / structural), rationale (plain language).
- **QualityThreshold**: A configurable system-level setting governing minimum score for automatic publication. Attributes: threshold value, last modified by, last modified date.
- **AuditLog**: Immutable append-only record of all TranslationJob events. Attributes: job reference, initiating user ID, timestamp, pre-score, post-score, change count, engine status. Visibility: full log visible to administrators only; per-user filtered view visible to the initiating editor.

---

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Editors report that at least **80% of engine-processed translations** require no further manual editing before publication, measured via a post-launch editorial survey after 30 days.
- **SC-002**: The average quality score of published Arabic articles increases from the pre-feature baseline by **at least 25 points** (on the 0–100 scale) within 60 days of full pipeline integration.
- **SC-003**: An editor submitting an article of **≤2,000 words** receives a fully corrected, annotated output **within 30 seconds**. An article of **>2,000 and ≤5,000 words** is queued asynchronously and the editor receives a completion notification; the queued processing MUST complete within **5 minutes** under normal load. Articles exceeding 5,000 words are rejected before a job is created.
- **SC-004**: The engine correctly preserves proper nouns and factual claims with **zero silent alterations**, verified by automated regression tests against a curated test corpus.
- **SC-005**: Texts flagged by the engine as below the publication quality threshold are reviewed and either approved or rejected by a human editor **within 24 hours** of flagging — supported by the audit trail feature.
- **SC-006**: The false-positive rate (engine flagging good text as needing correction) is **below 10%**, measured against a hand-annotated ground-truth corpus of 100 pre-validated translations.

---

## Assumptions

1. The plugin already has an active translation pipeline that produces raw Arabic text from English source articles; the Quality Engine is a **post-processing layer**, not a replacement for that pipeline.
2. The target Arabic register is **Modern Standard Arabic (Fusha)** by default, unless a future dialect-specific extension is requested. Regional dialect support is out of scope for this feature.
3. The engine's AI/NLP backend will be configured and available via the existing plugin infrastructure; the choice of underlying model or API is a planning-phase decision and intentionally excluded from this specification.
4. "Superlative quality" is operationally defined as a quality score of ≥ 85/100 on the engine's own scoring rubric, which must be calibrated against human expert review.
5. The change manifest is intended for editorial transparency, not for end-user display; it will not appear on the public-facing site.
6. Audit logs are retained for a minimum of 90 days, consistent with the project's standard data-retention practice.
