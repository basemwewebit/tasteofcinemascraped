"""Configuration from environment."""
import os
from pathlib import Path

from dotenv import load_dotenv

# Load .env from package directory or cwd
_env = Path(__file__).resolve().parent / ".env"
load_dotenv(_env)
load_dotenv()

OPENROUTER_API_KEY: str = os.environ.get("OPENROUTER_API_KEY", "")
OPENROUTER_MODEL: str = os.environ.get("OPENROUTER_MODEL", "google/gemini-3.1-flash-lite-preview")
# Fast model for lightweight tasks (title, bio, tags, category). Falls back to primary model if unset.
OPENROUTER_FAST_MODEL: str = os.environ.get("OPENROUTER_FAST_MODEL", "") or OPENROUTER_MODEL
OPENROUTER_BASE_URL: str = "https://openrouter.ai/api/v1"

WORDPRESS_URL: str = os.environ.get("WORDPRESS_URL", "").rstrip("/")
WORDPRESS_ENDPOINT_PATH: str = os.environ.get(
    "WORDPRESS_ENDPOINT_PATH", "wp-json/tasteofcinemascraped/v1/import"
)
WORDPRESS_SECRET: str = os.environ.get("WORDPRESS_SECRET", "")


def get_wordpress_import_url() -> str:
    return f"{WORDPRESS_URL}/{WORDPRESS_ENDPOINT_PATH.lstrip('/')}"
