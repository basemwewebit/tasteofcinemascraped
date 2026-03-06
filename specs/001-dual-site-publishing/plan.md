# Implementation Plan: Dual Site Publishing

**Branch**: `001-dual-site-publishing` | **Date**: 2026-03-05 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/specs/001-dual-site-publishing/spec.md`

## Summary

This feature automates publishing translated posts to both the local testing environment (`http://tasteofcinemaarabi.test/`) and the live remote production site (`https://tasteofcinemaarabi.com`). The synchronization is a strict one-time event upon initial post creation, bypassing subsequent local edits. Missing taxonomies are mapped to 'Uncategorized', and remote authentication utilizes Application Passwords. Failed remote publish attempts are queued and automatically retried via WP Cron.

## Technical Context

**Language/Version**: PHP 8.3+
**Primary Dependencies**: WordPress Core, WordPress REST API
**Storage**: WordPress Database (Posts, PostMeta, Terms)
**Testing**: PHPUnit / WP Mock for unit tests
**Target Platform**: Linux server running WordPress
**Project Type**: WordPress Plugin
**Performance Goals**: Publish overhead < 2s; Cron retries should not block site requests
**Constraints**: Requires Application Password configured on remote site; MUST NOT sync updates (one-way, one-time post creation only)
**Scale/Scope**: Handles individual post creation events async/sync depending on workflow

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

- **I. Quality, Reliability & Security**: Application Passwords are used over basic auth, satisfying the security principle. API requests must use `wp_remote_post` with proper timeout and SSL verification.
- **II. Clear Architecture & Editorial Integrity**: Dual publishing will be clearly encapsulated in its own service class (`RemotePublisherService`).
- **III. Code Standards, Review & Validation**: PHP code will follow PSR-12 and use strict types.
- **IV. Breaking-Change Policy**: N/A - This is a net-new feature, internal to the scraping workflow.
- **V. Long-Term Maintainability**: Utilizing WP Cron for retries avoids complex external queue dependencies, favoring built-in, maintainable solutions.

**Gate Evaluation**: PASS

## Project Structure

### Documentation (this feature)

```text
specs/001-dual-site-publishing/
├── plan.md              # This file
├── data-model.md        # Contract & Payload structure
├── contracts/           # API payload schemas
└── tasks.md             # Implementation steps
```

### Source Code

```text
src/
├── Services/
│   ├── RemotePublisherService.php      # Handles WP REST API requests to remote
│   └── PublishRetryQueue.php           # WP Cron wrapper for retries
├── Models/
│   └── PostPayload.php                 # DTO for standardizing the outgoing post shape
└── Providers/
    └── DualPublishingServiceProvider.php # Wires hooks and dependencies
```

**Structure Decision**: The feature drops into the existing `src/` plugin directory, utilizing a Services/Models pattern to isolate the REST communication from WordPress hooks.

## Complexity Tracking

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| Custom Retry Queue (PublishRetryQueue) | Network failures during remote publish are common. | Failing silently violates the spec; relying on manual user intervention was explicitly rejected in the clarification phase. Built-in WP Cron handles this elegantly without heavy infra. |

