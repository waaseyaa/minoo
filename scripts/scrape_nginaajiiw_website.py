#!/usr/bin/env python3
"""Scrape Nginaajiiw Salon & Spa website for business data.

Extracts business name, services, hours, contact info, and images from
the Square site. Uses JSON-LD, Open Graph meta tags, and falls back to
raw text extraction when structured data is unavailable.

Output: data/nginaajiiw_website.json
"""

import json
import re
import sys
from datetime import datetime, timezone
from pathlib import Path

import requests
from bs4 import BeautifulSoup

URL = "https://nginaajiiw-salon-spa.square.site/"
USER_AGENT = "MinooBot/1.0"
OUTPUT_DIR = Path(__file__).parent.parent / "data"


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


def extract_json_ld(soup: BeautifulSoup) -> list[dict]:
    """Extract all JSON-LD blocks from the page."""
    results = []
    for tag in soup.find_all("script", type="application/ld+json"):
        try:
            data = json.loads(tag.string)
            if isinstance(data, list):
                results.extend(data)
            else:
                results.append(data)
        except (json.JSONDecodeError, TypeError):
            continue
    return results


def extract_open_graph(soup: BeautifulSoup) -> dict:
    """Extract Open Graph meta tags."""
    og = {}
    for meta in soup.find_all("meta", property=re.compile(r"^og:")):
        key = meta.get("property", "").replace("og:", "")
        value = meta.get("content", "")
        if key and value:
            og[key] = value
    return og


def extract_images(soup: BeautifulSoup) -> list[dict]:
    """Extract image URLs and alt text."""
    images = []
    seen = set()
    for img in soup.find_all("img"):
        src = img.get("src", "")
        if not src or src in seen:
            continue
        # Skip tiny tracking pixels and icons
        if any(skip in src.lower() for skip in ["1x1", "pixel", "tracking", "favicon"]):
            continue
        seen.add(src)
        images.append({
            "src": src,
            "alt": img.get("alt", ""),
            "width": img.get("width"),
            "height": img.get("height"),
        })
    return images


def extract_raw_text(soup: BeautifulSoup) -> str:
    """Extract visible text from the page body."""
    body = soup.find("body")
    if not body:
        return ""
    # Remove script and style elements
    for tag in body.find_all(["script", "style", "noscript"]):
        tag.decompose()
    text = body.get_text(separator="\n", strip=True)
    # Collapse multiple blank lines
    text = re.sub(r"\n{3,}", "\n\n", text)
    return text[:5000]  # Cap at 5000 chars


def extract_contact_info(soup: BeautifulSoup, raw_text: str) -> dict:
    """Try to extract phone, email, address from the page."""
    contact = {}
    # Phone
    phone_match = re.search(r"(\(?\d{3}\)?[\s.-]?\d{3}[\s.-]?\d{4})", raw_text)
    if phone_match:
        contact["phone"] = phone_match.group(1)
    # Email
    email_match = re.search(r"[\w.+-]+@[\w.-]+\.\w{2,}", raw_text)
    if email_match:
        contact["email"] = email_match.group(0)
    # Links
    for a in soup.find_all("a", href=True):
        href = a["href"]
        if href.startswith("tel:") and "phone" not in contact:
            contact["phone"] = href.replace("tel:", "")
        elif href.startswith("mailto:") and "email" not in contact:
            contact["email"] = href.replace("mailto:", "")
    return contact


def main():
    print("=== Nginaajiiw Salon & Spa Website Scraper ===")
    OUTPUT_DIR.mkdir(parents=True, exist_ok=True)

    soup = fetch_page(URL)
    if not soup:
        print("ERROR: Could not fetch the website. Writing empty result.")
        result = {
            "source_url": URL,
            "scraped_at": datetime.now(timezone.utc).isoformat(),
            "error": "Could not fetch the website",
        }
        (OUTPUT_DIR / "nginaajiiw_website.json").write_text(
            json.dumps(result, indent=2, ensure_ascii=False)
        )
        return

    print("  Extracting structured data ...")
    json_ld = extract_json_ld(soup)
    og = extract_open_graph(soup)
    images = extract_images(soup)
    raw_text = extract_raw_text(soup)
    contact = extract_contact_info(soup, raw_text)

    # Page title
    title_tag = soup.find("title")
    title = title_tag.get_text(strip=True) if title_tag else ""

    # Meta description
    meta_desc = soup.find("meta", attrs={"name": "description"})
    description = meta_desc.get("content", "") if meta_desc else ""

    result = {
        "source_url": URL,
        "scraped_at": datetime.now(timezone.utc).isoformat(),
        "business_name": og.get("title", title),
        "description": og.get("description", description),
        "title": title,
        "open_graph": og,
        "json_ld": json_ld,
        "contact": contact,
        "images": images,
        "raw_text": raw_text if not json_ld else "",
    }

    output_path = OUTPUT_DIR / "nginaajiiw_website.json"
    output_path.write_text(json.dumps(result, indent=2, ensure_ascii=False))
    print(f"  Wrote {output_path}")
    print(f"  JSON-LD blocks: {len(json_ld)}")
    print(f"  Images found: {len(images)}")
    print(f"  Contact info: {contact or 'none found'}")
    print("Done.")


if __name__ == "__main__":
    main()
