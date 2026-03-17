#!/usr/bin/env python3
"""Scrape Nginaajiiw Salon & Spa social media profiles.

Instagram: nginaajiiw.salonandspa — fetches last 12 posts via instaloader.
Facebook: best-effort metadata scraping (login wall likely blocks most data).

Output: data/nginaajiiw_social.json
"""

import json
import sys
from datetime import datetime, timezone
from pathlib import Path

import requests

USER_AGENT = "MinooBot/1.0"
OUTPUT_DIR = Path(__file__).parent.parent / "data"

INSTAGRAM_USERNAME = "nginaajiiw.salonandspa"
FACEBOOK_PAGE_URL = "https://www.facebook.com/nginaajiiw"


def scrape_instagram(username: str, max_posts: int = 12) -> dict:
    """Scrape Instagram profile and recent posts via instaloader."""
    print(f"  Scraping Instagram @{username} ...")
    result = {
        "platform": "instagram",
        "username": username,
        "profile_url": f"https://www.instagram.com/{username}/",
        "posts": [],
        "error": None,
    }

    try:
        import instaloader

        loader = instaloader.Instaloader(
            download_pictures=False,
            download_videos=False,
            download_video_thumbnails=False,
            download_geotags=False,
            download_comments=False,
            save_metadata=False,
            compress_json=False,
            quiet=True,
        )

        try:
            profile = instaloader.Profile.from_username(loader.context, username)
            result["full_name"] = profile.full_name
            result["biography"] = profile.biography
            result["followers"] = profile.followers
            result["following"] = profile.followees
            result["post_count"] = profile.mediacount
            result["is_private"] = profile.is_private
            result["profile_pic_url"] = profile.profile_pic_url

            if not profile.is_private:
                count = 0
                for post in profile.get_posts():
                    if count >= max_posts:
                        break
                    result["posts"].append({
                        "shortcode": post.shortcode,
                        "url": f"https://www.instagram.com/p/{post.shortcode}/",
                        "caption": post.caption or "",
                        "likes": post.likes,
                        "comments": post.comments,
                        "date": post.date_utc.isoformat(),
                        "is_video": post.is_video,
                        "hashtags": list(post.caption_hashtags) if post.caption_hashtags else [],
                    })
                    count += 1
                    print(f"    Post {count}/{max_posts}: {post.shortcode}")
            else:
                result["error"] = "Profile is private"
                print("    Profile is private, cannot fetch posts.")

        except instaloader.exceptions.ProfileNotExistsException:
            result["error"] = f"Profile @{username} does not exist"
            print(f"    Profile @{username} not found.")
        except instaloader.exceptions.ConnectionException as e:
            result["error"] = f"Connection error: {e}"
            print(f"    Connection error: {e}")
        except Exception as e:
            result["error"] = f"Instagram error: {e}"
            print(f"    Error: {e}")

    except ImportError:
        result["error"] = "instaloader not installed"
        print("    WARNING: instaloader not installed, skipping Instagram.")

    return result


def scrape_facebook(page_url: str) -> dict:
    """Best-effort Facebook page metadata scraping.

    Facebook aggressively blocks scrapers and requires login for most
    content. This extracts only publicly available Open Graph metadata.
    """
    print(f"  Scraping Facebook {page_url} (best-effort) ...")
    result = {
        "platform": "facebook",
        "page_url": page_url,
        "error": None,
    }

    try:
        resp = requests.get(
            page_url,
            headers={"User-Agent": USER_AGENT},
            timeout=30,
            allow_redirects=True,
        )

        if resp.status_code == 200:
            from bs4 import BeautifulSoup

            soup = BeautifulSoup(resp.text, "lxml")

            # Extract Open Graph metadata (often available without login)
            for meta in soup.find_all("meta", property=True):
                prop = meta.get("property", "")
                content = meta.get("content", "")
                if prop.startswith("og:") and content:
                    key = prop.replace("og:", "")
                    result[key] = content

            title = soup.find("title")
            if title:
                result["page_title"] = title.get_text(strip=True)

            if len(result) <= 3:  # Only platform, page_url, error
                result["error"] = "Login wall — no public data extracted"
                print("    Login wall detected, minimal data extracted.")
            else:
                print(f"    Extracted {len(result) - 3} metadata fields.")
        else:
            result["error"] = f"HTTP {resp.status_code}"
            print(f"    HTTP {resp.status_code}")

    except requests.RequestException as e:
        result["error"] = f"Request failed: {e}"
        print(f"    Request failed: {e}")

    return result


def main():
    print("=== Nginaajiiw Social Media Scraper ===")
    OUTPUT_DIR.mkdir(parents=True, exist_ok=True)

    instagram = scrape_instagram(INSTAGRAM_USERNAME)
    facebook = scrape_facebook(FACEBOOK_PAGE_URL)

    result = {
        "scraped_at": datetime.now(timezone.utc).isoformat(),
        "instagram": instagram,
        "facebook": facebook,
    }

    output_path = OUTPUT_DIR / "nginaajiiw_social.json"
    output_path.write_text(json.dumps(result, indent=2, ensure_ascii=False))
    print(f"  Wrote {output_path}")
    print(f"  Instagram posts: {len(instagram.get('posts', []))}")
    print("Done.")


if __name__ == "__main__":
    main()
