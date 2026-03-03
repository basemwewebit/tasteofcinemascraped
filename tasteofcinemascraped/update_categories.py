import os
import subprocess
import json
import time

# Update Python path so it can import from tasteofcinemascraped module
import sys
sys.path.append(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from tasteofcinemascraped.translator import _derive_primary_category

def run_wp_cmd(args):
    """Run a WP-CLI command and return its JSON output."""
    cmd = ["wp"] + args + ["--format=json"]
    result = subprocess.run(cmd, capture_output=True, text=True)
    if result.returncode != 0:
        print(f"WP-CLI error: {result.stderr}")
        return None
    try:
        return json.loads(result.stdout)
    except json.JSONDecodeError:
        print(f"Failed to decode JSON: {result.stdout}")
        return None

def update_post_category(post_id, new_slug):
    """Update post category using WP-CLI."""
    # First, clear existing categories (we only want one)
    subprocess.run(["wp", "post", "term", "set", str(post_id), "category", new_slug], capture_output=True)

def main():
    print("Fetching all posts from WordPress...")
    # Fetch posts (limit to 200 for now, could be chunked if needed)
    posts = run_wp_cmd(["post", "list", "--post_type=post", "--posts_per_page=-1", "--fields=ID,post_title,post_content"])
    
    if not posts:
        print("No posts found or failed to fetch posts.")
        return

    print(f"Found {len(posts)} posts. Starting AI classification...")
    
    updated_count = 0
    failed_count = 0

    for idx, post in enumerate(posts):
        post_id = post.get("ID")
        title = post.get("post_title", "")
        content = post.get("post_content", "")
        
        # Strip HTML for excerpt
        import re
        excerpt = re.sub(r"<[^>]+>", " ", content)
        excerpt = re.sub(r"\s+", " ", excerpt).strip()[:400]
        
        print(f"[{idx+1}/{len(posts)}] Processing Post {post_id}: '{title}'...")
        
        # Rate limiting to avoid OpenRouter 429 errors (if many posts)
        time.sleep(1)
        
        try:
            primary = _derive_primary_category(title, excerpt)
            if primary and primary.get("slug"):
                slug = primary["slug"]
                update_post_category(post_id, slug)
                print(f"  -> Assigned Category: {primary.get('name')} ({slug})")
                updated_count += 1
            else:
                print(f"  -> AI Failed to assign a category. Defaulting to film-lists.")
                update_post_category(post_id, "film-lists")
                failed_count += 1
        except Exception as e:
            print(f"  -> Error API call: {e}. Defaulting to film-lists.")
            update_post_category(post_id, "film-lists")
            failed_count += 1

    print("\n--- Summary ---")
    print(f"Total Posts Processed: {len(posts)}")
    print(f"Successfully Assigned by AI: {updated_count}")
    print(f"Failed/Defaulted: {failed_count}")

if __name__ == "__main__":
    main()
