"""
Translate scraped article to Arabic via OpenRouter.
Keeps movie names and years unchanged. Slugs (post, author, tags, categories) stay English.

API call strategy (2 calls per article):
  Call 1 — primary model  : full content translation (long-form, high quality)
  Call 2 — fast model     : title, bio, tags, 5 extra tags, category (batch JSON)
"""
import json
import re
import httpx

from .config import OPENROUTER_API_KEY, OPENROUTER_BASE_URL, OPENROUTER_MODEL, OPENROUTER_FAST_MODEL
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
- Any paragraph that contains ONLY a numbered film entry (e.g. "25. Heat (1995)") must use <h2> tags instead of <p> tags.
Output only the translated text, no explanations."""

METADATA_BATCH_PROMPT = """You are a professional Arabic film content editor and translator.
Given English article metadata, return a SINGLE valid JSON object with these exact keys:

"title_ar": Arabic translation of the title.
"bio_ar": Arabic translation of the author bio (empty string if none provided).
"tags": Array of provided tags translated to Arabic. Each: {"name": "Arabic name", "slug": "keep-original-english-slug-unchanged"}.
"extra_tags": Exactly 5 new Arabic content tags relevant to this article, not duplicating existing slugs. Each: {"name": "Arabic name", "slug": "short-english-slug"}.
"category": Exactly ONE object from the list below that best fits the article:
  {"name":"\u0642\u0648\u0627\u0626\u0645 \u0623\u0641\u0644\u0627\u0645","slug":"film-lists"} \u2014 numbered or top-N film lists
  {"name":"\u0645\u0642\u0627\u0644\u0627\u062a \u0648\u062a\u062d\u0644\u064a\u0644\u0627\u062a","slug":"features"} \u2014 essays, analysis, interviews, guides
  {"name":"\u0642\u0648\u0627\u0626\u0645 \u0645\u062e\u0631\u062c\u064a\u0646 \u0648\u0645\u0645\u062b\u0644\u064a\u0646","slug":"people-lists"} \u2014 director or actor lists
  {"name":"\u0642\u0648\u0627\u0626\u0645 \u0645\u062a\u0646\u0648\u0639\u0629","slug":"other-lists"} \u2014 books, music, TV, non-film lists
  {"name":"\u0645\u0631\u0627\u062c\u0639\u0627\u062a \u0623\u0641\u0644\u0627\u0645","slug":"reviews"} \u2014 single film reviews or critiques
  {"name":"\u0623\u0641\u0636\u0644 \u0623\u0641\u0644\u0627\u0645 \u0627\u0644\u0633\u0646\u0629","slug":"best-of-year"} \u2014 best films of a specific year
  {"name":"\u0623\u0641\u0644\u0627\u0645 \u062d\u0633\u0628 \u0627\u0644\u0646\u0648\u0639","slug":"by-genre"} \u2014 films grouped by genre
  {"name":"\u0623\u0641\u0644\u0627\u0645 \u062d\u0633\u0628 \u0627\u0644\u0628\u0644\u062f","slug":"by-country"} \u2014 films grouped by country
  {"name":"\u0623\u0641\u0644\u0627\u0645 \u062d\u0633\u0628 \u0627\u0644\u0639\u0642\u062f","slug":"by-decade"} \u2014 films grouped by decade or era
  {"name":"\u0645\u0642\u0627\u0631\u0646\u0627\u062a \u0648\u062a\u0635\u0646\u064a\u0641\u0627\u062a","slug":"rankings"} \u2014 ranked comparisons, versus

Rules:
- Keep all movie titles in their original English.
- Keep all years as numbers.
- Use polished, cinematic Arabic.
- Output ONLY raw valid JSON \u2014 no markdown fences, no explanations."""


def _extract_movie_names_and_years(text: str) -> list[str]:
    """Heuristic: extract Title (Year) patterns to protect them from translation."""
    full = re.findall(r"([A-Za-z0-9\s\-',:]+)\s*\((\d{4})\)", text)
    items = [f"{t.strip()} ({y})" for t, y in full]
    return list(dict.fromkeys(items))[:50]  # dedupe, limit


def _build_user_message(content: str, keep_phrases: list[str]) -> str:
    if keep_phrases:
        extra = "These phrases must appear exactly as-is (do not translate): " + "; ".join(keep_phrases[:30])
        return extra + "\n\nText to translate:\n\n" + content
    return "Text to translate:\n\n" + content


def _call_openrouter(text: str, keep_phrases: list[str] | None = None) -> str:
    """Translate long-form content using the primary (pro) model."""
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
    content = (choice.get("message", {}).get("content") or "").strip()
    content = re.sub(r"^```[a-zA-Z0-9_-]*\n", "", content)
    content = re.sub(r"\n```$", "", content)
    return content.strip()


def _call_metadata_batch(
    title: str,
    bio: str,
    tags: list[str],
    excerpt: str,
    keep_phrases: list[str] | None = None,
) -> dict:
    """
    Single fast-model call that handles all lightweight metadata in one shot:
    title translation, bio translation, tag translation + 5 extra tags, category classification.
    Returns parsed dict or {} on failure (translate_article() applies defaults).
    """
    url = f"{OPENROUTER_BASE_URL}/chat/completions"
    headers = {
        "Authorization": f"Bearer {OPENROUTER_API_KEY}",
        "Content-Type": "application/json",
    }
    tag_lines = "\n".join(f'- "{t}"' for t in tags if (t or "").strip()) or "(none)"
    keep_note = ""
    if keep_phrases:
        keep_note = "\nKEEP UNCHANGED (do not translate these): " + "; ".join(keep_phrases[:30]) + "\n"
    user_content = (
        f"Title: {title}\n"
        f"Author Bio: {bio or '(none)'}\n"
        f"Existing Tags:\n{tag_lines}\n"
        f"Content Excerpt (for context):\n{excerpt[:600]}"
        f"{keep_note}"
    )
    payload = {
        "model": OPENROUTER_FAST_MODEL,
        "temperature": 0.0,
        "response_format": {"type": "json_object"},
        "messages": [
            {"role": "system", "content": METADATA_BATCH_PROMPT},
            {"role": "user", "content": user_content},
        ],
    }
    try:
        with httpx.Client(timeout=120.0) as client:
            resp = client.post(url, headers=headers, json=payload)
            resp.raise_for_status()
            data = resp.json()
        raw = (data.get("choices", [{}])[0].get("message", {}).get("content") or "").strip()
        raw = re.sub(r"^```[a-zA-Z0-9_-]*\n?", "", raw)
        raw = re.sub(r"\n?```$", "", raw)
        return json.loads(raw)
    except Exception:
        return {}


def _promote_film_entries_to_h2(html: str) -> str:
    """
    Post-processing safety net: convert numbered film entries to <h2> headings.
    e.g. <p><strong>25. Heat (1995)</strong></p>  →  <h2>25. Heat (1995)</h2>
    Complements the SYSTEM_PROMPT instruction for models that miss it.
    """
    if not html:
        return html
    pattern = re.compile(
        r'<p[^>]*>\s*(?:<strong[^>]*>)?\s*'
        r'(\d+[\.)\]\s]\s*[^<\n]{3,120}\(\d{4}\))'
        r'\s*(?:</strong>)?\s*</p>',
        re.IGNORECASE,
    )
    return pattern.sub(r'<h2>\1</h2>', html)


def translate_article(scraped: ScrapedArticle, translate_bio: bool = True) -> TranslatedArticle:
    """
    Translate article to Arabic using two API calls:
      Call 1 (primary/pro model)  — full content HTML translation.
      Call 2 (fast model)         — title, bio, tags, 5 extra tags, category (single batch JSON).
    Movie names and years are extracted upfront and passed as keep_phrases.
    """
    keep_phrases = _extract_movie_names_and_years(scraped.content)
    keep_phrases.extend(_extract_movie_names_and_years(scraped.title))

    # --- Call 1: content (pro model, long-form) ---
    content_ar = _call_openrouter(scraped.content_html or scraped.content, keep_phrases)
    content_ar = _promote_film_entries_to_h2(content_ar)

    # --- Call 2: metadata batch (fast model) ---
    excerpt_en = re.sub(r"<[^>]+>", " ", scraped.content or "")
    excerpt_en = re.sub(r"\s+", " ", excerpt_en).strip()
    bio_en = scraped.author_bio if translate_bio else ""

    meta = _call_metadata_batch(
        title=scraped.title,
        bio=bio_en,
        tags=scraped.tags,
        excerpt=excerpt_en,
        keep_phrases=keep_phrases,
    )

    title_ar = (meta.get("title_ar") or "").strip() or scraped.title
    author_bio_ar = (meta.get("bio_ar") or "").strip() or scraped.author_bio

    # Merge translated existing tags + 5 AI-generated extra tags (dedupe by slug)
    tags_with_slug: list[dict] = []
    seen_slugs: set[str] = set()
    for t in (meta.get("tags") or []):
        if isinstance(t, dict) and t.get("name") and t.get("slug"):
            slug = _slugify_english(str(t["slug"]))
            if slug and slug not in seen_slugs:
                tags_with_slug.append({"name": str(t["name"]), "slug": slug})
                seen_slugs.add(slug)
    for t in (meta.get("extra_tags") or []):
        if isinstance(t, dict) and t.get("name") and t.get("slug"):
            slug = _slugify_english(str(t["slug"]))
            if slug and slug not in seen_slugs:
                tags_with_slug.append({"name": str(t["name"]), "slug": slug})
                seen_slugs.add(slug)

    # Category
    cat = meta.get("category")
    if isinstance(cat, dict) and cat.get("name") and cat.get("slug"):
        cat_slug = _slugify_english(str(cat["slug"]))
        categories_with_slug = [{"name": str(cat["name"]), "slug": cat_slug or "film-lists"}]
    else:
        categories_with_slug = [{"name": "\u0642\u0648\u0627\u0626\u0645 \u0623\u0641\u0644\u0627\u0645", "slug": "film-lists"}]

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
