# Feature Specification: Run Scraper From WP Admin

**Feature Branch**: `001-wp-admin-scraper`  
**Created**: 2026-03-03  
**Status**: Draft  
**Input**: User description: "في طريقة نخلي هاي العملية من جوا ووردبريس من جوا الادمن... او سنة طبعا او شهر"

## Clarifications

### Session 2026-03-03
- Q: Progress & Log Display for Async Tasks → A: Real-time AJAX polling (live updates of progress and logs without page reload).
- Q: Python Environment Management → A: Assume pre-configured environment (fail fast if `.venv` or dependencies are missing).

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Scrape by Specific URL (Priority: P1)

As an Administrator, I want to be able to input a specific article URL into an admin interface and run the scraper, so that I don't have to use the server terminal to import individual articles.

**Why this priority**: Implements the direct translation of the core manual terminal workflow into a user-friendly UI for the most common use case (single article).

**Independent Test**: Can be fully tested by entering a valid Taste of Cinema URL into the new admin page and verifying that the corresponding article is successfully scraped, processed, and imported as a draft post.

**Acceptance Scenarios**:

1. **Given** the scraper admin interface, **When** I enter a valid URL and click "Run Scraper", **Then** the system executes the scraping process with the provided URL.
2. **Given** the scraper is running, **When** the process completes, **Then** I see a success message indicating the article was imported.
3. **Given** the scraper admin interface, **When** I enter an invalid URL and click "Run Scraper", **Then** the system displays an error message requesting a valid URL.

---

### User Story 2 - Scrape by Year/Month (Priority: P2)

As an Administrator, I want to be able to select a specific year and optionally a month, and run the scraper to import all articles from that time period.

**Why this priority**: Enables bulk importing of articles by time period, which is significantly more efficient than scraping URL by URL.

**Independent Test**: Can be fully tested by selecting a year and initiating the scraper, then verifying that multiple articles from that year are successfully queued and scraped.

**Acceptance Scenarios**:

1. **Given** the scraper admin interface, **When** I input a year (e.g., 2014) and click "Run Scraper", **Then** the system executes the scraping process with the corresponding year as the target argument.
2. **Given** the scraper admin interface, **When** I input a year and a month, then click "Run Scraper", **Then** the system executes the scraping process parameterized for that specific month.
3. **Given** a year-based scraping process, **When** it completes, **Then** I see a summary of the imported posts.

---

### Edge Cases

- What happens when the underlying Python execution environment (`.venv`) is missing or dependencies aren't installed? (Expected: Fail fast with a clear error message instructing the admin to configure the server).
- How does the system handle timeouts for large batches (like a full year) if the process takes longer than the server's maximum request lifetime?
- What happens if the scraper encounters an error midway during a bulk import?

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST provide a settings page or interface in the Admin dashboard dedicated to the Scraper tool.
- **FR-002**: System MUST allow the user to input a single target URL and execute the underlying scraper utility.
- **FR-003**: System MUST allow the user to input a specific Year (and optionally a Month) to execute the scraper utility for bulk importing.
- **FR-004**: System MUST check for a valid pre-configured Python runtime environment and fail gracefully (with clear instructions) if the environment is not prepared. It MUST NOT attempt to run `pip install` automatically.
- **FR-005**: System MUST capture the output (success, failure, or progress logs) of the execution and display real-time feedback to the Admin user via automatic polling without requiring manual page reloads.
- **FR-006**: System MUST execute the batch scraping process in a way that prevents application timeouts for large imports by executing them as an asynchronous background task, allowing the admin to navigate away and see progress later.

### Key Entities 

- **Scraper Execution**: Represents a single run instance of the web scraper, characterized by its parameters (URL, Year, Month), status (running, completed, failed), and output logs.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Administrators can trigger the scraping process for a single URL entirely from the Admin Dashboard without needing direct server terminal access.
- **SC-002**: The scraping process activated via the admin dashboard successfully results in new articles being drafted in the system in 100% of valid executions.
- **SC-003**: Bulk scraping by year and month can be triggered from the admin dashboard and successfully imports the expected volume of articles without timing out the web server.
