# tasteofcinemascraped-wp Development Guidelines

Auto-generated from all feature plans. Last updated: 2026-03-02

## Active Technologies
- PHP 8.1+ (WordPress plugin host) + Python 3.11 (scraper client) (002-predefined-categories)
- WordPress MySQL database — standard `wp_terms`, `wp_term_taxonomy`, (002-predefined-categories)
- PHP 8.x + WordPress Core REST API, `DOMDocument` for HTML parsing (001-clean-scraped-content)
- WordPress `wp_posts` table `post_content` (001-clean-scraped-content)
- PHP 8.3+ + WordPress Core, WordPress REST API (001-dual-site-publishing)
- WordPress Database (Posts, PostMeta, Terms) (001-dual-site-publishing)

- PHP 8.1+ (WordPress requirement; PSR-12 strict types), Python 3.11+ (existing pipeline, no changes required) + WordPress Plugin API, Action Scheduler ≥3.6 (standalone Composer package), OpenRouter API via `wp_remote_post()` (existing `OPENROUTER_API_KEY` env var reused) (001-translation-quality)

## Project Structure

```text
src/
tests/
```

## Commands

cd src [ONLY COMMANDS FOR ACTIVE TECHNOLOGIES][ONLY COMMANDS FOR ACTIVE TECHNOLOGIES] pytest [ONLY COMMANDS FOR ACTIVE TECHNOLOGIES][ONLY COMMANDS FOR ACTIVE TECHNOLOGIES] ruff check .

## Code Style

PHP 8.1+ (WordPress requirement; PSR-12 strict types), Python 3.11+ (existing pipeline, no changes required): Follow standard conventions

## Recent Changes
- 001-dual-site-publishing: Added PHP 8.3+ + WordPress Core, WordPress REST API
- 001-wp-admin-scraper: Added [if applicable, e.g., PostgreSQL, CoreData, files or N/A]
- 001-clean-scraped-content: Added PHP 8.x + WordPress Core REST API, `DOMDocument` for HTML parsing


<!-- MANUAL ADDITIONS START -->
<!-- MANUAL ADDITIONS END -->
