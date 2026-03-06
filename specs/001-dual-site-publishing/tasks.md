# Tasks: Dual Site Publishing

**Input**: Design documents from `/specs/001-dual-site-publishing/`
**Prerequisites**: `plan.md` (required), `spec.md` (required for user stories), `data-model.md`, `contracts/api.md`, `quickstart.md`

**Organization**: Tasks are grouped by user story to enable independent implementation and testing of each story.

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Project initialization and basic structure

- [x] T001 Create project structure per implementation plan (src/Services, src/Models)
- [x] T002 Update plugin entrypoint to register DualPublishingServiceProvider

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Core infrastructure that MUST be complete before ANY user story can be implemented

**⚠️ CRITICAL**: No user story work can begin until this phase is complete

- [x] T003 Create `PostPayload` DTO model in `src/Models/PostPayload.php`
- [x] T004 Create `DualPublishingServiceProvider` in `src/Providers/DualPublishingServiceProvider.php`
- [x] T005 [P] Create empty `RemotePublisherService` in `src/Services/RemotePublisherService.php`
- [x] T006 [P] Create empty `PublishRetryQueue` in `src/Services/PublishRetryQueue.php`

**Checkpoint**: Foundation ready - user story implementation can now begin

---

## Phase 3: User Story 1 - Automated Dual Publishing After Evaluation (Priority: P1) 🎯 MVP

**Goal**: Automatically publish a translated post to the remote site after it passes evaluation locally.

**Independent Test**: Trigger a post translation evaluation success hook locally; verify the post arrives on the remote site and includes the featured image. Turn off WiFi; trigger the hook again; verify WP Cron queues it for retry.

### Implementation for User Story 1

- [x] T007 [US1] Implement `RemotePublisherService::uploadMedia()` to handle featured image sync to remote in `src/Services/RemotePublisherService.php`
- [x] T008 [US1] Implement `RemotePublisherService::publishPost()` using `wp_remote_post` with `PostPayload` data and Application Passwords auth in `src/Services/RemotePublisherService.php`
- [x] T009 [US1] Implement `PublishRetryQueue::scheduleRetry()` to queue failed payloads in `src/Services/PublishRetryQueue.php`
- [x] T010 [US1] Implement `PublishRetryQueue::processQueue()` WP Cron callback to attempt re-publishing in `src/Services/PublishRetryQueue.php`
- [x] T011 [US1] Register WP Cron schedule for retries in `DualPublishingServiceProvider` (`src/Providers/DualPublishingServiceProvider.php`)
- [x] T012 [US1] Hook into the translation evaluation success action to instantiate `PostPayload` and trigger `RemotePublisherService::publishPost()`, catching errors to queue via `PublishRetryQueue::scheduleRetry()` in `DualPublishingServiceProvider` (`src/Providers/DualPublishingServiceProvider.php`)

**Checkpoint**: At this point, User Story 1 should be fully functional and testable independently

---

## Phase 4: Polish & Cross-Cutting Concerns

**Purpose**: Improvements that affect multiple user stories

- [x] T013 Setup PHPUnit testing scaffold if not exists
- [x] T014 [P] Add unit tests for `PostPayload` serialization in `tests/Unit/PostPayloadTest.php`
- [x] T015 Add unit tests / WP Mock tests for `RemotePublisherService` network handling in `tests/Unit/RemotePublisherServiceTest.php`
- [x] T016 Add unit tests for WP Cron queuing logic in `tests/Unit/PublishRetryQueueTest.php`

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies - can start immediately
- **Foundational (Phase 2)**: Depends on Setup completion - BLOCKS all user stories
- **User Stories (Phase 3+)**: All depend on Foundational phase completion
- **Polish (Final Phase)**: Depends on all desired user stories being complete

### User Story Dependencies

- **User Story 1 (P1)**: Can start after Foundational (Phase 2).

### Parallel Opportunities

- Foundational service class generation (T005, T006) can happen in parallel.
- Unit testing setup (T014) can run in parallel with other polish tasks.

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Complete Phase 1: Setup
2. Complete Phase 2: Foundational (CRITICAL - blocks all stories)
3. Complete Phase 3: User Story 1
4. **STOP and VALIDATE**: Test User Story 1 independently by triggering the evaluation hook.
5. Setup remote config variables in `.env` per `quickstart.md`.
6. Finalize with Phase 4 Polish tasks.
