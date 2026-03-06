# Data Model: Dual Site Publishing

## Overview
The dual publishing system operates primarily on the existing WordPress `WP_Post` and `WP_Term` data structures, but requires a standardized payload format to reliably transmit data to the remote REST API.

## Entities

### `PostPayload` (DTO)
Represents the structured data sent to the remote site.

**Fields**:
- `title` (string): The translated post title.
- `content` (string): The translated post content (HTML).
- `status` (string): The target post status (e.g., `'publish'`).
- `categories` (array of strings): The category names to assign.
- `tags` (array of strings): The tag names to assign.
- `featured_media` (integer|null): The remote attachment ID (requires pre-uploading the image).
- `meta` (object): Any relevant custom fields.

**Validation Rules**:
- Title and content MUST NOT be empty.
- If terms (categories/tags) are provided, the remote system MUST map unknowns to a default fallback.

### `PublishRetryJob`
Represents a queued attempt to retry a failed publication.

**Fields (stored as WP option or custom table depending on queue implementation)**:
- `local_post_id` (integer): ID of the post that failed to sync.
- `attempts` (integer): Number of failed attempts.
- `last_error` (string): The error message from the last failure.
- `next_run` (timestamp): Scheduled time for the next attempt.
