"""
Translate scraped article to Arabic via OpenRouter.
Keeps movie names and years unchanged. Slugs (post, author, tags, categories) stay English.
"""
import re
import httpx

from .config import OPENROUTER_API_KEY, OPENROUTER_BASE_URL, OPENROUTER_MODEL
from .models import ScrapedArticle, TranslatedArticle


def _slugify_english(text: str, max_len: int = 80) -> str:
    """Produce URL-safe English slug (for post_name, author_slug, term slugs)."""
    if not (text or "").strip():
        return ""
    s = re.sub(r"[^a-z0-9\s\-]", "", (text or "").lower())
    s = re.sub(r"[-\s]+", "-", s).strip("-")
    return s[:max_len] if s else ""

SYSTEM_PROMPT = """You are a professional cinematic translator. Translate the following text from English to Arabic.
Rules:
- Keep all movie titles in their original language (do not translate).
- Keep all years as numbers (e.g. 1958, 1970) unchanged.
- Use a polished, cinematic tone suitable for film criticism.
- Preserve any HTML tags if present; only translate the text content between tags.
- Preserve line breaks and paragraph structure.
Output only the translated text, no explanations."""


def _extract_movie_names_and_years(text: str) -> list[str]:
    """Heuristic: (Title) (Year) or "Title" (Year) patterns."""
    # e.g. "Back to the Wall (1958)", "Heat (1995)"
    pattern = re.compile(r"(?:^|[\s\('\"])[\w\s\-']+?\s*\((\d{4})\)")
    years = pattern.findall(text)
    # Also catch "Title (Year)" as full match for listing
    full = re.findall(r"([A-Za-z0-9\s\-',:]+)\s*\((\d{4})\)", text)
    items = [f"{t.strip()} ({y})" for t, y in full] + list(set(years))
    return list(dict.fromkeys(items))[:50]  # dedupe, limit


def _build_user_message(content: str, keep_phrases: list[str]) -> str:
    if keep_phrases:
        extra = "These phrases must appear exactly as-is (do not translate): " + "; ".join(keep_phrases[:30])
        return extra + "\n\nText to translate:\n\n" + content
    return "Text to translate:\n\n" + content


def _call_openrouter(text: str, keep_phrases: list[str] | None = None) -> str:
    url = f"{OPENROUTER_BASE_URL}/chat/completions"
    headers = {
        "Authorization": f"Bearer {OPENROUTER_API_KEY}",
        "Content-Type": "application/json",
    }
    user_content = _build_user_message(text, keep_phrases or [])
    payload = {
        "model": OPENROUTER_MODEL,
        "temperature": 0.0,
        "messages": [
            {"role": "system", "content": SYSTEM_PROMPT},
            {"role": "user", "content": user_content},
        ],
    }
    with httpx.Client(timeout=1200.0) as client:
        resp = client.post(url, headers=headers, json=payload)
        resp.raise_for_status()
        data = resp.json()
    choice = data.get("choices", [{}])[0]
    message = choice.get("message", {})
    content = (message.get("content") or "").strip()
    
    # Strip markdown block wrappers like ```html or ``` if the model wraps the output
    content = re.sub(r"^```[a-zA-Z0-9_-]*\n", "", content)
    content = re.sub(r"\n```$", "", content)
    
    return content.strip()


PRIMARY_CATEGORY_PROMPT = """You classify film/cinema articles. Given the article title and excerpt below, output exactly two lines:
Line 1: One category name in Arabic (short phrase, 2–5 words) that best classifies this article. Use consistent phrasing for the same type of topic (e.g. always "أفلام الإثارة والجريمة" for crime thrillers).
Line 2: An English slug for that category: lowercase, hyphens, no spaces (e.g. crime-thriller-movies). Use the same slug for the same type of topic so articles can be grouped under one category.
Output only these two lines, nothing else."""


def _derive_primary_category(title: str, excerpt: str) -> dict[str, str] | None:
    """
    Derive one primary category from title + excerpt via OpenRouter.
    Returns {"name": "اسم التصنيف", "slug": "english-slug"} or None if disabled/failed.
    Same slug for same topic so WordPress reuses the category for later articles.
    """
    if not (title or "").strip() or not OPENROUTER_API_KEY:
        return None
    text = f"Title:\n{title}\n\nExcerpt:\n{(excerpt or '')[:500]}"
    url = f"{OPENROUTER_BASE_URL}/chat/completions"
    headers = {
        "Authorization": f"Bearer {OPENROUTER_API_KEY}",
        "Content-Type": "application/json",
    }
    payload = {
        "model": OPENROUTER_MODEL,
        "messages": [
            {"role": "system", "content": PRIMARY_CATEGORY_PROMPT},
            {"role": "user", "content": text},
        ],
    }
    try:
        with httpx.Client(timeout=60.0) as client:
            resp = client.post(url, headers=headers, json=payload)
            resp.raise_for_status()
            data = resp.json()
        choice = data.get("choices", [{}])[0]
        raw = (choice.get("message", {}).get("content") or "").strip()
        if not raw:
            return None
        lines = [ln.strip() for ln in raw.split("\n") if ln.strip()]
        name_ar = lines[0] if lines else ""
        slug = _slugify_english(lines[1]) if len(lines) > 1 else _slugify_english(name_ar)
        if not name_ar:
            return None
        return {"name": name_ar, "slug": slug or "topic"}
    except Exception:
        return None


def translate_article(scraped: ScrapedArticle, translate_bio: bool = True) -> TranslatedArticle:
    """
    Translate title, content, tags, categories, and optionally author_bio to Arabic.
    Movie names and years are extracted and passed as keep_phrases so they stay unchanged.
    """
    keep_phrases = _extract_movie_names_and_years(scraped.content)
    keep_phrases.extend(_extract_movie_names_and_years(scraped.title))

    title_ar = _call_openrouter(scraped.title, keep_phrases) if scraped.title else scraped.title
    content_ar = _call_openrouter(scraped.content_html or scraped.content, keep_phrases)

    author_bio_ar = scraped.author_bio
    if translate_bio and scraped.author_bio:
        author_bio_ar = _call_openrouter(scraped.author_bio, keep_phrases)

    tags_with_slug: list[dict] = []
    for t in scraped.tags:
        if not (t or "").strip():
            continue
        slug = _slugify_english(t)
        name_ar = _call_openrouter(t, keep_phrases)
        tags_with_slug.append({"name": name_ar, "slug": slug or _slugify_english(name_ar)})

    categories_with_slug: list[dict] = []
    for c in scraped.categories:
        if not (c or "").strip():
            continue
        slug = _slugify_english(c)
        name_ar = _call_openrouter(c, keep_phrases)
        categories_with_slug.append({"name": name_ar, "slug": slug or _slugify_english(name_ar)})

    # Primary category from title + excerpt: create once, reuse by slug for later articles
    excerpt_plain = re.sub(r"<[^>]+>", " ", content_ar or "")
    excerpt_plain = re.sub(r"\s+", " ", excerpt_plain).strip()[:400]
    primary = _derive_primary_category(title_ar, excerpt_plain)
    if primary:
        existing_slugs = {c.get("slug") or "" for c in categories_with_slug}
        if (primary.get("slug") or "").strip() and primary["slug"] not in existing_slugs:
            categories_with_slug.insert(0, primary)
        elif not existing_slugs:
            categories_with_slug.insert(0, primary)

    return TranslatedArticle(
        source_url=scraped.source_url,
        title=title_ar,
        content=content_ar,
        content_html=content_ar,
        images=scraped.images,
        author_name=scraped.author_name,
        author_bio=author_bio_ar,
        author_slug=_slugify_english(scraped.author_name),
        post_name=_slugify_english(scraped.title),
        date=scraped.date,
        tags=tags_with_slug,
        categories=categories_with_slug,
        page_urls=scraped.page_urls,
    )
