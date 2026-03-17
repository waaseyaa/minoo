#!/usr/bin/env python3
"""Download LNHL images from official and host city websites.

Sources:
  - lnhl.ca — official tournament photos
  - visitmarkham.ca/lnhl/ — host city event photos

Outputs:
  - data/lnhl_media.json — image metadata (url, alt, source, filename)
  - data/media/lnhl/*.jpg — downloaded image files (max 20)
"""

import hashlib
import json
import mimetypes
import re
import sys
from datetime import datetime, timezone
from pathlib import Path
from urllib.parse import urljoin, urlparse

import requests
from bs4 import BeautifulSoup

USER_AGENT = "MinooBot/1.0"
OUTPUT_DIR = Path(__file__).parent.parent / "data"
MEDIA_DIR = OUTPUT_DIR / "media" / "lnhl"
MAX_IMAGES = 20
MIN_IMAGE_SIZE = 5000  # 5KB minimum to skip icons/tracking pixels

SOURCES = [
    {"name": "lnhl.ca", "url": "https://www.lnhl.ca/"},
    {"name": "lnhl.ca/gallery", "url": "https://www.lnhl.ca/gallery"},
    {"name": "lnhl.ca/photos", "url": "https://www.lnhl.ca/photos"},
    {"name": "visitmarkham", "url": "https://visitmarkham.ca/lnhl/"},
]

# Skip these in image URLs
SKIP_PATTERNS = [
    "1x1", "pixel", "tracking", "favicon", "icon", "logo",
    "spinner", "loading", "spacer", "blank", "transparent",
    "analytics", "badge", "button", "arrow", "chevron",
    ".svg", ".gif", "data:image",
]


def fetch_page(url: str) -> BeautifulSoup | None:
    """Fetch a page and return a BeautifulSoup object."""
    print(f"  Fetching {url} ...")
    try:
        resp = requests.get(url, headers={"User-Agent": USER_AGENT}, timeout=30)
        resp.raise_for_status()
        return BeautifulSoup(resp.text, "lxml")
    except requests.RequestException as e:
        print(f"  WARNING: Could not fetch {url}: {e}")
        return None


def extract_images(soup: BeautifulSoup, base_url: str, source_name: str) -> list[dict]:
    """Extract image metadata from a page."""
    images = []
    seen_urls = set()

    for img in soup.find_all("img"):
        src = img.get("src", "") or img.get("data-src", "") or img.get("data-lazy-src", "")
        if not src:
            continue

        # Resolve relative URLs
        if not src.startswith("http"):
            src = urljoin(base_url, src)

        # Skip duplicates
        if src in seen_urls:
            continue

        # Skip icons, tracking pixels, etc.
        src_lower = src.lower()
        if any(skip in src_lower for skip in SKIP_PATTERNS):
            continue

        seen_urls.add(src)

        # Get srcset for higher-res versions
        srcset = img.get("srcset", "")
        best_src = src
        if srcset:
            # Parse srcset and pick the largest
            parts = [p.strip() for p in srcset.split(",") if p.strip()]
            max_width = 0
            for part in parts:
                tokens = part.split()
                if len(tokens) >= 2:
                    url_part = tokens[0]
                    descriptor = tokens[1]
                    width_match = re.match(r"(\d+)w", descriptor)
                    if width_match and int(width_match.group(1)) > max_width:
                        max_width = int(width_match.group(1))
                        best_src = url_part if url_part.startswith("http") else urljoin(base_url, url_part)

        images.append({
            "url": best_src,
            "original_url": src,
            "alt": img.get("alt", ""),
            "title": img.get("title", ""),
            "width": img.get("width"),
            "height": img.get("height"),
            "source": source_name,
            "source_page": base_url,
        })

    # Also check for background images in style attributes
    for tag in soup.find_all(style=re.compile(r"background-image")):
        style = tag.get("style", "")
        bg_match = re.search(r"url\(['\"]?([^'\")\s]+)['\"]?\)", style)
        if bg_match:
            url = bg_match.group(1)
            if not url.startswith("http"):
                url = urljoin(base_url, url)
            if url not in seen_urls:
                seen_urls.add(url)
                images.append({
                    "url": url,
                    "original_url": url,
                    "alt": "",
                    "title": "",
                    "source": source_name,
                    "source_page": base_url,
                    "type": "background",
                })

    return images


def download_image(url: str, index: int) -> dict | None:
    """Download an image and return file info."""
    try:
        resp = requests.get(
            url,
            headers={"User-Agent": USER_AGENT},
            timeout=30,
            stream=True,
        )
        resp.raise_for_status()

        content_type = resp.headers.get("Content-Type", "")
        if not content_type.startswith("image/"):
            return None

        content = resp.content
        if len(content) < MIN_IMAGE_SIZE:
            return None

        # Determine extension
        ext = mimetypes.guess_extension(content_type.split(";")[0].strip()) or ".jpg"
        if ext == ".jpeg":
            ext = ".jpg"
        if ext == ".jpe":
            ext = ".jpg"

        # Generate filename
        url_hash = hashlib.md5(url.encode()).hexdigest()[:8]
        filename = f"lnhl_{index:03d}_{url_hash}{ext}"
        filepath = MEDIA_DIR / filename

        filepath.write_bytes(content)
        return {
            "filename": filename,
            "size_bytes": len(content),
            "content_type": content_type,
        }

    except requests.RequestException as e:
        print(f"    Download failed: {e}")
        return None


def main():
    print("=== LNHL Media Scraper ===")
    MEDIA_DIR.mkdir(parents=True, exist_ok=True)

    all_images = []

    # Collect images from all sources
    for source in SOURCES:
        soup = fetch_page(source["url"])
        if soup:
            images = extract_images(soup, source["url"], source["name"])
            all_images.extend(images)
            print(f"  Found {len(images)} images from {source['name']}")

    # Deduplicate by URL
    seen = set()
    unique_images = []
    for img in all_images:
        if img["url"] not in seen:
            seen.add(img["url"])
            unique_images.append(img)

    print(f"\n  Total unique images: {len(unique_images)}")
    print(f"  Downloading up to {MAX_IMAGES} images ...")

    # Download images (up to MAX_IMAGES)
    downloaded = 0
    for i, img in enumerate(unique_images):
        if downloaded >= MAX_IMAGES:
            break

        print(f"  [{downloaded + 1}/{MAX_IMAGES}] Downloading {img['url'][:80]} ...")
        file_info = download_image(img["url"], downloaded + 1)
        if file_info:
            img["downloaded"] = True
            img["local_filename"] = file_info["filename"]
            img["file_size_bytes"] = file_info["size_bytes"]
            downloaded += 1
        else:
            img["downloaded"] = False
            img["skip_reason"] = "Too small, not an image, or download failed"

    # Write metadata
    media_data = {
        "scraped_at": datetime.now(timezone.utc).isoformat(),
        "sources": [s["url"] for s in SOURCES],
        "total_found": len(unique_images),
        "total_downloaded": downloaded,
        "download_dir": "data/media/lnhl/",
        "images": unique_images[:MAX_IMAGES * 2],  # Include some non-downloaded for reference
    }

    media_path = OUTPUT_DIR / "lnhl_media.json"
    media_path.write_text(json.dumps(media_data, indent=2, ensure_ascii=False))
    print(f"\n  Wrote {media_path}")
    print(f"  Downloaded {downloaded} images to {MEDIA_DIR}")
    print("Done.")


if __name__ == "__main__":
    main()
