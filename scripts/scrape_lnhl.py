#!/usr/bin/env python3
"""Scrape Little NHL 2026 event data and team lists.

Sources:
  - lnhl.ca — official site (event info, team lists)
  - visitmarkham.ca/lnhl/ — host city event page
  - anishinabeknews.ca — news articles about LNHL 2026

Outputs:
  - data/lnhl_event.json — event details (dates, venues, divisions, schedule)
  - data/lnhl_teams.json — participating teams by division
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
    "lnhl": "https://www.lnhl.ca/",
    "lnhl_teams": "https://www.lnhl.ca/teams",
    "lnhl_schedule": "https://www.lnhl.ca/schedule",
    "lnhl_about": "https://www.lnhl.ca/about",
    "visitmarkham": "https://visitmarkham.ca/lnhl/",
    "anishinabek_news": "https://anishinabeknews.ca/?s=little+nhl",
}

# Hardcoded base data for LNHL 2026 — enriched by scraping
BASE_EVENT = {
    "name": "Little Native Hockey League (LNHL) Tournament 2026",
    "short_name": "Little NHL 2026",
    "year": 2026,
    "dates": {
        "start": "2026-03-15",
        "end": "2026-03-19",
        "note": "Dates are approximate — confirm from official source",
    },
    "location": {
        "city": "Markham",
        "province": "Ontario",
        "country": "Canada",
    },
    "venues": [],
    "divisions": [
        "Tyke (7-8)",
        "Novice (9-10)",
        "Atom (11-12)",
        "Peewee (13-14)",
        "Bantam (15-16)",
        "Midget (17-18)",
        "Girls",
    ],
    "organizer": "Little Native Hockey League Inc.",
    "website": "https://www.lnhl.ca/",
    "description": (
        "The Little Native Hockey League (LNHL) is the largest annual "
        "Indigenous hockey tournament in the world. First Nations communities "
        "from across Ontario send teams to compete in multiple age divisions."
    ),
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


def scrape_lnhl_main(soup: BeautifulSoup) -> dict:
    """Extract event info from the LNHL main page."""
    info = {}
    # Look for venue/arena info
    text = soup.get_text(separator="\n", strip=True)

    # Try to find dates
    date_patterns = [
        r"March\s+\d{1,2}\s*[-–]\s*\d{1,2},?\s*2026",
        r"March\s+\d{1,2}\s*[-–]\s*March\s+\d{1,2},?\s*2026",
        r"\d{1,2}\s*[-–]\s*\d{1,2}\s+March\s+2026",
    ]
    for pattern in date_patterns:
        match = re.search(pattern, text, re.IGNORECASE)
        if match:
            info["date_text"] = match.group(0)
            break

    # Look for arena/venue names
    arena_patterns = [
        r"(?:arena|rink|centre|center|complex)[\s:]+([^\n]+)",
        r"([^\n]*(?:arena|rink|centre|center|complex)[^\n]*)",
    ]
    venues = set()
    for pattern in arena_patterns:
        for match in re.finditer(pattern, text, re.IGNORECASE):
            venue = match.group(1).strip() if match.group(1) else match.group(0).strip()
            if len(venue) > 5 and len(venue) < 100:
                venues.add(venue)
    if venues:
        info["venues_found"] = list(venues)

    return info


def scrape_teams_page(soup: BeautifulSoup) -> list[dict]:
    """Extract team lists from the LNHL teams page."""
    teams = []
    if not soup:
        return teams

    # Try tables first
    for table in soup.find_all("table"):
        for row in table.find_all("tr"):
            cells = row.find_all(["td", "th"])
            if len(cells) >= 2:
                team_name = cells[0].get_text(strip=True)
                community = cells[1].get_text(strip=True) if len(cells) > 1 else ""
                division = cells[2].get_text(strip=True) if len(cells) > 2 else ""
                if team_name and team_name.lower() not in ("team", "name", "team name"):
                    teams.append({
                        "team_name": team_name,
                        "community": community,
                        "division": division,
                    })

    # If no tables, try list items
    if not teams:
        for li in soup.find_all("li"):
            text = li.get_text(strip=True)
            if text and len(text) > 3 and len(text) < 200:
                # Check if it looks like a team entry
                if any(kw in text.lower() for kw in [
                    "first nation", "nation", "hawks", "warriors", "bears",
                    "eagles", "wolves", "thunder", "braves", "chiefs",
                ]):
                    teams.append({"team_name": text, "community": "", "division": ""})

    # Also look for heading-grouped sections
    if not teams:
        current_division = ""
        for tag in soup.find_all(["h2", "h3", "h4", "p", "li", "div"]):
            text = tag.get_text(strip=True)
            if tag.name in ("h2", "h3", "h4"):
                if any(d.lower() in text.lower() for d in BASE_EVENT["divisions"]):
                    current_division = text
            elif current_division and text and len(text) > 3:
                teams.append({
                    "team_name": text,
                    "community": "",
                    "division": current_division,
                })

    return teams


def scrape_news_articles(soup: BeautifulSoup, base_url: str) -> list[dict]:
    """Extract LNHL-related news article summaries."""
    articles = []
    if not soup:
        return articles

    for article in soup.find_all("article")[:10]:
        title_tag = article.find(["h2", "h3"])
        if not title_tag:
            continue
        link = title_tag.find("a")
        title = title_tag.get_text(strip=True)
        url = link.get("href", "") if link else ""
        if not url.startswith("http"):
            url = urljoin(base_url, url)

        excerpt_tag = article.find("p")
        excerpt = excerpt_tag.get_text(strip=True) if excerpt_tag else ""

        date_tag = article.find("time")
        date = date_tag.get("datetime", date_tag.get_text(strip=True)) if date_tag else ""

        if "lnhl" in title.lower() or "little nhl" in title.lower() or "little native" in title.lower():
            articles.append({
                "title": title,
                "url": url,
                "excerpt": excerpt,
                "date": date,
            })

    return articles


def main():
    print("=== LNHL 2026 Event & Teams Scraper ===")
    OUTPUT_DIR.mkdir(parents=True, exist_ok=True)

    # Start with base event data
    event = dict(BASE_EVENT)
    event["scraped_at"] = datetime.now(timezone.utc).isoformat()
    event["sources"] = {}

    # Scrape main LNHL site
    soup = fetch_page(SOURCES["lnhl"])
    if soup:
        main_info = scrape_lnhl_main(soup)
        event["sources"]["lnhl_main"] = main_info
        if "date_text" in main_info:
            event["dates"]["scraped_text"] = main_info["date_text"]
        if "venues_found" in main_info:
            event["venues"] = main_info["venues_found"]

    # Scrape about page
    about_soup = fetch_page(SOURCES["lnhl_about"])
    if about_soup:
        about_text = about_soup.get_text(separator="\n", strip=True)[:3000]
        event["sources"]["lnhl_about"] = {"raw_text": about_text}

    # Scrape Visit Markham
    vm_soup = fetch_page(SOURCES["visitmarkham"])
    if vm_soup:
        vm_text = vm_soup.get_text(separator="\n", strip=True)[:3000]
        event["sources"]["visitmarkham"] = {"raw_text": vm_text}

    # Scrape news
    news_soup = fetch_page(SOURCES["anishinabek_news"])
    if news_soup:
        articles = scrape_news_articles(news_soup, SOURCES["anishinabek_news"])
        event["news_articles"] = articles
        print(f"  Found {len(articles)} LNHL news articles")

    # Write event data
    event_path = OUTPUT_DIR / "lnhl_event.json"
    event_path.write_text(json.dumps(event, indent=2, ensure_ascii=False))
    print(f"  Wrote {event_path}")

    # Scrape teams
    print("\n  Scraping team lists ...")
    teams_soup = fetch_page(SOURCES["lnhl_teams"])
    teams = scrape_teams_page(teams_soup) if teams_soup else []

    teams_data = {
        "scraped_at": datetime.now(timezone.utc).isoformat(),
        "source_url": SOURCES["lnhl_teams"],
        "team_count": len(teams),
        "teams": teams,
    }

    # If no teams found from the page, note it
    if not teams:
        teams_data["note"] = (
            "No teams could be extracted from the website. "
            "The teams page may require JavaScript or may not be published yet."
        )
        print("  WARNING: No teams extracted. Page may require JS rendering.")

    teams_path = OUTPUT_DIR / "lnhl_teams.json"
    teams_path.write_text(json.dumps(teams_data, indent=2, ensure_ascii=False))
    print(f"  Wrote {teams_path}")
    print(f"  Teams found: {len(teams)}")
    print("Done.")


if __name__ == "__main__":
    main()
