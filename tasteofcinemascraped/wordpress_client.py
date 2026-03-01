"""Send translated article payload to WordPress plugin endpoint."""
import httpx

from .config import get_wordpress_import_url, WORDPRESS_SECRET
from .models import TranslatedArticle


def send_article(article: TranslatedArticle) -> dict:
    """
    POST article to tasteofcinemascraped-wp import endpoint.
    Returns response JSON; raises on HTTP errors.
    """
    url = get_wordpress_import_url()
    headers = {
        "Content-Type": "application/json",
        "X-Tasteofcinema-Secret": WORDPRESS_SECRET,
    }
    # Tags/categories: list of {"name": str, "slug": str}; slugs stay English
    tags_payload = []
    for t in article.tags or []:
        if isinstance(t, dict) and "name" in t:
            tags_payload.append({"name": t["name"], "slug": (t.get("slug") or "").strip() or None})
        elif isinstance(t, str) and t.strip():
            tags_payload.append({"name": t.strip(), "slug": None})
    categories_payload = []
    for c in article.categories or []:
        if isinstance(c, dict) and "name" in c:
            categories_payload.append({"name": c["name"], "slug": (c.get("slug") or "").strip() or None})
        elif isinstance(c, str) and c.strip():
            categories_payload.append({"name": c.strip(), "slug": None})

    payload = {
        "source_url": article.source_url,
        "title": article.title,
        "content": article.content_html or article.content,
        "post_name": (article.post_name or "").strip() or None,
        "author_name": article.author_name,
        "author_bio": article.author_bio or "",
        "author_slug": (article.author_slug or "").strip() or None,
        "date": article.date or "",
        "tags": tags_payload,
        "categories": categories_payload,
        "images": article.images or [],
    }
    with httpx.Client(timeout=60.0) as client:
        resp = client.post(url, headers=headers, json=payload)
        resp.raise_for_status()
        return resp.json()
