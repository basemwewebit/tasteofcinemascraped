"""
CLI: scrape URL(s) -> translate -> send to WordPress.
Single: python -m tasteofcinemascraped <article_url> [--dry-run]
Batch:  python -m tasteofcinemascraped --year 2026 [--dry-run]  (preserves original publish date per article)
"""
import argparse
import json
import sys
import time

from .config import OPENROUTER_API_KEY, WORDPRESS_SECRET, get_wordpress_import_url
from .scraper import scrape_article, discover_article_urls_from_archive
from .translator import translate_article, _slugify_english
from .wordpress_client import send_article

TASTEOFcinema_BASE = "https://www.tasteofcinema.com"


def _run_one(
    url: str,
    dry_run: bool,
    no_translate: bool,
) -> int:
    """Scrape one URL, translate, optionally send. Returns 0 on success, 1 on error."""
    print("Scraping...", flush=True)
    try:
        scraped = scrape_article(url)
    except Exception as e:
        print(f"Scrape error: {e}", file=sys.stderr)
        return 1
    print(f"  Title: {scraped.title[:60]}..." if len(scraped.title) > 60 else f"  Title: {scraped.title}")
    print(f"  Author: {scraped.author_name}, Pages: {len(scraped.page_urls)}, Date: {scraped.date or '(none)'}", flush=True)

    if no_translate or (dry_run and not OPENROUTER_API_KEY):
        from .models import TranslatedArticle
        translated = TranslatedArticle(
            source_url=scraped.source_url,
            title=scraped.title,
            content=scraped.content,
            content_html=scraped.content_html,
            images=scraped.images,
            author_name=scraped.author_name,
            author_bio=scraped.author_bio,
            author_slug=_slugify_english(scraped.author_name),
            post_name=_slugify_english(scraped.title),
            date=scraped.date,
            tags=[{"name": t, "slug": _slugify_english(t)} for t in scraped.tags],
            categories=[{"name": c, "slug": _slugify_english(c)} for c in scraped.categories],
            page_urls=scraped.page_urls,
        )
        if dry_run and not OPENROUTER_API_KEY:
            print("Dry-run: OPENROUTER_API_KEY not set; skipping translation.", flush=True)
    else:
        if not OPENROUTER_API_KEY:
            print("Error: OPENROUTER_API_KEY not set.", file=sys.stderr)
            return 1
        print("Translating...", flush=True)
        try:
            translated = translate_article(scraped, translate_bio=True)
        except Exception as e:
            print(f"Translation error: {e}", file=sys.stderr)
            return 1
        print("  Done.", flush=True)

    if dry_run:
        print("Dry-run: skipping WordPress import.")
        out = {
            "source_url": translated.source_url,
            "title": translated.title,
            "author_name": translated.author_name,
            "date": translated.date,
            "tags": translated.tags,
            "categories": translated.categories,
        }
        print(json.dumps(out, ensure_ascii=False, indent=2))
        return 0

    if not WORDPRESS_SECRET:
        print("Error: WORDPRESS_SECRET not set.", file=sys.stderr)
        return 1
    print("Sending to WordPress...", flush=True)
    try:
        result = send_article(translated)
    except Exception as e:
        print(f"WordPress error: {e}", file=sys.stderr)
        return 1
    if result.get("skipped"):
        print(f"Skipped (already imported): post_id={result.get('post_id')}")
    else:
        print(f"Created: post_id={result.get('post_id')}")
    return 0


def main() -> int:
    parser = argparse.ArgumentParser(
        description="Scrape Taste of Cinema article(s), translate, import to WordPress. Original publish date is preserved."
    )
    parser.add_argument("url", nargs="?", default="", help="Single article URL")
    parser.add_argument("--year", type=int, metavar="YEAR", help="Scrape all articles from this year (e.g. 2026). Discovers from archive then runs each.")
    parser.add_argument("--archive", type=str, metavar="URL", help="Scrape all articles from this archive URL (e.g. https://www.tasteofcinema.com/2026/).")
    parser.add_argument("--dry-run", action="store_true", help="Scrape and translate only; do not send to WordPress.")
    parser.add_argument("--no-translate", action="store_true", help="Skip translation; send scraped content as-is.")
    parser.add_argument("--delay", type=float, default=2.0, help="Seconds between each article when using --year/--archive (default: 2).")
    args = parser.parse_args()

    if args.year:
        archive_url = f"{TASTEOFcinema_BASE}/{args.year}/"
        print(f"Discovering articles from year {args.year}: {archive_url}", flush=True)
        try:
            urls = discover_article_urls_from_archive(archive_url)
        except Exception as e:
            print(f"Archive discovery error: {e}", file=sys.stderr)
            return 1
        print(f"Found {len(urls)} article(s).", flush=True)
        if not urls:
            return 0
        failed = 0
        for i, u in enumerate(urls, 1):
            print(f"\n[{i}/{len(urls)}] {u}", flush=True)
            if _run_one(u, args.dry_run, args.no_translate) != 0:
                failed += 1
            if i < len(urls) and args.delay > 0:
                time.sleep(args.delay)
        if failed:
            print(f"\n{failed} article(s) failed.", file=sys.stderr)
            return 1
        return 0

    if args.archive:
        print(f"Discovering articles from archive: {args.archive}", flush=True)
        try:
            urls = discover_article_urls_from_archive(args.archive)
        except Exception as e:
            print(f"Archive discovery error: {e}", file=sys.stderr)
            return 1
        print(f"Found {len(urls)} article(s).", flush=True)
        if not urls:
            return 0
        failed = 0
        for i, u in enumerate(urls, 1):
            print(f"\n[{i}/{len(urls)}] {u}", flush=True)
            if _run_one(u, args.dry_run, args.no_translate) != 0:
                failed += 1
            if i < len(urls) and args.delay > 0:
                time.sleep(args.delay)
        if failed:
            print(f"\n{failed} article(s) failed.", file=sys.stderr)
            return 1
        return 0

    if not (args.url or "").strip():
        parser.print_help()
        print("\nError: provide URL or --year or --archive.", file=sys.stderr)
        return 1

    return _run_one(args.url.strip(), args.dry_run, args.no_translate)


if __name__ == "__main__":
    sys.exit(main())
