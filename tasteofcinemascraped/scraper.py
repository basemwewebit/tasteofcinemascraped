"""
Scrape Taste of Cinema article(s) across multiple pages.
Extracts: title, content (without Author Bio), images, author, author_bio, date, tags, categories.
"""
import re
from urllib.parse import urljoin, urlparse

from scrapling.fetchers import Fetcher

from .models import ScrapedArticle

# Base URL for relative links
TASTEOFcinema_BASE = "https://www.tasteofcinema.com"

# Selectors tuned for Taste of Cinema (WordPress / common theme)
SELECTORS = {
    "title": "h1.entry-title, .entry-header h1, h1",
    "content": ".entry-content, .post-content, .article-content, article .content, main .entry-content, .post .entry-content, div.entry-content, div.post-content",
    "author": ".author a::text, .byline a::text, .entry-meta .author a::text, a[rel='author']::text",
    "date": "time::attr(datetime), .entry-date::attr(datetime), .posted-on time::attr(datetime), time.entry-date::attr(datetime), .entry-meta time::attr(datetime), .date::text, .posted-on time::text, .entry-meta .entry-date::text",
    "tags": ".tags-links a::text, .tag-links a::text, a[rel='tag']::text",
    "categories": ".cat-links a::text, .category-links a::text, .entry-categories a::text",
    "images_in_content": ".entry-content img, .post-content img",
}


def _normalize_url(url: str) -> str:
    return url.rstrip("/")


def _page_url(base_url: str, page_num: int) -> str:
    """Build URL for page 1, 2, 3 (e.g. .../slug/ and .../slug/2/)."""
    base = _normalize_url(base_url)
    if page_num <= 1:
        return base if base.endswith("/") else base + "/"
    return f"{base}/{page_num}/"


def _fetch_page(url: str) -> "Fetcher.get":
    return Fetcher.get(url)


def _get_text(selector_result, default: str = "") -> str:
    if selector_result is None:
        return default
    if hasattr(selector_result, "get"):
        return (selector_result.get() or default).strip()
    if hasattr(selector_result, "strip"):
        return selector_result.strip() or default
    return str(selector_result).strip() if selector_result else default


def _get_all_text(selector_result) -> list[str]:
    if selector_result is None:
        return []
    if hasattr(selector_result, "getall"):
        return [t.strip() for t in selector_result.getall() if t and str(t).strip()]
    if isinstance(selector_result, list):
        return [str(x).strip() for x in selector_result if x]
    return []


def _strip_page_links(html: str) -> str:
    """Remove WordPress page-links pagination block from content (e.g. Pages: 1 2)."""
    if not html or "page-links" not in html:
        return html
    return re.sub(
        r'<div[^>]*class="[^"]*page-links[^"]*"[^>]*>.*?</div>',
        "",
        html,
        flags=re.IGNORECASE | re.DOTALL,
    ).strip()


# Month name to number for date parsing
_MONTHS = {
    "january": 1, "february": 2, "march": 3, "april": 4, "may": 5, "june": 6,
    "july": 7, "august": 8, "september": 9, "october": 10, "november": 11, "december": 12,
}


def _normalize_date(date_str: str) -> str:
    """
    Normalize date to YYYY-MM-DD for WordPress. Accepts ISO, "February 25, 2026", "25 Feb 2026", etc.
    """
    if not date_str or not (s := date_str.strip()):
        return ""
    # Already ISO (with or without time)
    m = re.match(r"^(\d{4})-(\d{2})-(\d{2})", s)
    if m:
        return f"{m.group(1)}-{m.group(2)}-{m.group(3)}"
    # "February 25, 2026" or "25 February 2026"
    m = re.search(
        r"(?:January|February|March|April|May|June|July|August|September|October|November|December)\s+(\d{1,2}),?\s+(\d{4})",
        s,
        re.I,
    )
    if m:
        month_name = re.search(r"(January|February|March|April|May|June|July|August|September|October|November|December)", s, re.I)
        if month_name:
            mn = _MONTHS.get(month_name.group(1).lower(), 1)
            return f"{m.group(2)}-{mn:02d}-{int(m.group(1)):02d}"
    m = re.search(r"(\d{1,2})\s+(January|February|March|April|May|June|July|August|September|October|November|December)\s+(\d{4})", s, re.I)
    if m:
        mn = _MONTHS.get(m.group(2).lower(), 1)
        return f"{m.group(3)}-{mn:02d}-{int(m.group(1)):02d}"
    return s


def _extract_author_bio_from_content(html: str) -> tuple[str, str]:
    """
    Find 'Author Bio:' block at the end of content; return (content_without_bio, author_bio_text).
    """
    if not html or "Author Bio" not in html:
        return html, ""
    # Match from "Author Bio:" (or similar) to end of container
    pattern = re.compile(
        r"(?:Author Bio\s*:?\s*)(.+?)(?=</(?:p|div|section)|$)",
        re.IGNORECASE | re.DOTALL,
    )
    match = pattern.search(html)
    if not match:
        return html, ""
    bio_text = re.sub(r"<[^>]+>", " ", match.group(1))
    bio_text = re.sub(r"\s+", " ", bio_text).strip()
    # Remove the Author Bio block from content (from "Author Bio" to next closing tag or end)
    remove_pattern = re.compile(
        r"<[^>]*>(?:\s*Author Bio\s*:?\s*).*?$",
        re.IGNORECASE | re.DOTALL,
    )
    content_without = remove_pattern.sub("", html).strip()
    # Also try to remove trailing paragraph containing Author Bio
    content_without = re.sub(r"<p[^>]*>.*?Author Bio.*?</p>", "", content_without, flags=re.IGNORECASE | re.DOTALL)
    return content_without.strip(), bio_text


def _extract_from_response(page, base_url: str) -> dict:
    """Extract all fields from a single page response."""
    title = ""
    for sel in SELECTORS["title"].split(", "):
        try:
            el = page.css(sel.strip())
            if el:
                title = _get_text(el[0].css("::text") if hasattr(el[0], "css") else el[0].text)
                if title:
                    break
        except Exception:
            continue
    if not title and page.css("h1"):
        title = _get_text(page.css("h1::text").get())

    # Prefer the *largest* content block (main post body); on page 2 the first .entry-content may be sidebar/empty
    content_html = ""
    candidates: list[tuple[str, int]] = []  # (html, plain_len)

    for sel in SELECTORS["content"].split(", "):
        try:
            els = page.css(sel.strip())
            for el in els:
                raw = (el.get() or "") if hasattr(el, "get") else str(el)
                if not raw or len(raw) < 50:
                    continue
                plain_len = len(re.sub(r"<[^>]+>", "", raw).strip())
                if plain_len > 50:
                    candidates.append((raw, plain_len))
        except Exception:
            continue
    if not candidates:
        for fallback in ["article", "main", ".post"]:
            try:
                els = page.css(fallback)
                for el in els:
                    raw = (el.get() or "") if hasattr(el, "get") else str(el)
                    if not raw:
                        continue
                    plain_len = len(re.sub(r"<[^>]+>", "", raw).strip())
                    if plain_len > 100:
                        candidates.append((raw, plain_len))
            except Exception:
                continue
    if candidates:
        content_html = max(candidates, key=lambda x: x[1])[0]
    content_html = _strip_page_links(content_html)
    content_plain = re.sub(r"<[^>]+>", "\n", content_html)
    content_plain = re.sub(r"\n+", "\n", content_plain).strip()

    author_name = ""
    for sel in SELECTORS["author"].split(", "):
        try:
            r = page.css(sel.strip())
            if r:
                author_name = _get_text(r.get() if hasattr(r, "get") else (r[0] if r else None))
                if author_name:
                    break
        except Exception:
            continue
    if not author_name:
        byline = page.css(".entry-meta, .byline")
        if byline:
            text = byline[0].text_content() if hasattr(byline[0], "text_content") else ""
            m = re.search(r"by\s+(\w+(?:\s+\w+)*)", text or "", re.I)
            if m:
                author_name = m.group(1).strip()

    date_str = ""
    for sel in SELECTORS["date"].split(", "):
        try:
            r = page.css(sel.strip())
            if r:
                date_str = _get_text(r.get() if hasattr(r, "get") else (r[0] if r else None))
                if date_str and len(date_str.strip()) > 4:
                    break
        except Exception:
            continue
    # Fallback: meta tags (article:published_time, og:published_time)
    if not date_str or len(date_str.strip()) < 5:
        for meta_sel in [
            'meta[property="article:published_time"]::attr(content)',
            'meta[property="og:published_time"]::attr(content)',
            'meta[name="date"]::attr(content)',
            'meta[name="publishdate"]::attr(content)',
        ]:
            try:
                r = page.css(meta_sel)
                if r:
                    val = _get_text(r.get() if hasattr(r, "get") else (r[0] if r else None))
                    if val and len(val) >= 10 and ("202" in val or "201" in val):
                        date_str = val.strip()
                        break
            except Exception:
                continue
    # Fallback: parse "Posted on February 25, 2026" from entry-meta / byline
    if not date_str or len(date_str.strip()) < 5:
        for meta_sel in [".entry-meta", ".posted-on", ".byline", ".post-meta"]:
            try:
                meta_el = page.css(meta_sel)
                if meta_el:
                    meta_text = meta_el[0].text_content() if hasattr(meta_el[0], "text_content") else (meta_el[0].get() or "")
                    if isinstance(meta_text, str) and ("202" in meta_text or "201" in meta_text):
                        m = re.search(
                            r"(?:Posted on\s+)?(\d{4}-\d{2}-\d{2}|(?:January|February|March|April|May|June|July|August|September|October|November|December)\s+\d{1,2},?\s+\d{4}|\d{1,2}\s+(?:January|February|March|April|May|June|July|August|September|October|November|December)\s+\d{4})",
                            meta_text,
                            re.IGNORECASE,
                        )
                        if m:
                            date_str = m.group(1).strip()
                            break
            except Exception:
                continue

    date_str = _normalize_date(date_str)

    tags = []
    for sel in SELECTORS["tags"].split(", "):
        try:
            r = page.css(sel.strip())
            if r:
                tags = _get_all_text(r.getall() if hasattr(r, "getall") else r)
                if tags:
                    break
        except Exception:
            continue

    categories = []
    for sel in SELECTORS["categories"].split(", "):
        try:
            r = page.css(sel.strip())
            if r:
                categories = _get_all_text(r.getall() if hasattr(r, "getall") else r)
                if categories:
                    break
        except Exception:
            continue

    images = []
    for sel in SELECTORS["images_in_content"].split(", "):
        try:
            imgs = page.css(sel.strip())
            for img in imgs:
                src = None
                alt = ""
                if hasattr(img, "attrib"):
                    src = img.attrib.get("src")
                    alt = img.attrib.get("alt", "")
                if not src and hasattr(img, "css"):
                    src = img.css("::attr(src)").get() if hasattr(img.css("::attr(src)"), "get") else None
                if not src:
                    continue
                src = urljoin(base_url, src)
                images.append({"url": src, "alt": alt or None})
            if images:
                break
        except Exception:
            continue

    content_html_no_bio, author_bio = _extract_author_bio_from_content(content_html)
    content_plain_no_bio = re.sub(r"<[^>]+>", "\n", content_html_no_bio)
    content_plain_no_bio = re.sub(r"\n+", "\n", content_plain_no_bio).strip()

    return {
        "title": title,
        "content_html": content_html_no_bio,
        "content_plain": content_plain_no_bio,
        "author_name": author_name,
        "author_bio": author_bio,
        "date": date_str,
        "tags": tags,
        "categories": categories,
        "images": images,
    }


def _archive_page_url(archive_base: str, page_num: int) -> str:
    """Archive pagination: .../page/2/, .../page/3/ (WordPress style)."""
    base = _normalize_url(archive_base)
    if page_num <= 1:
        return base + "/" if not base.endswith("/") else base
    return f"{base}/page/{page_num}/"


def _is_article_url(url: str, archive_netloc: str, archive_path_prefix: str) -> bool:
    """True if URL looks like a single article (not archive pagination, not comment anchor)."""
    parsed = urlparse(url)
    if parsed.netloc != archive_netloc:
        return False
    path = (parsed.path or "").strip().rstrip("/")
    if not path.startswith(archive_path_prefix):
        return False
    # Exclude archive pagination: /2026/page/2
    if "/page/" in path:
        return False
    # Must be at least archive + one segment, e.g. /2026/slug
    rest = path[len(archive_path_prefix) :].lstrip("/")
    parts = [p for p in rest.split("/") if p]
    if not parts:
        return False
    # Exclude numeric-only last segment (article pagination like /slug/2)
    if len(parts) >= 2 and parts[-1].isdigit():
        return False
    return True


def _normalize_article_url(url: str) -> str:
    """Strip fragment and trailing numeric pagination to get canonical article URL."""
    parsed = urlparse(url)
    path = (parsed.path or "").rstrip("/")
    parts = path.split("/")
    if len(parts) >= 2 and parts[-1].isdigit():
        path = "/".join(parts[:-1])
    return f"{parsed.scheme}://{parsed.netloc}{path}/"


def discover_article_urls_from_archive(
    archive_url: str,
    max_archive_pages: int = 50,
) -> list[str]:
    """
    Discover article URLs from a Taste of Cinema archive (e.g. year https://www.tasteofcinema.com/2026/).
    Fetches archive and its pagination (/page/2/, ...), collects all links that look like articles.
    Returns deduplicated list of article URLs (original publication date is preserved per-article when scraping).
    """
    parsed = urlparse(_normalize_url(archive_url))
    base_site = f"{parsed.scheme}://{parsed.netloc}"
    archive_path = (parsed.path or "").rstrip("/") or "/"
    archive_path_prefix = archive_path if archive_path.endswith("/") else archive_path + "/"
    seen: set[str] = set()
    ordered: list[str] = []

    for page_num in range(1, max_archive_pages + 1):
        url = _archive_page_url(archive_url, page_num)
        try:
            page = _fetch_page(url)
        except Exception:
            break
        if page.status != 200:
            break
        added = 0
        try:
            links = page.css("a[href]")
            for a in links:
                href = None
                if hasattr(a, "attrib") and "href" in a.attrib:
                    href = a.attrib["href"]
                elif hasattr(a, "css"):
                    href = a.css("::attr(href)").get() if hasattr(a.css("::attr(href)"), "get") else None
                if not href or not href.strip():
                    continue
                full = urljoin(base_site, href.strip().split("#")[0])
                if not _is_article_url(full, parsed.netloc, archive_path_prefix):
                    continue
                canonical = _normalize_article_url(full)
                if canonical not in seen:
                    seen.add(canonical)
                    ordered.append(canonical)
                    added += 1
        except Exception:
            pass
        if added == 0 and page_num > 1:
            break
    return ordered


def _has_content(page) -> bool:
    """True if page has article content (e.g. .entry-content with text)."""
    try:
        content = page.css(".entry-content, .post-content")
        if not content:
            return False
        text = content[0].text_content() if hasattr(content[0], "text_content") else ""
        return bool(text and len(text.strip()) > 20)
    except Exception:
        return False


def _strip_overlap(accumulated: str, new_part: str, min_overlap: int = 80) -> str:
    """
    If new_part starts with the same text as the end of accumulated (repeated intro on paginated pages),
    return only the part of new_part that comes after the overlap to avoid duplication.
    """
    if not accumulated or not new_part:
        return new_part
    # Try from longest possible overlap down to min_overlap
    max_try = min(len(accumulated), len(new_part))
    for length in range(max_try, min_overlap - 1, -1):
        if accumulated[-length:] == new_part[:length]:
            return new_part[length:].lstrip()
    return new_part


def scrape_article(source_url: str, max_pages: int = 10) -> ScrapedArticle:
    """
    Scrape a Taste of Cinema article, following pagination (/2/, /3/, ...).
    Returns a single ScrapedArticle with merged content from all pages.
    """
    base_url = _normalize_url(source_url)
    parsed = urlparse(base_url)
    base_path = parsed.path.rstrip("/") or "/"
    base_site = f"{parsed.scheme}://{parsed.netloc}"

    all_content_html: list[str] = []
    all_content_plain: list[str] = []
    all_images: list[dict] = []
    page_urls: list[str] = []
    title = ""
    author_name = ""
    author_bio = ""
    date_str = ""
    tags: list[str] = []
    categories: list[str] = []
    first_page_data: dict | None = None

    for page_num in range(1, max_pages + 1):
        url = _page_url(source_url, page_num)
        try:
            page = _fetch_page(url)
        except Exception as e:
            if page_num == 1:
                raise
            break
        if page.status != 200:
            break

        data = _extract_from_response(page, base_site)
        # For page 2+: stop only if we got no meaningful content (paginated page may use different structure)
        if page_num > 1:
            content_len = len((data.get("content_plain") or "").strip()) + len((data.get("content_html") or "").strip())
            if content_len < 50:
                break

        page_urls.append(url)

        if page_num == 1:
            first_page_data = data
            title = data["title"]
            author_name = data["author_name"]
            author_bio = data["author_bio"]
            date_str = data["date"]
            tags = list(data["tags"])
            categories = list(data["categories"])
        # Author bio often appears only on the last page; keep last non-empty
        if (data.get("author_bio") or "").strip():
            author_bio = (data["author_bio"] or "").strip()

        # From page 2 onward, strip repeated intro so we don't duplicate content
        if page_num == 1:
            all_content_html.append(data["content_html"])
            all_content_plain.append(data["content_plain"])
        else:
            acc_html = "\n\n".join(all_content_html)
            acc_plain = "\n\n".join(all_content_plain)
            new_html = _strip_overlap(acc_html, data["content_html"] or "")
            new_plain = _strip_overlap(acc_plain, data["content_plain"] or "")
            new_html = (new_html or "").strip()
            new_plain = (new_plain or "").strip()
            if new_html:
                all_content_html.append(new_html)
            if new_plain:
                all_content_plain.append(new_plain)
            # If this page was entirely duplicate, stop pagination
            if not new_html and not new_plain:
                break
        for im in data["images"]:
            if not any(i["url"] == im["url"] for i in all_images):
                all_images.append(im)

    if not first_page_data:
        raise ValueError(f"No content fetched from {source_url}")

    # Fallback: date from article URL path (e.g. .../2026/ or .../2026/02/25/; avoid /2026/10-great)
    if not (date_str or "").strip():
        path = urlparse(source_url).path or ""
        # Only treat as month/day if segment is exactly 1-2 digits followed by / or end
        ym = re.search(r"/(\d{4})(?:/(\d{1,2})(?:/(\d{1,2}))?)?(?:/|$)", path)
        if ym:
            y = ym.group(1)
            m = ym.group(2)
            d = ym.group(3)
            if m and d and 1 <= int(m) <= 12 and 1 <= int(d) <= 31:
                date_str = f"{y}-{int(m):02d}-{int(d):02d}"
            elif m and 1 <= int(m) <= 12:
                date_str = f"{y}-{int(m):02d}-01"
            else:
                date_str = f"{y}-01-01"
        else:
            date_str = first_page_data.get("date") or ""

    return ScrapedArticle(
        source_url=source_url,
        title=title or first_page_data["title"],
        content="\n\n".join(all_content_plain) if all_content_plain else first_page_data["content_plain"],
        content_html="\n\n".join(all_content_html) if all_content_html else first_page_data["content_html"],
        images=all_images,
        author_name=author_name or first_page_data["author_name"],
        author_bio=author_bio or first_page_data["author_bio"],
        date=date_str or first_page_data["date"],
        tags=tags,
        categories=categories,
        page_urls=page_urls,
    )
