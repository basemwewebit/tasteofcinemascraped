# REST API Contracts: Translation Quality Engine

**Feature**: `001-translation-quality`
**Date**: 2026-03-02
**Base namespace**: `tasteofcinemascraped/v1` (existing plugin namespace)

All endpoints follow the existing plugin authentication pattern: `X-Tasteofcinema-Secret` header or `secret` query param for machine clients; WordPress cookie auth (`nonce`) for admin UI clients.

---

## Endpoint 1: Submit Quality Job (manual on-demand)

**Route**: `POST /wp-json/tasteofcinemascraped/v1/quality/run`

**Permission**: `edit_posts` capability (editors and administrators)

**Request Body** (JSON):

```json
{
  "post_id": 42,
  "content_type": "review"
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `post_id` | integer | ✅ | WordPress post ID to process |
| `content_type` | string | ❌ | One of `synopsis`, `dialogue`, `review`, `editorial`. Defaults to `editorial`. |

**Response — Synchronous path (≤2,000 words)** `201 Created`:

```json
{
  "job_id": 18,
  "status": "auto-approved",
  "pre_score": 52,
  "post_score": 88,
  "change_count": 7,
  "processing_mode": "sync",
  "change_manifest": [
    {
      "id": "cr-001",
      "original": "...",
      "revised": "...",
      "category": "cultural",
      "rationale": "..."
    }
  ]
}
```

**Response — Async path (>2,000 words)** `202 Accepted`:

```json
{
  "job_id": 19,
  "status": "pending",
  "processing_mode": "async",
  "poll_url": "/wp-json/tasteofcinemascraped/v1/quality/jobs/19"
}
```

**Error Responses**:

| HTTP | Code | Condition |
|------|------|-----------|
| `400` | `toc_word_count_exceeded` | Input exceeds 5,000 words |
| `400` | `toc_invalid_content_type` | `content_type` not in allowed set |
| `404` | `toc_post_not_found` | `post_id` does not exist |
| `403` | `toc_forbidden` | Caller lacks `edit_posts` capability |
| `503` | `toc_engine_unavailable` | AI backend unreachable; job created with `engine-unavailable` status |

---

## Endpoint 2: Get Job Status / Result

**Route**: `GET /wp-json/tasteofcinemascraped/v1/quality/jobs/{job_id}`

**Permission**: `edit_posts` for own jobs; `manage_options` to view any job.

**Response** `200 OK`:

```json
{
  "job_id": 19,
  "post_id": 42,
  "content_type": "review",
  "status": "flagged-for-review",
  "pre_score": 41,
  "post_score": 66,
  "change_count": 12,
  "word_count": 3100,
  "model_version": "anthropic/claude-3.5-sonnet",
  "created_at": "2026-03-02T00:01:00Z",
  "updated_at": "2026-03-02T00:05:43Z",
  "change_manifest": [ ... ]
}
```

**Error Responses**:

| HTTP | Code | Condition |
|------|------|-----------|
| `404` | `toc_job_not_found` | Job ID does not exist |
| `403` | `toc_forbidden` | Editor requesting another user's job |

---

## Endpoint 3: Resolve a Flagged Job (Review Queue Action)

**Route**: `POST /wp-json/tasteofcinemascraped/v1/quality/jobs/{job_id}/resolve`

**Permission**: `edit_posts` for own jobs; `manage_options` to resolve any job.

**Request Body** (JSON):

```json
{
  "action": "approve",
  "rejection_note": ""
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `action` | string | ✅ | One of `approve` (approve-as-is), `reject` |
| `rejection_note` | string | ❌ | Free-text note, stored only when `action` is `reject` |

**Response** `200 OK`:

```json
{
  "job_id": 19,
  "status": "human-approved",
  "post_status": "publish"
}
```

**Error Responses**:

| HTTP | Code | Condition |
|------|------|-----------|
| `409` | `toc_job_already_resolved` | Job is already in a terminal state |
| `400` | `toc_invalid_action` | `action` not `approve` or `reject` |

---

## Endpoint 4: List Audit Log (Admin)

**Route**: `GET /wp-json/tasteofcinemascraped/v1/quality/audit`

**Permission**: `manage_options` for full log; `edit_posts` for own-only log (filtered automatically by user).

**Query Parameters**:

| Param | Type | Description |
|-------|------|-------------|
| `post_id` | integer | Filter by article |
| `status` | string | Filter by job status |
| `content_type` | string | Filter by content type |
| `from` | string | ISO 8601 date — start of range |
| `to` | string | ISO 8601 date — end of range |
| `min_score` | integer | Minimum post_score filter |
| `max_score` | integer | Maximum post_score filter |
| `per_page` | integer | Default 20, max 100 |
| `page` | integer | 1-based page number |

**Response** `200 OK`:

```json
{
  "total": 142,
  "page": 1,
  "per_page": 20,
  "jobs": [
    {
      "job_id": 18,
      "post_id": 42,
      "post_title": "أفضل أفلام الجريمة",
      "status": "auto-approved",
      "pre_score": 52,
      "post_score": 88,
      "content_type": "review",
      "created_at": "2026-03-02T00:01:00Z"
    }
  ]
}
```

---

## Endpoint 5: Quality Settings (Admin Only)

**Route**: `GET /wp-json/tasteofcinemascraped/v1/quality/settings`
**Route**: `PUT /wp-json/tasteofcinemascraped/v1/quality/settings`

**Permission**: `manage_options` only.

**GET Response / PUT Request Body**:

```json
{
  "quality_threshold": 70,
  "quality_model": "anthropic/claude-3.5-sonnet",
  "audit_retention_days": 90,
  "auto_run_on_import": true
}
```

| Field | Validation |
|-------|-----------|
| `quality_threshold` | Integer 0–100 |
| `quality_model` | Non-empty string (passed to OpenRouter as model ID) |
| `audit_retention_days` | Integer 7–365 |
| `auto_run_on_import` | Boolean |
