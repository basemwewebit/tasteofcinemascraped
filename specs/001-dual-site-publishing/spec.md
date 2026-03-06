# Feature Specification: Dual Site Publishing

**Feature Branch**: `001-dual-site-publishing`  
**Created**: 2026-03-05  
**Status**: Draft  
**Input**: User description: "بدي كل عمليات الاضافة تصير ع كلا الموقعين  هون https://tasteofcinemaarabi.com http://tasteofcinemaarabi.test/ يعني بس نعمل سكرابد وترجمة وتقييم للترجمة ننشر البوست لوكالي و ع الموقع"
## Clarifications

### Session 2026-03-05

- Q: Post Updates (Data Consistency) → A: No, dual publishing is strictly a one-time event upon initial creation.
- Q: Remote Taxonomy (Categories/Tags) → A: Map missing terms to a predefined default category (e.g., 'Uncategorized').

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Automated Dual Publishing After Evaluation (Priority: P1)

As an editor or automated agent, when a scraped post's translation is generated and evaluated as passing, I want the post to be published simultaneously on the local testing environment and the live production website, so that content is seamlessly available across both platforms without manual duplication.

**Why this priority**: Core functionality requested by the user. Ensuring content reaches the live site automatically after passing quality checks saves time and reduces administrative overhead.

**Independent Test**: Can be fully tested by triggering a post translation and evaluation process, then verifying the post appears on both defined URLs with identical content and metadata.

**Acceptance Scenarios**:

1. **Given** a successfully scraped and translated post, **When** its translation evaluation step passes, **Then** the post is published to the local WordPress instance.
2. **Given** a successfully scraped and translated post, **When** its translation evaluation step passes, **Then** the post is also transmitted and published to the remote production WordPress instance.
3. **Given** a post that fails the evaluation step, **When** the workflow attempts to proceed, **Then** the post is NOT published to either site.

### Edge Cases

- What happens when the remote site (tasteofcinemaarabi.com) is down or unreachable during the publishing attempt?
- How does system handle media/image uploads to ensure image URLs are localized and point to the correct internal path for both environments?
- What happens if the publishing succeeds locally but fails on the remote server? Is there a retry mechanism?

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST automate the publishing of posts to a remote environment (`https://tasteofcinemaarabi.com`).
- **FR-002**: System MUST continue publishing posts to the local environment (`http://tasteofcinemaarabi.test/`).
- **FR-003**: System MUST trigger the dual publishing workflow ONLY AFTER successful scraping, translation, and translation evaluation.
- **FR-004**: System MUST handle media attachments (featured images) ensuring they are properly uploaded and referenced on both target environments instead of linking to external sources.
- **FR-005**: System MUST authenticate securely with the remote WordPress site using Application Passwords.
- **FR-006**: System MUST implement error handling if the remote publishing fails, logging the error and automatically retrying on a schedule via WP Cron.
- **FR-007**: System MUST treat dual publishing as a strict one-time event upon initial post creation; subsequent local updates MUST NOT sync to the remote environment.
- **FR-008**: System MUST map any categories or tags present in the payload that do not exist on the remote site to a predefined default category (e.g., 'Uncategorized').

### Key Entities *(include if feature involves data)*

- **Post Payload**: The structured post data (title, content, categories, featured image) standardized for dual transmission.
- **Publishing Log**: A record of successful/failed publishing actions across local and remote instances.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: 100% of posts that pass translation evaluation are successfully published to both local and live sites within 2 minutes of evaluation.
- **SC-002**: 0% of translation-failed posts are published.
- **SC-003**: Media links and content formatting are correctly localized on both respective sites on 100% of published posts.
