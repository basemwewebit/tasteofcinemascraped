# tasteofcinemascraped

Scrape Taste of Cinema articles (multi-page), translate via OpenRouter, and import into WordPress.

## Setup

1. **Python 3.10+**

   ```bash
   cd /path/to/tasteofcinemaarabi
   python -m venv .venv
   source .venv/bin/activate   # or .venv\Scripts\activate on Windows
   pip install -r tasteofcinemascraped/requirements.txt
   ```

2. **Optional: Scrapling browsers** (if using StealthyFetcher/DynamicFetcher later)

   ```bash
   scrapling install
   ```

3. **Environment**

   Copy `tasteofcinemascraped/.env.example` to `tasteofcinemascraped/.env` and set:

   - `OPENROUTER_API_KEY` – from openrouter.ai
   - `OPENROUTER_MODEL` – e.g. `anthropic/claude-3.5-sonnet`
   - `WORDPRESS_URL` – site URL (e.g. https://yoursite.com)
   - `WORDPRESS_ENDPOINT_PATH` – default `wp-json/tasteofcinemascraped/v1/import`
   - `WORDPRESS_SECRET` – same as in WordPress Settings → TOC Scraped

4. **WordPress plugin**

   Activate **Taste of Cinema Scraped Import** and set the import secret under **Settings → TOC Scraped**.

## Usage

From the project root (tasteofcinemaarabi):

```bash
# Full run: scrape → translate → import
python -m tasteofcinemascraped "https://www.tasteofcinema.com/2026/10-great-crime-thriller-movies-you-probably-havent-seen/"

# Dry-run: scrape and translate only, print summary (no WordPress)
python -m tasteofcinemascraped "https://..." --dry-run

# No translation: scrape and send English content
python -m tasteofcinemascraped "https://..." --no-translate
```

## Flow

1. **Scrape** – Fetches article and pagination (/2/, /3/…). Extracts title, content (without Author Bio block), images, author, author bio, date, tags, categories.
2. **Translate** – OpenRouter: Arabic translation; movie titles and years are kept as-is.
3. **Import** – POST to WordPress plugin; creates draft post, uploads images, creates/assigns author, tags, categories. Skips if `source_url` already imported.
