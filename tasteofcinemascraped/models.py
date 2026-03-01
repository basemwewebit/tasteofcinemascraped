"""Data models for scraped and translated article."""
from dataclasses import dataclass, field
from typing import Optional


@dataclass
class ScrapedArticle:
    source_url: str
    title: str
    content: str
    content_html: str
    images: list[dict]  # [{"url": str, "alt": str | None}, ...]
    author_name: str
    author_bio: str
    date: str  # publication date as string
    tags: list[str]
    categories: list[str]
    page_urls: list[str] = field(default_factory=list)  # all fetched page URLs


@dataclass
class TranslatedArticle:
    """Article after translation; same shape plus translated fields. Slugs stay English."""
    source_url: str
    title: str
    content: str
    content_html: str
    images: list[dict]
    author_name: str
    author_bio: str
    author_slug: str = ""  # English, for WP user login
    post_name: str = ""   # English, for post permalink/slug
    date: str = ""
    tags: list[dict] = field(default_factory=list)   # [{"name": str, "slug": str}, ...]
    categories: list[dict] = field(default_factory=list)  # [{"name": str, "slug": str}, ...]
    page_urls: list[str] = field(default_factory=list)
