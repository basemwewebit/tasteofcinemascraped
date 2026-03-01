<!--
SYNC IMPACT REPORT
==================
Version change: [TEMPLATE / unversioned] → 1.0.0
Bump rationale: MAJOR — first full population of constitution from blank template.
                All placeholder tokens replaced; project identity, principles,
                and governance formally established for the first time.

Modified principles:
  [PRINCIPLE_1_NAME] → I. Quality, Reliability & Security
  [PRINCIPLE_2_NAME] → II. Clear Architecture & Editorial Integrity
  [PRINCIPLE_3_NAME] → III. Code Standards, Review & Validation
  [PRINCIPLE_4_NAME] → IV. Breaking-Change Policy
  [PRINCIPLE_5_NAME] → V. Long-Term Maintainability
  [SECTION_2_NAME]  → Collaboration & Communication
  [SECTION_3_NAME]  → Data & Security Responsibilities

Added sections: Collaboration & Communication, Data & Security Responsibilities
Removed sections: None (all template sections retained and populated)

Templates requiring updates:
  ✅ .specify/templates/plan-template.md
     — "Constitution Check" gate already generic; aligns with the 5 principles above.
  ✅ .specify/templates/spec-template.md
     — Mandatory sections (User Scenarios, Requirements, Success Criteria) align with
       Quality and Editorial Integrity principles. No changes needed.
  ✅ .specify/templates/tasks-template.md
     — Existing task categories (Setup, Foundational, User Stories, Polish) cover
       security hardening, documentation, and validation steps as required.
  ⚠ .specify/templates/checklist-template.md
     — Not yet reviewed; recommend manual verification that checklist items reference
       the six principles defined here.

Deferred TODOs:
  — RATIFICATION_DATE set to today (2026-03-01); if this project was informally governed
    prior to this document, update to the actual founding date.
-->

# Taste of Cinema Scraped WP Constitution

## Core Principles

### I. Quality, Reliability & Security

All contributions to this plugin MUST prioritize reliability, security, and a high standard
of correctness above speed of delivery. Features that introduce regressions, expose
attack surfaces, or degrade editorial output quality MUST NOT be merged regardless of
how minor the change appears.

**Non-negotiable rules:**
- Every merged change MUST leave the plugin in a working, deployable state.
- Security vulnerabilities MUST be treated as blocking defects and remediated promptly.
- Safe defaults MUST be applied throughout: deny-by-default permissions, sanitized
  inputs, escaped outputs.
- Plugin capabilities MUST follow the least-privilege principle; no code may request or
  retain access beyond what the immediate task requires.

### II. Clear Architecture & Editorial Integrity

The codebase MUST reflect a clear, documented architecture. Structural decisions MUST be
made intentionally and recorded so that any collaborator can understand and extend the
system without reverse-engineering it. Editorial output (scraped content, translations,
enrichment data) MUST be accurate, attributable, and free of corruption.

**Non-negotiable rules:**
- Each module, class, or service MUST have a single, stated responsibility.
- Architectural decisions that deviate from established patterns MUST be documented with
  explicit rationale in the relevant spec or plan artifact.
- Scraped or generated content MUST preserve source fidelity; no silent data loss or
  distortion is permitted.
- Translation and enrichment pipelines MUST produce verifiable, reviewable output before
  it reaches the WordPress database.

### III. Code Standards, Review & Validation

All code MUST adhere to agreed project standards and MUST be reviewed before being merged
to the main branch. When behavior changes, tests or documented validation steps MUST
accompany the change.

**Non-negotiable rules:**
- No code reaches `main` without at least one peer review that checks correctness,
  security, and adherence to this constitution.
- When a change alters observable behavior, the author MUST include either automated
  tests or explicit manual validation steps in the pull request description.
- Code formatting and linting MUST pass in CI before review begins.
- PHP code MUST follow PSR-12 and declare strict types where applicable.

### IV. Breaking-Change Policy

Breaking changes (API contract changes, database schema alterations, hook signature
changes, removal of publicly consumable functionality) MUST NOT be introduced without
documented rationale and a migration path.

**Non-negotiable rules:**
- Every breaking change MUST include: (a) a clear explanation of why the breakage is
  necessary, (b) migration notes describing the steps an existing user or dependent must
  take, and (c) a version bump following semantic versioning (MAJOR.MINOR.PATCH).
- Deprecation MUST be signaled at least one minor version before removal.
- Breaking changes to WordPress hooks or REST endpoints MUST be flagged in the changelog
  with a `[BREAKING]` prefix.

### V. Long-Term Maintainability

Decisions MUST favor long-term maintainability over short-term convenience. Complexity
MUST be justified; unnecessary abstraction, premature optimization, and one-off hacks
are prohibited.

**Non-negotiable rules:**
- YAGNI (You Aren't Gonna Need It): do not add capability speculatively.
- Every dependency added MUST have a stated purpose and MUST be evaluable for removal.
- Technical debt MUST be tracked explicitly (in a task or issue) rather than left silent.
- Inline comments MUST explain *why*, not *what*; self-documenting code is preferred
  over comment-heavy code that obscures poor design.

## Collaboration & Communication

All collaborators MUST uphold respectful, professional communication in all project
channels (code reviews, issue threads, commit messages, documentation).

**Standards:**
- Commit messages MUST be traceable: reference the relevant spec branch or issue ID
  where applicable, and describe *what changed and why* in the imperative mood.
- Disagreements about approach MUST be resolved through documented discussion, not silent
  workarounds.
- Questions about requirements or scope MUST be raised before implementation begins, not
  discovered during review.
- Every collaborator is accountable for the parts of the codebase they touch; ownership
  does not end at merge.

## Data & Security Responsibilities

User data, site credentials, and scraped content MUST be handled with strict care.
Protection of site data is a shared, non-delegable responsibility.

**Standards:**
- No credentials, API keys, or personal data MUST appear in source code, logs, or
  committed files; use environment variables or WordPress options with appropriate
  capability checks.
- Scraped data pipelines MUST validate and sanitize all external input before storage
  or display.
- Defects that risk data loss or exposure MUST be remediated as P1 priorities,
  superseding feature work.
- Access to WordPress capabilities (e.g., `manage_options`, database writes) MUST be
  guarded by appropriate `current_user_can()` checks.

## Governance

This constitution supersedes all other informal practices and conventions in the project.
Any practice not addressed here defaults to WordPress coding standards and PHP-FIG PSRs.

**Amendment procedure:**
1. Propose the amendment as a pull request modifying this file.
2. Include a Sync Impact Report (as an HTML comment block at the top) documenting
   the version change, affected principles, and templates requiring updates.
3. At least one other collaborator MUST approve the amendment PR before merge.
4. The `CONSTITUTION_VERSION` MUST be incremented per semantic versioning rules
   (MAJOR: removals/redefinitions; MINOR: additions/expansions; PATCH: clarifications).
5. `LAST_AMENDED_DATE` MUST be updated to the merge date in ISO format (YYYY-MM-DD).

**Compliance:**
- All PR reviewers MUST verify that the proposed change does not violate any principle
  stated here; non-compliant PRs MUST be blocked until resolved.
- The constitution MUST be re-read by all contributors at the start of any new feature
  cycle (i.e., before beginning a `/speckit.specify` or `/speckit.plan` workflow).

**Version**: 1.0.0 | **Ratified**: 2026-03-01 | **Last Amended**: 2026-03-01
