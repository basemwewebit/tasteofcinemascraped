# tasteofcinemascraped

Scrape Taste of Cinema articles (multi-page), translate via OpenRouter, and import into WordPress.

## Setup

1. **Python 3.10+**

   ```bash
   cd wp-content/plugins/tasteofcinemascraped-wp
   python -m venv .venv
   source .venv/bin/activate   # or .venv\Scripts\activate on Windows
   pip install -r tasteofcinemascraped/requirements.txt
   ```

2. **Environment**

   Copy `tasteofcinemascraped/.env.example` to `tasteofcinemascraped/.env` and set:

   | Variable | Description |
   |----------|-------------|
   | `OPENROUTER_API_KEY` | from openrouter.ai |
   | `OPENROUTER_MODEL` | primary model for content translation (e.g. `google/gemini-3.1-pro-preview`) |
   | `OPENROUTER_FAST_MODEL` | lightweight model for metadata batch — title, bio, tags, category (e.g. `google/gemini-3.1-flash-lite-preview`). Falls back to `OPENROUTER_MODEL` if unset. |
   | `WORDPRESS_URL` | site URL |
   | `WORDPRESS_ENDPOINT_PATH` | default `wp-json/tasteofcinemascraped/v1/import` |
   | `WORDPRESS_SECRET` | same as WordPress Settings → TOC Scraped |

3. **WordPress plugin**

   Activate **Taste of Cinema Scraped Import** and set the import secret under **Settings → TOC Scraped**.

## Usage

Run from the plugin root (`tasteofcinemascraped-wp/`):

```bash
# Full run: scrape → translate → import
python -m tasteofcinemascraped "https://www.tasteofcinema.com/2026/10-great-crime-thriller-movies/"

# Dry-run: scrape and translate only, print summary (no WordPress)
python -m tasteofcinemascraped "https://..." --dry-run

# No translation: scrape and send English content
python -m tasteofcinemascraped "https://..." --no-translate

# Whole year
python -m tasteofcinemascraped --year 2026
```

## Translation Pipeline (2 API calls per article)

1. **Scrape** — fetches all pages, extracts title, HTML content, images, author, bio, date, tags, categories.
2. **Translate** — two OpenRouter calls:
   - **Call 1 (primary model):** full content HTML — high quality, long-form.
   - **Call 2 (fast model):** single JSON batch — title, bio, translated tags + 5 AI-generated extra tags, category classification from 10 predefined options.
   - Film entry paragraphs (`25. Heat (1995)`) are promoted to `<h2>` via prompt + post-processing regex.
   - Movie titles and years kept unchanged throughout.
3. **Import** — POST to WordPress plugin: creates draft, uploads images (→ WebP), creates/assigns author, tags (with English slugs), category. Skips if `source_url` already imported.
4. **Dual publish** — on post publish, syncs to live site with correct tag IDs (creates missing tags on remote if needed).
