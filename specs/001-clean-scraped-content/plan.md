# Implementation Plan: Clean Scraped Content

**Branch**: `001-clean-scraped-content` | **Date**: 2026-03-03 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/specs/001-clean-scraped-content/spec.md`

## Summary

The goal is to enhance the scraping pipeline to automatically strip redundant thumbnail images appearing at the start of an article's content and to normalize all imported HTML by stripping `style` and `class` attributes, replacing them with standardized Tailwind CSS utility classes.

## Technical Context

**Language/Version**: PHP 8.x
**Primary Dependencies**: WordPress Core REST API, `DOMDocument` for HTML parsing
**Storage**: WordPress `wp_posts` table `post_content`
**Testing**: Manual validation in WordPress environment
**Target Platform**: WordPress (Linux/PHP)
**Project Type**: Plugin Enhancement
**Performance Goals**: Content parsing and transformation must add < 50ms per article during the import REST API call.
**Constraints**: HTML parsing must preserve the structural integrity of the scraped text and images while mutating only attributes and removing the specifically targeted duplicate thumbnail element.
**Scale/Scope**: Impacts every new article imported via the REST API.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

- **I. Quality, Reliability & Security**: Will use native PHP `DOMDocument` for resilient HTML parsing instead of fragile regular expressions, ensuring no malformed markup or security risks (XSS via styles).
- **II. Clear Architecture & Editorial Integrity**: A new dedicated class, `TOC_Content_Cleaner`, will be created to handle the single responsibility of data transformation, preserving the original data intent while improving presentation.
- **III. Code Standards, Review & Validation**: Implementation will adhere to PSR-12 and use strict internal typing.
- **IV. Breaking-Change Policy**: The change improves the incoming payload non-destructively; no breaking changes to expected API signatures.
- **V. Long-Term Maintainability**: The cleaning mechanism will be isolated from the core API routing (`tasteofcinemascraped_import_callback`) via a clear interface method, making future taxonomy updates easy.

## Project Structure

### Documentation (this feature)

```text
specs/001-clean-scraped-content/
├── plan.md              
├── research.md          
├── data-model.md        
├── quickstart.md        
├── contracts/           
└── tasks.md             
```

### Source Code (repository root)

```text
src/
├── includes/
│   ├── class-toc-content-cleaner.php
tasteofcinemascraped-wp.php
```

**Structure Decision**: We will add a new class `class-toc-content-cleaner.php` in the `includes` directory, registering it statically within the main plugin file to process `$content` before `wp_insert_post` is called.

## Complexity Tracking

*(No constitution violations. `DOMDocument` provides the simplest robust solution for HTML manipulation in PHP without introducing third-party libraries.)*
