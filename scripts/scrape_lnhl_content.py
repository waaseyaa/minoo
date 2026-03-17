#!/usr/bin/env python3
"""Curate teaching content about the Little NHL tournament.

Sources:
  - Wikipedia — Little NHL article
  - Canadian Geographic — LNHL coverage
  - NHL.com — Indigenous hockey articles

Outputs:
  - data/lnhl_teaching.json — structured teaching data
  - data/lnhl_teaching_raw.txt — raw source material for reference
"""

import json
import re
import sys
from datetime import datetime, timezone
from pathlib import Path
from urllib.parse import urljoin

import requests
from bs4 import BeautifulSoup

USER_AGENT = "MinooBot/1.0"
OUTPUT_DIR = Path(__file__).parent.parent / "data"

SOURCES = {
    "wikipedia": "https://en.wikipedia.org/wiki/Little_Native_Hockey_League",
    "canadian_geographic": "https://canadiangeographic.ca/?s=little+native+hockey+league",
    "nhl_indigenous": "https://www.nhl.com/news/topic/hockey-is-for-everyone",
}

# Hardcoded teaching structure — enriched by scraping
BASE_TEACHING = {
    "title": "Little Native Hockey League (LNHL)",
    "subtitle": "The World's Largest Indigenous Hockey Tournament",
    "origin": {
        "year_founded": 1971,
        "founding_community": "Little Current, Ontario",
        "founding_story": (
            "The Little Native Hockey League was founded in 1971 in Little Current, "
            "Manitoulin Island, Ontario. What started as a small gathering of First "
            "Nations hockey teams has grown into the largest annual Indigenous hockey "
            "tournament in the world."
        ),
    },
    "founders": {
        "note": "Founded by First Nations community leaders on Manitoulin Island",
        "details": "To be enriched from source material",
    },
    "four_pillars": [
        {
            "name": "Culture",
            "description": "Celebrating Indigenous culture and identity through sport",
        },
        {
            "name": "Community",
            "description": "Bringing First Nations communities together",
        },
        {
            "name": "Competition",
            "description": "Healthy competition and athletic excellence",
        },
        {
            "name": "Character",
            "description": "Building character and leadership in youth",
        },
    ],
    "significance": {
        "cultural": (
            "The LNHL is more than a hockey tournament — it is a cultural gathering "
            "that strengthens connections between First Nations communities across Ontario. "
            "It provides Indigenous youth with positive role models and a sense of pride."
        ),
        "sporting": (
            "Many LNHL alumni have gone on to play professional hockey, including in the "
            "NHL and OHL. The tournament has been a launching pad for Indigenous hockey talent."
        ),
    },
    "notable_alumni": [],
    "statistics": {
        "annual_participants": "Approximately 2,000+ players",
        "communities": "150+ First Nations communities",
        "divisions": 7,
        "years_running": 55,
    },
}


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


def scrape_wikipedia(soup: BeautifulSoup) -> dict:
    """Extract structured content from the Wikipedia article."""
    result = {"sections": [], "body_text": "", "notable_players": []}
    if not soup:
        return result

    content = soup.find("div", {"id": "mw-content-text"})
    if not content:
        return result

    # Extract main paragraphs
    paragraphs = []
    for p in content.find_all("p", recursive=True):
        text = p.get_text(strip=True)
        if text and len(text) > 30:
            # Skip reference-heavy lines
            text = re.sub(r"\[\d+\]", "", text)
            paragraphs.append(text)

    result["body_text"] = "\n\n".join(paragraphs[:20])

    # Extract section headings
    for heading in content.find_all(["h2", "h3"]):
        span = heading.find("span", class_="mw-headline")
        if span:
            section_title = span.get_text(strip=True)
            if section_title.lower() not in ("references", "external links", "see also", "notes"):
                result["sections"].append(section_title)

    # Look for notable players/alumni in lists
    for li in content.find_all("li"):
        text = li.get_text(strip=True)
        # Heuristic: if it mentions NHL, OHL, or has a linked player name
        if any(kw in text for kw in ["NHL", "OHL", "WHL", "AHL"]):
            result["notable_players"].append(re.sub(r"\[\d+\]", "", text).strip())

    return result


def scrape_canadian_geographic(soup: BeautifulSoup) -> list[dict]:
    """Extract relevant articles from Canadian Geographic search."""
    articles = []
    if not soup:
        return articles

    for article in soup.find_all("article")[:5]:
        title_tag = article.find(["h2", "h3"])
        if not title_tag:
            continue
        link = title_tag.find("a")
        title = title_tag.get_text(strip=True)
        url = link.get("href", "") if link else ""
        excerpt_tag = article.find("p")
        excerpt = excerpt_tag.get_text(strip=True) if excerpt_tag else ""

        if any(kw in title.lower() for kw in ["hockey", "indigenous", "first nation", "lnhl", "native"]):
            articles.append({"title": title, "url": url, "excerpt": excerpt})

    return articles


def main():
    print("=== LNHL Teaching Content Scraper ===")
    OUTPUT_DIR.mkdir(parents=True, exist_ok=True)

    teaching = dict(BASE_TEACHING)
    teaching["scraped_at"] = datetime.now(timezone.utc).isoformat()
    teaching["sources"] = []
    raw_parts = []

    # Wikipedia
    wiki_soup = fetch_page(SOURCES["wikipedia"])
    wiki_data = scrape_wikipedia(wiki_soup)
    if wiki_data["body_text"]:
        teaching["body_text"] = wiki_data["body_text"]
        teaching["sections"] = wiki_data["sections"]
        teaching["sources"].append({
            "name": "Wikipedia",
            "url": SOURCES["wikipedia"],
            "type": "encyclopedia",
        })
        raw_parts.append(f"=== Wikipedia: Little Native Hockey League ===\n\n{wiki_data['body_text']}")

        if wiki_data["notable_players"]:
            teaching["notable_alumni"] = wiki_data["notable_players"]
            print(f"  Found {len(wiki_data['notable_players'])} notable alumni references")
    else:
        teaching["body_text"] = teaching["significance"]["cultural"]
        print("  Wikipedia article not available, using base content.")

    # Canadian Geographic
    cg_soup = fetch_page(SOURCES["canadian_geographic"])
    cg_articles = scrape_canadian_geographic(cg_soup)
    if cg_articles:
        teaching["related_articles"] = cg_articles
        teaching["sources"].append({
            "name": "Canadian Geographic",
            "url": SOURCES["canadian_geographic"],
            "type": "magazine",
        })
        for art in cg_articles:
            raw_parts.append(f"=== Canadian Geographic: {art['title']} ===\n{art.get('excerpt', '')}")
        print(f"  Found {len(cg_articles)} Canadian Geographic articles")

    # NHL.com (best-effort)
    nhl_soup = fetch_page(SOURCES["nhl_indigenous"])
    if nhl_soup:
        teaching["sources"].append({
            "name": "NHL.com",
            "url": SOURCES["nhl_indigenous"],
            "type": "sports_media",
        })
        # Extract any text mentioning LNHL or Little NHL
        nhl_text = nhl_soup.get_text(separator="\n", strip=True)
        lnhl_mentions = []
        for line in nhl_text.split("\n"):
            if "little" in line.lower() and ("nhl" in line.lower() or "native" in line.lower()):
                lnhl_mentions.append(line.strip())
        if lnhl_mentions:
            raw_parts.append("=== NHL.com mentions ===\n" + "\n".join(lnhl_mentions[:10]))
            print(f"  Found {len(lnhl_mentions)} LNHL mentions on NHL.com")

    # Write structured teaching data
    teaching_path = OUTPUT_DIR / "lnhl_teaching.json"
    teaching_path.write_text(json.dumps(teaching, indent=2, ensure_ascii=False))
    print(f"  Wrote {teaching_path}")

    # Write raw source material
    raw_path = OUTPUT_DIR / "lnhl_teaching_raw.txt"
    raw_content = (
        f"LNHL Teaching Raw Source Material\n"
        f"Scraped: {datetime.now(timezone.utc).isoformat()}\n"
        f"{'=' * 60}\n\n"
    )
    raw_content += "\n\n".join(raw_parts) if raw_parts else "No raw content extracted."
    raw_path.write_text(raw_content)
    print(f"  Wrote {raw_path}")
    print("Done.")


if __name__ == "__main__":
    main()
