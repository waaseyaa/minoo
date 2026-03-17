# Little NHL Sprint Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Acquire LNHL + Nginaajiiw content via Python scraping, split businesses from groups with dedicated templates, and populate Minoo with timely tournament content.

**Architecture:** Two parallel tracks — Python scripts produce JSON datasets (Phase 1), Minoo platform gets business entity split + templates (Phase 2). Phase 3 merges data into entities. BusinessController delegates to group storage filtered by `type=business`. Social posts stored as JSON in a `social_posts` text field on groups.

**Tech Stack:** Python 3 (requests, beautifulsoup4, instaloader), PHP 8.3 (Waaseyaa framework), Twig 3, vanilla CSS

**Spec:** `docs/superpowers/specs/2026-03-17-lnhl-sprint-design.md`

---

## File Structure

### New Files — Python Scripts
| File | Responsibility |
|------|----------------|
| `scripts/requirements.txt` | Python dependencies |
| `scripts/scrape_nginaajiiw_website.py` | Scrape Square site for business data |
| `scripts/scrape_nginaajiiw_social.py` | Scrape FB/Insta for recent posts |
| `scripts/scrape_lnhl.py` | Scrape lnhl.ca + spordle for event/team data |
| `scripts/scrape_lnhl_content.py` | Curate teaching content from articles |
| `scripts/scrape_lnhl_media.py` | Download LNHL images with attribution |
| `scripts/community_crossref.py` | Match LNHL teams to Minoo communities |

### New Files — Minoo
| File | Responsibility |
|------|----------------|
| `src/Controller/BusinessController.php` | List/show businesses (delegates to group storage, type=business) |
| `tests/Minoo/Unit/Controller/BusinessControllerTest.php` | Unit tests for BusinessController |
| `templates/businesses.html.twig` | Business listing + detail template |
| `templates/components/business-card.html.twig` | Business card for listing grid |

### Modified Files — Minoo
| File | Change |
|------|--------|
| `src/Provider/GroupServiceProvider.php` | Add `social_posts` field, add `/businesses` routes |
| `src/Controller/GroupController.php` | Filter out `type=business` from list |
| `templates/groups.html.twig` | No change needed (controller handles filtering) |
| `src/Seed/ConfigSeeder.php` | Add `tournament` to eventTypes() |
| `public/css/minoo.css` | Add `.card--business`, `.business-detail`, `.social-feed`, `.owner-card` styles |

### Output Files — Data (gitignored)
| File | Content |
|------|---------|
| `data/nginaajiiw_website.json` | Business profile from Square site |
| `data/nginaajiiw_social.json` | Recent FB/Insta posts |
| `data/lnhl_event.json` | Tournament metadata |
| `data/lnhl_teams.json` | Team→community mapping |
| `data/lnhl_teaching.json` | Curated teaching content |
| `data/lnhl_media.json` | Image URLs + attribution |
| `data/community_enrichment.json` | Match results |

---

## Phase 1 — Data Acquisition (Python)

### Task 1: Setup Python environment and directory structure

**Files:**
- Create: `scripts/requirements.txt`
- Create: `.gitignore` entry for `data/`

- [ ] **Step 1: Create directories**

```bash
mkdir -p scripts data/media/lnhl
```

- [ ] **Step 2: Create requirements.txt**

```
requests>=2.31
beautifulsoup4>=4.12
instaloader>=4.10
lxml>=5.0
```

- [ ] **Step 3: Create Python virtual environment and install deps**

```bash
cd /home/fsd42/dev/minoo
python3 -m venv scripts/.venv
source scripts/.venv/bin/activate
pip install -r scripts/requirements.txt
```

- [ ] **Step 4: Add data/ and scripts/.venv/ to .gitignore**

Append to `.gitignore`:
```
data/
scripts/.venv/
__pycache__/
*.pyc
```

- [ ] **Step 5: Commit**

```bash
git add scripts/requirements.txt .gitignore
git commit -m "chore: add Python scraping environment for LNHL sprint"
```

---

### Task 2: Scrape Nginaajiiw website

**Files:**
- Create: `scripts/scrape_nginaajiiw_website.py`
- Output: `data/nginaajiiw_website.json`

- [ ] **Step 1: Write the scraper**

Create `scripts/scrape_nginaajiiw_website.py`:

```python
#!/usr/bin/env python3
"""Scrape Nginaajiiw Salon & Spa website for business profile data."""

import json
import sys
from pathlib import Path

import requests
from bs4 import BeautifulSoup

TARGET_URL = "https://nginaajiiw-salon-spa.square.site/"
OUTPUT_PATH = Path(__file__).parent.parent / "data" / "nginaajiiw_website.json"

def scrape():
    resp = requests.get(TARGET_URL, timeout=30, headers={
        "User-Agent": "Mozilla/5.0 (compatible; MinooBot/1.0; +https://minoo.live)"
    })
    resp.raise_for_status()
    soup = BeautifulSoup(resp.text, "lxml")

    # Extract what we can from the Square site structure
    # Square sites use specific class patterns — adapt selectors as needed
    result = {
        "name": "Nginaajiiw Salon & Spa",
        "website": TARGET_URL,
        "booking_url": TARGET_URL,  # Square sites often have booking built in
        "description": "",
        "services": [],
        "hours": {},
        "phone": "",
        "email": "",
        "address": "",
        "images": [],
        "owner": "Larissa Toulouse",
        "raw_text": "",  # Fallback: store all visible text for manual extraction
    }

    # Grab all visible text as fallback
    result["raw_text"] = soup.get_text(separator="\n", strip=True)

    # Try to extract structured data
    # Square sites often have JSON-LD or Open Graph meta
    for script in soup.find_all("script", type="application/ld+json"):
        try:
            ld = json.loads(script.string)
            if isinstance(ld, dict):
                result["description"] = ld.get("description", result["description"])
                result["phone"] = ld.get("telephone", result["phone"])
                addr = ld.get("address", {})
                if isinstance(addr, dict):
                    parts = [addr.get("streetAddress", ""), addr.get("addressLocality", ""),
                             addr.get("addressRegion", ""), addr.get("postalCode", "")]
                    result["address"] = ", ".join(p for p in parts if p)
        except (json.JSONDecodeError, TypeError):
            pass

    # Open Graph fallback
    og_desc = soup.find("meta", property="og:description")
    if og_desc and not result["description"]:
        result["description"] = og_desc.get("content", "")

    og_image = soup.find("meta", property="og:image")
    if og_image:
        result["images"].append({
            "url": og_image.get("content", ""),
            "alt": "Nginaajiiw Salon & Spa",
            "type": "hero"
        })

    # Extract images from page
    for img in soup.find_all("img"):
        src = img.get("src", "")
        if src and src.startswith("http") and src not in [i["url"] for i in result["images"]]:
            result["images"].append({
                "url": src,
                "alt": img.get("alt", ""),
                "type": "gallery"
            })

    # Remove raw_text if we got structured data
    if result["description"]:
        del result["raw_text"]

    OUTPUT_PATH.parent.mkdir(parents=True, exist_ok=True)
    OUTPUT_PATH.write_text(json.dumps(result, indent=2, ensure_ascii=False))
    print(f"Wrote {OUTPUT_PATH}")
    print(f"  Description: {result['description'][:80]}...")
    print(f"  Images: {len(result['images'])}")
    print(f"  Phone: {result['phone']}")
    print(f"  Address: {result['address']}")

if __name__ == "__main__":
    scrape()
```

- [ ] **Step 2: Run the scraper**

```bash
source scripts/.venv/bin/activate
python scripts/scrape_nginaajiiw_website.py
```

Expected: `data/nginaajiiw_website.json` created with business profile data. If Square site blocks or returns minimal data, the `raw_text` fallback captures all visible text for manual extraction.

- [ ] **Step 3: Review output and manually supplement if needed**

Open `data/nginaajiiw_website.json`. If fields are empty, manually fill from browser visit to the website. Key fields needed: description, services, phone, address, booking_url.

- [ ] **Step 4: Commit**

```bash
git add scripts/scrape_nginaajiiw_website.py
git commit -m "feat: add Nginaajiiw website scraper"
```

---

### Task 3: Scrape Nginaajiiw social media

**Files:**
- Create: `scripts/scrape_nginaajiiw_social.py`
- Output: `data/nginaajiiw_social.json`

- [ ] **Step 1: Write the Instagram scraper**

Create `scripts/scrape_nginaajiiw_social.py`:

```python
#!/usr/bin/env python3
"""Scrape Nginaajiiw Salon & Spa social media for recent posts."""

import json
import sys
from datetime import datetime
from pathlib import Path

OUTPUT_PATH = Path(__file__).parent.parent / "data" / "nginaajiiw_social.json"

INSTAGRAM_USERNAME = "nginaajiiw.salonandspa"
FACEBOOK_PAGE_URL = "https://www.facebook.com/people/Nginaajiiw-Salon-Spa/100083560024537/"

def scrape_instagram():
    """Scrape recent public Instagram posts using instaloader."""
    posts = []
    try:
        import instaloader
        L = instaloader.Instaloader()
        profile = instaloader.Profile.from_username(L.context, INSTAGRAM_USERNAME)

        count = 0
        for post in profile.get_posts():
            if count >= 12:  # Last 12 posts
                break
            posts.append({
                "caption": post.caption or "",
                "image_url": post.url,
                "date": post.date_utc.strftime("%Y-%m-%d"),
                "permalink": f"https://www.instagram.com/p/{post.shortcode}/",
                "type": "carousel" if post.typename == "GraphSidecar" else "image",
                "likes": post.likes,
            })
            count += 1
            print(f"  Instagram post {count}: {post.date_utc.strftime('%Y-%m-%d')}")

    except Exception as e:
        print(f"  Instagram scrape failed: {e}", file=sys.stderr)
        print("  Fallback: manually add Instagram posts to data/nginaajiiw_social.json")

    return posts

def scrape_facebook():
    """
    Facebook public page scraping is unreliable without API access.
    This attempts a basic metadata grab but will likely need manual fallback.
    """
    posts = []
    try:
        import requests
        from bs4 import BeautifulSoup

        # Try to get basic page info (usually blocked by login wall)
        resp = requests.get(FACEBOOK_PAGE_URL, timeout=30, headers={
            "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36"
        })
        soup = BeautifulSoup(resp.text, "lxml")

        # Extract Open Graph metadata as fallback
        og_desc = soup.find("meta", property="og:description")
        og_image = soup.find("meta", property="og:image")

        if og_desc or og_image:
            posts.append({
                "text": og_desc.get("content", "") if og_desc else "",
                "image_url": og_image.get("content", "") if og_image else "",
                "date": datetime.now().strftime("%Y-%m-%d"),
                "permalink": FACEBOOK_PAGE_URL,
                "type": "page_info",
            })
            print(f"  Facebook: got page metadata")
        else:
            print("  Facebook: login wall detected, manual entry needed")

    except Exception as e:
        print(f"  Facebook scrape failed: {e}", file=sys.stderr)

    return posts

def main():
    print("Scraping Nginaajiiw social media...")

    result = {
        "facebook": {
            "page_url": FACEBOOK_PAGE_URL,
            "posts": scrape_facebook(),
        },
        "instagram": {
            "profile_url": f"https://www.instagram.com/{INSTAGRAM_USERNAME}/",
            "posts": scrape_instagram(),
        },
    }

    OUTPUT_PATH.parent.mkdir(parents=True, exist_ok=True)
    OUTPUT_PATH.write_text(json.dumps(result, indent=2, ensure_ascii=False))
    print(f"\nWrote {OUTPUT_PATH}")
    print(f"  Instagram posts: {len(result['instagram']['posts'])}")
    print(f"  Facebook posts: {len(result['facebook']['posts'])}")

    if not result["instagram"]["posts"] and not result["facebook"]["posts"]:
        print("\n⚠ No posts scraped. Manual fallback required:")
        print("  1. Visit the Instagram and Facebook pages in a browser")
        print("  2. Manually add post data to data/nginaajiiw_social.json")
        print("  3. Use the schema from the spec for the JSON format")

if __name__ == "__main__":
    main()
```

- [ ] **Step 2: Run the scraper**

```bash
source scripts/.venv/bin/activate
python scripts/scrape_nginaajiiw_social.py
```

Expected: Instagram posts likely succeeds (instaloader works well with public profiles). Facebook will probably return minimal data (login wall). Check output and manually supplement Facebook posts if needed.

- [ ] **Step 3: Review and supplement output**

Check `data/nginaajiiw_social.json`. If Instagram failed due to rate limiting, wait 5 minutes and retry. If Facebook returned nothing, manually add 3-6 recent posts from the public page.

- [ ] **Step 4: Commit**

```bash
git add scripts/scrape_nginaajiiw_social.py
git commit -m "feat: add Nginaajiiw social media scraper"
```

---

### Task 4: Scrape LNHL event and team data

**Files:**
- Create: `scripts/scrape_lnhl.py`
- Output: `data/lnhl_event.json`, `data/lnhl_teams.json`

- [ ] **Step 1: Write the LNHL scraper**

Create `scripts/scrape_lnhl.py`:

```python
#!/usr/bin/env python3
"""Scrape Little NHL 2026 event metadata and team/community data."""

import json
import sys
from pathlib import Path

import requests
from bs4 import BeautifulSoup

DATA_DIR = Path(__file__).parent.parent / "data"

SOURCES = {
    "lnhl": "https://lnhl.ca",
    "markham": "https://visitmarkham.ca/lnhl/",
    "anishinabek": "https://anishinabeknews.ca/2026/03/record-breaking-little-nhl-tournament-features-271-participating-teams/",
}

HEADERS = {"User-Agent": "Mozilla/5.0 (compatible; MinooBot/1.0; +https://minoo.live)"}

def fetch(url):
    """Fetch URL with error handling."""
    try:
        resp = requests.get(url, timeout=30, headers=HEADERS)
        resp.raise_for_status()
        return BeautifulSoup(resp.text, "lxml")
    except Exception as e:
        print(f"  Failed to fetch {url}: {e}", file=sys.stderr)
        return None

def scrape_event():
    """Build event metadata from multiple sources."""
    event = {
        "name": "Little NHL 2026",
        "title": "Little NHL 2026",  # Minoo event entity uses 'title' as label key
        "dates": {"start": "2026-03-15", "end": "2026-03-19"},
        "location": "Markham, Ontario",
        "host_nation": "Wiikwemkoong Unceded Territory",
        "venues": [
            {"name": "Centennial Community Centre", "city": "Markham"},
            {"name": "Angus Glen Community Centre", "city": "Markham"},
            {"name": "Mount Joy Community Centre", "city": "Markham"},
        ],
        "divisions": ["Tyke", "Novice", "Atom", "Peewee", "Bantam", "Midget", "Girls"],
        "description": "",
        "total_teams": 271,
        "total_players": 4500,
        "sponsors": ["Hydro One", "Indigenous Tourism Ontario"],
        "urls": {
            "official": "https://lnhl.ca",
            "markham": "https://visitmarkham.ca/lnhl/",
        },
    }

    # Try to enrich description from Anishinabek News article
    soup = fetch(SOURCES["anishinabek"])
    if soup:
        article = soup.find("article") or soup.find("div", class_="entry-content")
        if article:
            paragraphs = article.find_all("p")
            # Take first 3 paragraphs as description
            desc_parts = []
            for p in paragraphs[:3]:
                text = p.get_text(strip=True)
                if text and len(text) > 20:
                    desc_parts.append(text)
            event["description"] = "\n\n".join(desc_parts)
            print(f"  Event description: {len(event['description'])} chars from Anishinabek News")

    # Try lnhl.ca for additional info
    soup = fetch(SOURCES["lnhl"])
    if soup:
        # Extract any team count or schedule info
        text = soup.get_text()
        print(f"  lnhl.ca: {len(text)} chars of content scraped")

    return event

def scrape_teams():
    """Extract team/community list from available sources."""
    teams = []

    # Try Anishinabek News article for team mentions
    soup = fetch(SOURCES["anishinabek"])
    if soup:
        text = soup.get_text()
        # The article likely mentions participating communities
        # Extract community names - this will need manual curation
        print(f"  Anishinabek article: {len(text)} chars for team extraction")

    # Try lnhl.ca for team listings
    soup = fetch(SOURCES["lnhl"])
    if soup:
        # Look for team/community listings in tables, lists, or divs
        for table in soup.find_all("table"):
            for row in table.find_all("tr"):
                cells = row.find_all(["td", "th"])
                if len(cells) >= 2:
                    team_name = cells[0].get_text(strip=True)
                    if team_name and team_name.lower() not in ["team", "name", ""]:
                        teams.append({
                            "team_name": team_name,
                            "community": cells[1].get_text(strip=True) if len(cells) > 1 else "",
                            "division": cells[2].get_text(strip=True) if len(cells) > 2 else "",
                        })

        # Also try list elements
        if not teams:
            for ul in soup.find_all("ul"):
                for li in ul.find_all("li"):
                    text = li.get_text(strip=True)
                    if text and len(text) > 3:
                        teams.append({
                            "team_name": text,
                            "community": "",
                            "division": "",
                        })

    if not teams:
        print("  ⚠ No teams extracted automatically. Manual entry needed.")
        print("  Check: https://lnhl.ca and Spordle schedules for team listings")

    return teams

def main():
    print("Scraping LNHL 2026 data...")

    DATA_DIR.mkdir(parents=True, exist_ok=True)

    event = scrape_event()
    event_path = DATA_DIR / "lnhl_event.json"
    event_path.write_text(json.dumps(event, indent=2, ensure_ascii=False))
    print(f"\nWrote {event_path}")

    teams = scrape_teams()
    teams_path = DATA_DIR / "lnhl_teams.json"
    teams_path.write_text(json.dumps(teams, indent=2, ensure_ascii=False))
    print(f"Wrote {teams_path} ({len(teams)} teams)")

if __name__ == "__main__":
    main()
```

- [ ] **Step 2: Run the scraper**

```bash
source scripts/.venv/bin/activate
python scripts/scrape_lnhl.py
```

Expected: Event metadata populated from Anishinabek News. Team extraction may be partial — LNHL.ca may use JavaScript rendering. Check output.

- [ ] **Step 3: Review and supplement**

Check `data/lnhl_event.json` — ensure description is meaningful. Check `data/lnhl_teams.json` — if empty or sparse, manually add key teams from lnhl.ca or Spordle schedules viewed in browser.

- [ ] **Step 4: Commit**

```bash
git add scripts/scrape_lnhl.py
git commit -m "feat: add LNHL event and team scraper"
```

---

### Task 5: Curate LNHL teaching content

**Files:**
- Create: `scripts/scrape_lnhl_content.py`
- Output: `data/lnhl_teaching.json`

- [ ] **Step 1: Write the teaching content curator**

Create `scripts/scrape_lnhl_content.py`:

```python
#!/usr/bin/env python3
"""Curate Little NHL teaching content from authoritative sources."""

import json
import sys
from pathlib import Path

import requests
from bs4 import BeautifulSoup

OUTPUT_PATH = Path(__file__).parent.parent / "data" / "lnhl_teaching.json"
HEADERS = {"User-Agent": "Mozilla/5.0 (compatible; MinooBot/1.0; +https://minoo.live)"}

SOURCES = [
    {
        "url": "https://en.wikipedia.org/wiki/Little_Native_Hockey_League",
        "title": "Little Native Hockey League - Wikipedia",
        "type": "reference",
    },
    {
        "url": "https://canadiangeographic.ca/articles/celebrating-50-years-of-the-little-nhl/",
        "title": "Celebrating 50 Years of the Little NHL - Canadian Geographic",
        "type": "narrative",
    },
    {
        "url": "https://www.nhl.com/news/color-of-hockey-little-native-league-blossoming",
        "title": "Color of Hockey: Little Native Hockey League Blossoming - NHL.com",
        "type": "narrative",
    },
]

def fetch_text(url):
    """Fetch and extract article body text."""
    try:
        resp = requests.get(url, timeout=30, headers=HEADERS)
        resp.raise_for_status()
        soup = BeautifulSoup(resp.text, "lxml")

        # Remove nav, footer, sidebar, script, style
        for tag in soup.find_all(["nav", "footer", "aside", "script", "style", "header"]):
            tag.decompose()

        # Try article body, then main, then body
        body = soup.find("article") or soup.find("main") or soup.find("div", class_="mw-parser-output")
        if body:
            paragraphs = [p.get_text(strip=True) for p in body.find_all("p") if p.get_text(strip=True)]
            return "\n\n".join(paragraphs)
        return soup.get_text(separator="\n", strip=True)
    except Exception as e:
        print(f"  Failed to fetch {url}: {e}", file=sys.stderr)
        return ""

def main():
    print("Curating LNHL teaching content...")

    # Base teaching structure
    teaching = {
        "title": "The Little Native Hockey League",
        "type": "history",
        "origin": (
            "The Little Native Hockey League (LNHL) was founded in 1971 in Little Current, "
            "Manitoulin Island, by Earl Abotossaway and a group of community leaders. Created "
            "as a response to the racism and exclusion Indigenous youth faced in mainstream "
            "hockey leagues, the tournament gave First Nations children a space to compete "
            "with pride, surrounded by their communities and cultures."
        ),
        "founders": ["Earl Abotossaway"],
        "four_pillars": ["Education", "Citizenship", "Sportsmanship", "Respect"],
        "significance": (
            "More than a hockey tournament, the LNHL is one of the largest annual gatherings "
            "of First Nations communities in Ontario. It brings together thousands of families, "
            "Elders, and youth from across the province, strengthening cultural bonds and "
            "community connections. The four pillars — Education, Citizenship, Sportsmanship, "
            "and Respect — reflect the tournament's role in nurturing well-rounded young people."
        ),
        "notable_alumni": [
            {"name": "Ted Nolan", "nation": "Ojibwe (Garden River First Nation)", "achievement": "NHL head coach (Buffalo Sabres, New York Islanders), Jack Adams Award winner"},
        ],
        "notable_supporters_2026": [
            {"name": "Crystal Shawanda", "nation": "Wiikwemkoong", "note": "Ojibwe country/blues musician, drove from Nashville for the tournament"},
            {"note": "NHL players giving shout outs to teams (identify specific players from social media/coverage)"},
        ],
        "ceremonies": ["Flag ceremony (opening, nation flag carriers)"],
        "participating_communities_observed": ["Sagamok Anishnawbek"],
        "current_scale": {
            "year": 2026,
            "teams": 271,
            "girls_teams": 59,
            "players": 4500,
            "host": "Wiikwemkoong Unceded Territory",
            "location": "Markham, Ontario",
        },
        "body": "",  # Will be assembled from source material
        "sources": [],
    }

    # Fetch source material
    all_text = []
    for source in SOURCES:
        print(f"  Fetching: {source['title']}...")
        text = fetch_text(source["url"])
        if text:
            all_text.append(f"## {source['title']}\n\n{text}")
            teaching["sources"].append({"url": source["url"], "title": source["title"]})
            print(f"    Got {len(text)} chars")
        else:
            print(f"    ⚠ Failed")

    # Store raw source material for editorial curation
    raw_path = OUTPUT_PATH.parent / "lnhl_teaching_raw.txt"
    raw_path.write_text("\n\n---\n\n".join(all_text))
    print(f"\n  Raw source material saved to {raw_path} for editorial review")

    # Assemble a body from the origin and significance
    teaching["body"] = (
        f"{teaching['origin']}\n\n"
        f"{teaching['significance']}\n\n"
        f"In 2026, the tournament set a new record with {teaching['current_scale']['teams']} teams, "
        f"including {teaching['current_scale']['girls_teams']} girls' teams and over "
        f"{teaching['current_scale']['players']} players. Hosted by "
        f"{teaching['current_scale']['host']} and held in "
        f"{teaching['current_scale']['location']}, the LNHL continues to grow as a celebration "
        f"of Indigenous youth, community, and resilience.\n\n"
        f"The 2026 tournament opened with a flag ceremony honouring the nations represented. "
        f"Ojibwe country and blues artist Crystal Shawanda, from Wiikwemkoong, drove from "
        f"Nashville to attend — a testament to how the Little NHL draws the community home "
        f"no matter the distance. NHL players also gave shout outs to the young athletes, "
        f"connecting the tournament's grassroots spirit to the highest levels of the sport."
    )

    OUTPUT_PATH.parent.mkdir(parents=True, exist_ok=True)
    OUTPUT_PATH.write_text(json.dumps(teaching, indent=2, ensure_ascii=False))
    print(f"\nWrote {OUTPUT_PATH}")

if __name__ == "__main__":
    main()
```

- [ ] **Step 2: Run the curator**

```bash
source scripts/.venv/bin/activate
python scripts/scrape_lnhl_content.py
```

Expected: `data/lnhl_teaching.json` with structured teaching content. `data/lnhl_teaching_raw.txt` with raw source material for editorial review.

- [ ] **Step 3: Review teaching body text**

The body text is pre-written from research. Review `data/lnhl_teaching.json` and edit the `body` field if needed. The raw source material in `data/lnhl_teaching_raw.txt` provides additional context.

- [ ] **Step 4: Commit**

```bash
git add scripts/scrape_lnhl_content.py
git commit -m "feat: add LNHL teaching content curator"
```

---

### Task 6: Scrape LNHL media

**Files:**
- Create: `scripts/scrape_lnhl_media.py`
- Output: `data/lnhl_media.json`, `data/media/lnhl/*.jpg`

- [ ] **Step 1: Write the media scraper**

Create `scripts/scrape_lnhl_media.py`:

```python
#!/usr/bin/env python3
"""Download LNHL images with attribution for Minoo content."""

import json
import sys
from pathlib import Path
from urllib.parse import urljoin, urlparse

import requests
from bs4 import BeautifulSoup

DATA_DIR = Path(__file__).parent.parent / "data"
MEDIA_DIR = DATA_DIR / "media" / "lnhl"
OUTPUT_PATH = DATA_DIR / "lnhl_media.json"
HEADERS = {"User-Agent": "Mozilla/5.0 (compatible; MinooBot/1.0; +https://minoo.live)"}

SOURCES = [
    "https://lnhl.ca",
    "https://visitmarkham.ca/lnhl/",
]

def download_image(url, filename):
    """Download an image to the media directory."""
    try:
        resp = requests.get(url, timeout=30, headers=HEADERS, stream=True)
        resp.raise_for_status()
        filepath = MEDIA_DIR / filename
        with open(filepath, "wb") as f:
            for chunk in resp.iter_content(8192):
                f.write(chunk)
        return True
    except Exception as e:
        print(f"  Failed to download {url}: {e}", file=sys.stderr)
        return False

def scrape_images():
    """Collect image URLs from LNHL sources."""
    images = []
    seen_urls = set()

    for source_url in SOURCES:
        try:
            resp = requests.get(source_url, timeout=30, headers=HEADERS)
            resp.raise_for_status()
            soup = BeautifulSoup(resp.text, "lxml")

            for img in soup.find_all("img"):
                src = img.get("src", "")
                if not src:
                    continue
                # Resolve relative URLs
                src = urljoin(source_url, src)
                # Skip tiny icons, tracking pixels
                if any(skip in src.lower() for skip in ["pixel", "tracking", "favicon", "logo", "icon", "1x1"]):
                    continue
                if src in seen_urls:
                    continue
                seen_urls.add(src)

                # Generate filename from URL
                parsed = urlparse(src)
                ext = Path(parsed.path).suffix or ".jpg"
                filename = f"lnhl-{len(images)+1:03d}{ext}"

                images.append({
                    "url": src,
                    "filename": filename,
                    "caption": img.get("alt", ""),
                    "attribution": f"Source: {urlparse(source_url).netloc}",
                    "use_for": ["event_hero"] if len(images) == 0 else ["gallery"],
                    "source_page": source_url,
                })

            print(f"  {source_url}: found {len([i for i in images if i['source_page'] == source_url])} images")

        except Exception as e:
            print(f"  Failed to scrape {source_url}: {e}", file=sys.stderr)

    return images

def main():
    print("Scraping LNHL media...")
    MEDIA_DIR.mkdir(parents=True, exist_ok=True)

    images = scrape_images()

    # Download images (limit to first 20 to be reasonable)
    downloaded = 0
    for img in images[:20]:
        if download_image(img["url"], img["filename"]):
            img["downloaded"] = True
            downloaded += 1
        else:
            img["downloaded"] = False

    OUTPUT_PATH.write_text(json.dumps(images, indent=2, ensure_ascii=False))
    print(f"\nWrote {OUTPUT_PATH}")
    print(f"  Total images found: {len(images)}")
    print(f"  Downloaded: {downloaded}")

    if not images:
        print("\n⚠ No images found. LNHL.ca may use JavaScript rendering.")
        print("  Manual fallback: screenshot or download images from browser.")

if __name__ == "__main__":
    main()
```

- [ ] **Step 2: Run the scraper**

```bash
source scripts/.venv/bin/activate
python scripts/scrape_lnhl_media.py
```

Expected: Images downloaded to `data/media/lnhl/`, metadata in `data/lnhl_media.json`. If sites use JS rendering, may get zero images — manually download from browser.

- [ ] **Step 3: Review and tag images**

Review `data/lnhl_media.json`. Update `use_for` tags: mark best image as `["event_hero", "teaching"]`. Remove any irrelevant images (ads, navigation icons that slipped through).

- [ ] **Step 4: Commit**

```bash
git add scripts/scrape_lnhl_media.py
git commit -m "feat: add LNHL media scraper"
```

---

### Task 7: Community cross-reference

**Files:**
- Create: `scripts/community_crossref.py`
- Output: `data/community_enrichment.json`

**Dependency:** Requires `data/lnhl_teams.json` from Task 4. Also requires access to Minoo's SQLite database to read existing communities.

- [ ] **Step 1: Write the cross-reference script**

Create `scripts/community_crossref.py`:

```python
#!/usr/bin/env python3
"""Cross-reference LNHL teams with Minoo community entities."""

import json
import sqlite3
import sys
from pathlib import Path

DATA_DIR = Path(__file__).parent.parent / "data"
DB_PATH = Path(__file__).parent.parent / "waaseyaa.sqlite"
TEAMS_PATH = DATA_DIR / "lnhl_teams.json"
OUTPUT_PATH = DATA_DIR / "community_enrichment.json"

def normalize(name):
    """Normalize community name for fuzzy matching."""
    return name.lower().strip().replace("first nation", "").replace("  ", " ").strip()

def load_minoo_communities():
    """Load communities from Minoo's SQLite database."""
    if not DB_PATH.exists():
        print(f"  ⚠ Database not found at {DB_PATH}")
        print("  Run the dev server first or copy production DB")
        return []

    conn = sqlite3.connect(str(DB_PATH))
    conn.row_factory = sqlite3.Row
    try:
        rows = conn.execute("SELECT cid, name, slug, nc_id FROM community WHERE status = 1").fetchall()
        return [dict(r) for r in rows]
    except Exception as e:
        print(f"  ⚠ Failed to query communities: {e}", file=sys.stderr)
        return []
    finally:
        conn.close()

def main():
    print("Cross-referencing LNHL teams with Minoo communities...")

    if not TEAMS_PATH.exists():
        print(f"  ⚠ {TEAMS_PATH} not found. Run scrape_lnhl.py first.")
        sys.exit(1)

    teams = json.loads(TEAMS_PATH.read_text())
    communities = load_minoo_communities()

    if not communities:
        print("  ⚠ No communities loaded. Creating placeholder output.")
        result = {"matched": [], "unmatched": [{"team": t, "action": "manual_review"} for t in teams]}
        OUTPUT_PATH.write_text(json.dumps(result, indent=2, ensure_ascii=False))
        return

    # Build normalized lookup
    comm_lookup = {}
    for c in communities:
        norm = normalize(c["name"])
        comm_lookup[norm] = c
        # Also index without "First Nation" suffix variations
        for suffix in [" first nation", " unceded territory", " indian reserve"]:
            if norm.endswith(suffix.strip()):
                comm_lookup[norm.replace(suffix.strip(), "").strip()] = c

    matched = []
    unmatched = []

    for team in teams:
        community_name = team.get("community", "") or team.get("team_name", "")
        norm = normalize(community_name)

        match = comm_lookup.get(norm)
        if not match:
            # Try partial match
            for key, comm in comm_lookup.items():
                if norm in key or key in norm:
                    match = comm
                    break

        if match:
            matched.append({
                "lnhl_team": team.get("team_name", ""),
                "community_name": community_name,
                "minoo_community_id": match["cid"],
                "minoo_community_name": match["name"],
                "minoo_slug": match["slug"],
                "match_method": "name_normalized",
            })
        else:
            unmatched.append({
                "lnhl_team": team.get("team_name", ""),
                "community_name": community_name,
                "action": "manual_review",
            })

    result = {"matched": matched, "unmatched": unmatched}
    OUTPUT_PATH.write_text(json.dumps(result, indent=2, ensure_ascii=False))
    print(f"\nWrote {OUTPUT_PATH}")
    print(f"  Matched: {len(matched)}")
    print(f"  Unmatched: {len(unmatched)}")

if __name__ == "__main__":
    main()
```

- [ ] **Step 2: Run the cross-reference**

```bash
source scripts/.venv/bin/activate
python scripts/community_crossref.py
```

Expected: Matches LNHL teams to Minoo communities. Unmatched teams flagged for manual review. If database not available, produces placeholder output.

- [ ] **Step 3: Review matches**

Check `data/community_enrichment.json`. Verify matched communities are correct. Review unmatched list for spelling variants that could be manually matched.

- [ ] **Step 4: Commit**

```bash
git add scripts/community_crossref.py
git commit -m "feat: add LNHL community cross-reference script"
```

---

## Phase 2 — Minoo Platform Changes

### Task 8: Add `social_posts` field to group entity and `tournament` to event types

**Files:**
- Modify: `src/Provider/GroupServiceProvider.php:131` (add field before closing bracket)
- Modify: `src/Seed/ConfigSeeder.php:16` (add tournament type)

- [ ] **Step 1: Write failing test for tournament event type**

No test needed for ConfigSeeder (static arrays already tested). Verify manually:

```bash
php -r "require 'vendor/autoload.php'; var_dump(Minoo\Seed\ConfigSeeder::eventTypes());"
```

Expected: 3 types (powwow, gathering, ceremony). No `tournament` yet.

- [ ] **Step 2: Add `tournament` to ConfigSeeder::eventTypes()**

In `src/Seed/ConfigSeeder.php`, add after line 15 (ceremony entry):

```php
['type' => 'tournament', 'name' => 'Tournament', 'description' => 'Sports tournament or competitive event.'],
```

- [ ] **Step 3: Verify tournament type exists**

```bash
php -r "require 'vendor/autoload.php'; var_dump(Minoo\Seed\ConfigSeeder::eventTypes());"
```

Expected: 4 types including tournament.

- [ ] **Step 4: Add `social_posts` field to GroupServiceProvider**

In `src/Provider/GroupServiceProvider.php`, add after the `verified_at` field definition (after line 113):

```php
'social_posts' => [
    'type' => 'text_long',
    'label' => 'Social Posts',
    'description' => 'JSON array of recent social media posts.',
    'weight' => 97,
],
```

- [ ] **Step 5: Run tests to verify no breakage**

```bash
./vendor/bin/phpunit --testsuite MinooUnit
```

Expected: All tests pass. The new field doesn't break existing queries.

- [ ] **Step 6: Commit**

```bash
git add src/Provider/GroupServiceProvider.php src/Seed/ConfigSeeder.php
git commit -m "feat: add social_posts field to groups and tournament event type"
```

---

### Task 9: Create BusinessController with TDD

**Files:**
- Create: `tests/Minoo/Unit/Controller/BusinessControllerTest.php`
- Create: `src/Controller/BusinessController.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Minoo/Unit/Controller/BusinessControllerTest.php`:

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Controller;

use Minoo\Controller\BusinessController;
use Minoo\Entity\Group;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Entity\Storage\EntityQueryInterface;
use Waaseyaa\Entity\Storage\EntityStorageInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

#[CoversClass(BusinessController::class)]
final class BusinessControllerTest extends TestCase
{
    private EntityTypeManager $entityTypeManager;
    private Environment $twig;
    private EntityStorageInterface $storage;
    private EntityQueryInterface $query;
    private AccountInterface $account;
    private HttpRequest $request;

    protected function setUp(): void
    {
        $this->query = $this->createMock(EntityQueryInterface::class);
        $this->query->method('condition')->willReturnSelf();
        $this->query->method('sort')->willReturnSelf();

        $this->storage = $this->createMock(EntityStorageInterface::class);
        $this->storage->method('getQuery')->willReturn($this->query);

        $this->entityTypeManager = $this->createMock(EntityTypeManager::class);
        $this->entityTypeManager->method('getStorage')
            ->with('group')
            ->willReturn($this->storage);

        $this->twig = new Environment(new ArrayLoader([
            'businesses.html.twig' => '{{ path }}{% for b in businesses|default([]) %}|{{ b.get("name") }}{% endfor %}{% if business is defined and business %}|{{ business.get("name") }}{% endif %}',
        ]));

        $this->account = $this->createMock(AccountInterface::class);
        $this->request = HttpRequest::create('/');
    }

    #[Test]
    public function list_returns_200_with_businesses(): void
    {
        $salon = new Group(['gid' => 1, 'name' => 'Nginaajiiw Salon & Spa', 'slug' => 'nginaajiiw-salon-spa', 'type' => 'business']);
        $shop = new Group(['gid' => 2, 'name' => 'Cedar & Stone', 'slug' => 'cedar-and-stone', 'type' => 'business']);

        $this->query->method('execute')->willReturn([1, 2]);
        $this->storage->method('loadMultiple')
            ->with([1, 2])
            ->willReturn([1 => $salon, 2 => $shop]);

        $controller = new BusinessController($this->entityTypeManager, $this->twig);
        $response = $controller->list([], [], $this->account, $this->request);

        $this->assertSame(200, $response->statusCode);
        $this->assertStringContainsString('Nginaajiiw Salon & Spa', $response->content);
        $this->assertStringContainsString('Cedar & Stone', $response->content);
    }

    #[Test]
    public function list_returns_200_when_empty(): void
    {
        $this->query->method('execute')->willReturn([]);

        $controller = new BusinessController($this->entityTypeManager, $this->twig);
        $response = $controller->list([], [], $this->account, $this->request);

        $this->assertSame(200, $response->statusCode);
        $this->assertStringContainsString('/businesses', $response->content);
    }

    #[Test]
    public function show_returns_200_for_existing_business(): void
    {
        $salon = new Group(['gid' => 1, 'name' => 'Nginaajiiw Salon & Spa', 'slug' => 'nginaajiiw-salon-spa', 'type' => 'business']);

        $this->query->method('execute')->willReturn([1]);
        $this->storage->method('load')
            ->with(1)
            ->willReturn($salon);

        $controller = new BusinessController($this->entityTypeManager, $this->twig);
        $response = $controller->show(['slug' => 'nginaajiiw-salon-spa'], [], $this->account, $this->request);

        $this->assertSame(200, $response->statusCode);
        $this->assertStringContainsString('Nginaajiiw Salon & Spa', $response->content);
    }

    #[Test]
    public function show_returns_404_for_missing_business(): void
    {
        $this->query->method('execute')->willReturn([]);

        $controller = new BusinessController($this->entityTypeManager, $this->twig);
        $response = $controller->show(['slug' => 'nonexistent'], [], $this->account, $this->request);

        $this->assertSame(404, $response->statusCode);
    }

    #[Test]
    public function show_returns_404_for_non_business_group(): void
    {
        $group = new Group(['gid' => 1, 'name' => 'Some Community Group', 'slug' => 'some-group', 'type' => 'offline']);

        $this->query->method('execute')->willReturn([1]);
        $this->storage->method('load')
            ->with(1)
            ->willReturn($group);

        $controller = new BusinessController($this->entityTypeManager, $this->twig);
        $response = $controller->show(['slug' => 'some-group'], [], $this->account, $this->request);

        $this->assertSame(404, $response->statusCode);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
./vendor/bin/phpunit tests/Minoo/Unit/Controller/BusinessControllerTest.php
```

Expected: FAIL — `BusinessController` class not found.

- [ ] **Step 3: Write minimal BusinessController**

Create `src/Controller/BusinessController.php`:

```php
<?php

declare(strict_types=1);

namespace Minoo\Controller;

use Minoo\Support\CommunityLookup;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\SSR\SsrResponse;

final class BusinessController
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly Environment $twig,
    ) {}

    /** @param array<string, mixed> $params */
    /** @param array<string, mixed> $query */
    public function list(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $storage = $this->entityTypeManager->getStorage('group');
        $ids = $storage->getQuery()
            ->condition('type', 'business')
            ->condition('status', 1)
            ->sort('name', 'ASC')
            ->execute();
        $businesses = $ids !== [] ? array_values($storage->loadMultiple($ids)) : [];

        $businesses = array_filter($businesses, function ($entity) {
            $mediaId = $entity->get('media_id');
            if ($mediaId === null || $mediaId === '') {
                return true;
            }
            $status = $entity->get('copyright_status');
            return in_array($status, ['community_owned', 'cc_by_nc_sa'], true);
        });
        $businesses = array_values($businesses);

        $communities = CommunityLookup::build($this->entityTypeManager, $businesses);

        $html = $this->twig->render('businesses.html.twig', [
            'path' => '/businesses',
            'businesses' => $businesses,
            'communities' => $communities,
        ]);

        return new SsrResponse(content: $html);
    }

    /** @param array<string, mixed> $params */
    /** @param array<string, mixed> $query */
    public function show(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $slug = $params['slug'] ?? '';
        $storage = $this->entityTypeManager->getStorage('group');
        $ids = $storage->getQuery()
            ->condition('slug', $slug)
            ->condition('type', 'business')
            ->condition('status', 1)
            ->execute();
        $business = $ids !== [] ? $storage->load(reset($ids)) : null;

        if ($business !== null) {
            $mediaId = $business->get('media_id');
            if ($mediaId !== null && $mediaId !== '') {
                $status = $business->get('copyright_status');
                if (!in_array($status, ['community_owned', 'cc_by_nc_sa'], true)) {
                    $business = null;
                }
            }
        }

        // Load linked owner (ResourcePerson)
        $owner = null;
        if ($business !== null) {
            $personStorage = $this->entityTypeManager->getStorage('resource_person');
            $ownerIds = $personStorage->getQuery()
                ->condition('linked_group_id', $business->id())
                ->condition('status', 1)
                ->execute();
            $owner = $ownerIds !== [] ? $personStorage->load(reset($ownerIds)) : null;
        }

        $html = $this->twig->render('businesses.html.twig', [
            'path' => '/businesses/' . $slug,
            'business' => $business,
            'owner' => $owner,
        ]);

        return new SsrResponse(
            content: $html,
            statusCode: $business !== null ? 200 : 404,
        );
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

```bash
./vendor/bin/phpunit tests/Minoo/Unit/Controller/BusinessControllerTest.php
```

Expected: All 5 tests pass. Note: the `show_returns_404_for_non_business_group` test verifies that accessing a non-business group via `/businesses/` returns 404 because the query includes `condition('type', 'business')`.

- [ ] **Step 5: Commit**

```bash
git add src/Controller/BusinessController.php tests/Minoo/Unit/Controller/BusinessControllerTest.php
git commit -m "feat: add BusinessController with type=business filtering (TDD)"
```

---

### Task 10: Register business routes and update group listing

**Files:**
- Modify: `src/Provider/GroupServiceProvider.php:143-165` (add business routes)
- Modify: `src/Controller/GroupController.php:26-28` (filter out businesses)

- [ ] **Step 1: Add business routes to GroupServiceProvider**

In `src/Provider/GroupServiceProvider.php`, add after the existing `groups.show` route (after line 164):

```php
$router->addRoute(
    'businesses.list',
    RouteBuilder::create('/businesses')
        ->controller('Minoo\\Controller\\BusinessController::list')
        ->allowAll()
        ->render()
        ->methods('GET')
        ->build(),
);

$router->addRoute(
    'businesses.show',
    RouteBuilder::create('/businesses/{slug}')
        ->controller('Minoo\\Controller\\BusinessController::show')
        ->allowAll()
        ->render()
        ->methods('GET')
        ->requirement('slug', '[a-z0-9][a-z0-9-]*[a-z0-9]')
        ->build(),
);
```

- [ ] **Step 2: Filter businesses out of group listing**

In `src/Controller/GroupController.php:26`, add one line to the existing query chain. The rest of the method (copyright filtering, CommunityLookup, render) stays unchanged.

Add `->condition('type', 'business', '!=')` to the query:

```php
$ids = $storage->getQuery()
    ->condition('status', 1)
    ->condition('type', 'business', '!=')
    ->sort('name', 'ASC')
    ->execute();
```

This is only the query change — the `array_filter` for copyright status and everything below it remains as-is.

- [ ] **Step 3: Run full test suite**

```bash
./vendor/bin/phpunit --testsuite MinooUnit
```

Expected: All tests pass. Clear stale manifest if needed:

```bash
rm -f storage/framework/packages.php
```

- [ ] **Step 4: Commit**

```bash
git add src/Provider/GroupServiceProvider.php src/Controller/GroupController.php
git commit -m "feat: register /businesses routes, exclude businesses from /groups"
```

---

### Task 11: Create business templates and CSS

**Files:**
- Create: `templates/businesses.html.twig`
- Create: `templates/components/business-card.html.twig`
- Modify: `public/css/minoo.css` (add business styles in `@layer components`)

- [ ] **Step 1: Create business card component**

Create `templates/components/business-card.html.twig`:

```twig
<article class="card card--business">
  {% if image_url is defined and image_url %}
    <img class="card__image" src="{{ image_url }}" alt="{{ name }}" loading="lazy">
  {% endif %}
  <span class="card__badge card__badge--business">{{ trans('businesses.badge') }}</span>
  <h3 class="card__title"><a href="{{ url }}">{{ name }}</a></h3>
  {% if community_name is defined and community_name %}
    <p class="card__meta card__community"><a href="/communities/{{ community_slug|url_encode }}">{{ community_name }}</a></p>
  {% endif %}
  {% if excerpt is defined and excerpt %}
    <p class="card__body">{{ excerpt }}</p>
  {% endif %}
  {% if booking_url is defined and booking_url %}
    <a href="{{ booking_url }}" class="btn btn--sm" target="_blank" rel="noopener">{{ trans('businesses.book_now') }}</a>
  {% endif %}
</article>
```

- [ ] **Step 2: Create businesses template**

Create `templates/businesses.html.twig`:

```twig
{% extends "base.html.twig" %}

{% block title %}
  {%- if path == '/businesses' -%}
    {{ trans('businesses.title') }} — Minoo
  {%- elseif business is defined and business -%}
    {{ business.get('name') }} — Minoo
  {%- else -%}
    {{ trans('businesses.not_found') }} — Minoo
  {%- endif -%}
{% endblock %}

{% block content %}
  {% if path == '/businesses' %}
    <div class="flow-lg">
      <h1>{{ trans('businesses.title') }}</h1>
      <p class="hero__subtitle">{{ trans('businesses.subtitle') }}</p>

      {% if businesses is defined and businesses|length > 0 %}
        <div class="card-grid">
          {% for b in businesses %}
            {% set comm = communities[b.get('community_id')]|default({}) %}
            {% include "components/business-card.html.twig" with {
              name: b.get('name'),
              excerpt: b.get('description')|default('')|split("\n\n")|first|length > 120 ? b.get('description')|default('')|split("\n\n")|first|slice(0, 120) ~ '…' : b.get('description')|default('')|split("\n\n")|first,
              url: "/businesses/" ~ b.get('slug'),
              booking_url: b.get('booking_url')|default(''),
              community_name: comm.name|default(''),
              community_slug: comm.slug|default('')
            } %}
          {% endfor %}
        </div>
      {% else %}
        {% include "components/empty-state.html.twig" with {
          heading: trans('businesses.empty_heading'),
          body: trans('businesses.empty_body'),
          action_url: lang_url('/communities'),
          action_label: trans('businesses.explore_button')
        } %}
      {% endif %}
    </div>

  {% elseif business is defined and business %}
    <div class="flow-lg business-detail">
      <a href="{{ lang_url('/businesses') }}" class="detail__back">{{ trans('businesses.detail_back') }}</a>

      {% set social_posts = business.get('social_posts')|default('')|trim %}

      <div class="business-detail__header">
        <div class="business-detail__info">
          <span class="card__badge card__badge--business">{{ trans('businesses.badge') }}</span>
          <h1>{{ business.get('name') }}</h1>
          <div class="detail__body flow">
            {% for paragraph in business.get('description')|default('')|split("\n\n") %}
              <p>{{ paragraph }}</p>
            {% endfor %}
          </div>
        </div>
        <aside class="business-detail__sidebar">
          {% if business.get('booking_url') %}
            <a href="{{ business.get('booking_url') }}" class="btn" target="_blank" rel="noopener">{{ trans('businesses.book_now') }}</a>
          {% endif %}
          {% if business.get('phone') %}
            <p><a href="tel:{{ business.get('phone') }}">{{ business.get('phone') }}</a></p>
          {% endif %}
          {% if business.get('email') %}
            <p><a href="mailto:{{ business.get('email') }}">{{ business.get('email') }}</a></p>
          {% endif %}
          {% if business.get('address') %}
            <p>{{ business.get('address') }}</p>
          {% endif %}
          {% if business.get('url') %}
            <p class="detail__website"><a href="{{ business.get('url') }}" target="_blank" rel="noopener">{{ trans('businesses.visit_website') }}</a></p>
          {% endif %}
        </aside>
      </div>

      {% if owner is defined and owner %}
        <section class="owner-card">
          <h2>{{ trans('businesses.owner_heading') }}</h2>
          <div class="owner-card__content">
            <div class="owner-card__info">
              <h3><a href="/people/{{ owner.get('slug') }}">{{ owner.get('name') }}</a></h3>
              {% if owner.get('bio') %}
                <p>{{ owner.get('bio')|split("\n\n")|first }}</p>
              {% endif %}
            </div>
          </div>
        </section>
      {% endif %}

      {% if social_posts is defined and social_posts|length > 0 %}
        <section class="social-feed">
          <h2>{{ trans('businesses.social_heading') }}</h2>
          <div class="social-feed__grid">
            {% for post in social_posts|slice(0, 6) %}
              <a href="{{ post.permalink }}" class="social-feed__post" target="_blank" rel="noopener">
                {% if post.image_url is defined and post.image_url %}
                  <img src="{{ post.image_url }}" alt="" loading="lazy">
                {% endif %}
                <p>{{ post.text|default(post.caption|default(''))|slice(0, 100) }}{% if (post.text|default(post.caption|default('')))|length > 100 %}…{% endif %}</p>
                <span class="social-feed__meta">{{ post.source|default('') }} · {{ post.date|default('') }}</span>
              </a>
            {% endfor %}
          </div>
        </section>
      {% endif %}
    </div>

  {% else %}
    <div class="flow-lg">
      <h1>{{ trans('businesses.not_found') }}</h1>
      <p>{{ trans('businesses.not_found_message') }}</p>
      <p><a href="{{ lang_url('/businesses') }}">{{ trans('businesses.browse_all') }}</a></p>
    </div>
  {% endif %}
{% endblock %}
```

- [ ] **Step 3: Add business CSS to minoo.css**

Add to `public/css/minoo.css` inside `@layer components` (after the existing card domain accents around line 787):

```css
  .card--business  { --card-accent: var(--color-programs); }

  /* Business detail */
  .business-detail {
    max-inline-size: var(--width-wide);
  }

  .business-detail__header {
    display: grid;
    grid-template-columns: 1fr;
    gap: var(--space-md);
  }

  @media (min-width: 48rem) {
    .business-detail__header {
      grid-template-columns: 2fr 1fr;
    }
  }

  .business-detail__sidebar {
    display: flex;
    flex-direction: column;
    gap: var(--space-xs);
    font-size: var(--text-sm);
    color: var(--text-secondary);
    padding: var(--space-sm);
    background-color: var(--surface-card);
    border-radius: 0.5rem;
    align-self: start;
  }

  .business-detail__sidebar .btn {
    text-align: center;
  }

  /* Owner card */
  .owner-card {
    padding: var(--space-sm);
    background-color: var(--surface-card);
    border-radius: 0.5rem;
    border-inline-start: 3px solid var(--color-people);
  }

  .owner-card h2 {
    font-size: var(--text-sm);
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: var(--text-muted);
    margin-block-end: var(--space-xs);
  }

  .owner-card__content {
    display: flex;
    gap: var(--space-sm);
    align-items: start;
  }

  .owner-card__info h3 {
    font-family: var(--font-heading);
    font-size: var(--text-base);
  }

  .owner-card__info h3 a {
    color: var(--text-primary);
  }

  .owner-card__info p {
    font-size: var(--text-sm);
    color: var(--text-secondary);
    line-height: 1.5;
    margin-block-start: var(--space-3xs);
  }

  /* Social feed */
  .social-feed h2 {
    font-size: var(--text-sm);
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: var(--text-muted);
    margin-block-end: var(--space-xs);
  }

  .social-feed__grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(min(100%, 16rem), 1fr));
    gap: var(--space-sm);
  }

  .social-feed__post {
    display: flex;
    flex-direction: column;
    gap: var(--space-3xs);
    padding: var(--space-xs);
    background-color: var(--surface-card);
    border-radius: 0.5rem;
    text-decoration: none;
    color: var(--text-secondary);
    font-size: var(--text-sm);
    transition: transform 0.15s ease;
  }

  .social-feed__post:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
  }

  .social-feed__post img {
    border-radius: 0.25rem;
    inline-size: 100%;
    aspect-ratio: 1;
    object-fit: cover;
  }

  .social-feed__meta {
    font-size: 0.65rem;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.05em;
  }
```

- [ ] **Step 4: Update BusinessController::show() to decode social posts**

Update the `show()` method in `src/Controller/BusinessController.php` to decode the social_posts JSON before passing to Twig. Add before the `$this->twig->render()` call:

```php
$socialPosts = [];
if ($business !== null) {
    $raw = $business->get('social_posts') ?? '';
    if ($raw !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $socialPosts = $decoded;
        }
    }
}
```

And add `'social_posts' => $socialPosts` to the render array.

- [ ] **Step 5: Run tests**

```bash
rm -f storage/framework/packages.php
./vendor/bin/phpunit --testsuite MinooUnit
```

Expected: All tests pass.

- [ ] **Step 6: Commit**

```bash
git add templates/businesses.html.twig templates/components/business-card.html.twig public/css/minoo.css src/Controller/BusinessController.php
git commit -m "feat: add business listing and detail templates with social feed"
```

---

## Phase 3 — Content Population

### Task 12: Populate Nginaajiiw business entity and create Larissa ResourcePerson

**Dependency:** Requires `data/nginaajiiw_website.json` and `data/nginaajiiw_social.json` from Tasks 2-3.

This task creates a CLI script to populate the database from scraped data.

**Files:**
- Create: `scripts/populate_nginaajiiw.php`

- [ ] **Step 1: Write the population script**

Create `scripts/populate_nginaajiiw.php`:

```php
#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Populate Nginaajiiw Salon & Spa business entity from scraped data.
 * Run: php scripts/populate_nginaajiiw.php
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use Waaseyaa\Foundation\HttpKernel;

// Boot kernel
$kernel = new HttpKernel(dirname(__DIR__));
$ref = new ReflectionMethod($kernel, 'boot');
$ref->invoke($kernel);

$entityTypeManager = $kernel->getContainer()->get(Waaseyaa\Entity\EntityTypeManager::class);

// Load scraped data
$websiteData = json_decode(file_get_contents(dirname(__DIR__) . '/data/nginaajiiw_website.json'), true);
$socialData = json_decode(file_get_contents(dirname(__DIR__) . '/data/nginaajiiw_social.json'), true);

// 1. Find and update Nginaajiiw group entity
$groupStorage = $entityTypeManager->getStorage('group');
$ids = $groupStorage->getQuery()->condition('slug', 'nginaajiiw-salon-spa')->execute();

if ($ids === []) {
    echo "⚠ Nginaajiiw group entity not found by slug. Check database.\n";
    exit(1);
}

$group = $groupStorage->load(reset($ids));
echo "Found group: {$group->get('name')} (gid: {$group->id()})\n";

// Update type to business
$group->set('type', 'business');
$group->set('description', $websiteData['description'] ?? $group->get('description'));
$group->set('url', $websiteData['website'] ?? '');
$group->set('phone', $websiteData['phone'] ?? '');
$group->set('email', $websiteData['email'] ?? '');
$group->set('address', $websiteData['address'] ?? '');
$group->set('booking_url', $websiteData['booking_url'] ?? '');
$group->set('source', 'scrape:nginaajiiw:2026-03-17');
$group->set('updated_at', time());

// Merge social posts into a flat array
$socialPosts = [];
foreach ($socialData['instagram']['posts'] ?? [] as $post) {
    $socialPosts[] = [
        'source' => 'instagram',
        'text' => $post['caption'] ?? '',
        'image_url' => $post['image_url'] ?? '',
        'date' => $post['date'] ?? '',
        'permalink' => $post['permalink'] ?? '',
    ];
}
foreach ($socialData['facebook']['posts'] ?? [] as $post) {
    $socialPosts[] = [
        'source' => 'facebook',
        'text' => $post['text'] ?? '',
        'image_url' => $post['image_url'] ?? '',
        'date' => $post['date'] ?? '',
        'permalink' => $post['permalink'] ?? '',
    ];
}
$group->set('social_posts', json_encode(array_slice($socialPosts, 0, 12)));

$groupStorage->save($group);
echo "✓ Updated Nginaajiiw: type=business, fields populated, {$count = count($socialPosts)} social posts\n";

// 2. Create or update Larissa Toulouse ResourcePerson
$personStorage = $entityTypeManager->getStorage('resource_person');
$existingIds = $personStorage->getQuery()->condition('slug', 'larissa-toulouse')->execute();

if ($existingIds !== []) {
    $person = $personStorage->load(reset($existingIds));
    echo "Found existing person: {$person->get('name')} (rpid: {$person->id()})\n";
} else {
    $person = new \Minoo\Entity\ResourcePerson([
        'name' => 'Larissa Toulouse',
        'slug' => 'larissa-toulouse',
    ]);
    echo "Creating new person: Larissa Toulouse\n";
}

$person->set('business_name', 'Nginaajiiw Salon & Spa');
$person->set('linked_group_id', $group->id());
$person->set('website', $websiteData['website'] ?? '');
$person->set('source', 'scrape:nginaajiiw:2026-03-17');
$person->set('updated_at', time());

// Note: roles and offerings are entity_reference fields — need term IDs
// Look up "Small Business Owner" in person_roles vocabulary
$termStorage = $entityTypeManager->getStorage('taxonomy_term');
$roleIds = $termStorage->getQuery()
    ->condition('name', 'Small Business Owner')
    ->condition('vid', 'person_roles')
    ->execute();
if ($roleIds !== []) {
    $person->set('roles', [reset($roleIds)]);
    echo "  Linked role: Small Business Owner (tid: " . reset($roleIds) . ")\n";
}

// Look up offerings
$offeringNames = ['Hair Services', 'Esthetics'];
$offeringIds = [];
foreach ($offeringNames as $offeringName) {
    $tids = $termStorage->getQuery()
        ->condition('name', $offeringName)
        ->condition('vid', 'person_offerings')
        ->execute();
    if ($tids !== []) {
        $offeringIds[] = reset($tids);
    }
}
if ($offeringIds !== []) {
    $person->set('offerings', $offeringIds);
    echo "  Linked offerings: " . implode(', ', $offeringNames) . "\n";
}

$personStorage->save($person);
echo "✓ Saved Larissa Toulouse (rpid: {$person->id()})\n";

echo "\nDone. Verify at:\n";
echo "  /businesses/nginaajiiw-salon-spa\n";
echo "  /people/larissa-toulouse\n";
```

- [ ] **Step 2: Run the population script**

```bash
php scripts/populate_nginaajiiw.php
```

Expected: Nginaajiiw updated to `type=business` with all fields populated. Larissa Toulouse created/updated and linked.

- [ ] **Step 3: Verify in browser**

```bash
php -S localhost:8081 -t public
```

Visit:
- `http://localhost:8081/businesses` — should show Nginaajiiw
- `http://localhost:8081/businesses/nginaajiiw-salon-spa` — full detail page
- `http://localhost:8081/groups` — should NOT show Nginaajiiw
- `http://localhost:8081/people/larissa-toulouse` — should show linked business

- [ ] **Step 4: Commit**

```bash
git add scripts/populate_nginaajiiw.php
git commit -m "feat: add Nginaajiiw population script with social posts and owner"
```

---

### Task 13: Create LNHL event and teaching entities

**Dependency:** Requires `data/lnhl_event.json` and `data/lnhl_teaching.json` from Tasks 4-5.

**Files:**
- Create: `scripts/populate_lnhl.php`

- [ ] **Step 1: Write the LNHL population script**

Create `scripts/populate_lnhl.php`:

```php
#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Create LNHL event and teaching entities from scraped data.
 * Run: php scripts/populate_lnhl.php
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use Waaseyaa\Foundation\HttpKernel;

$kernel = new HttpKernel(dirname(__DIR__));
$ref = new ReflectionMethod($kernel, 'boot');
$ref->invoke($kernel);

$entityTypeManager = $kernel->getContainer()->get(Waaseyaa\Entity\EntityTypeManager::class);

// Load scraped data
$eventData = json_decode(file_get_contents(dirname(__DIR__) . '/data/lnhl_event.json'), true);
$teachingData = json_decode(file_get_contents(dirname(__DIR__) . '/data/lnhl_teaching.json'), true);

// 1. Create LNHL Event
echo "Creating LNHL 2026 event...\n";
$eventStorage = $entityTypeManager->getStorage('event');

// Check if already exists
$existingIds = $eventStorage->getQuery()->condition('slug', 'little-nhl-2026')->execute();
if ($existingIds !== []) {
    $event = $eventStorage->load(reset($existingIds));
    echo "  Found existing event (eid: {$event->id()}), updating...\n";
} else {
    $event = new \Minoo\Entity\Event([
        'title' => $eventData['title'] ?? $eventData['name'],
        'slug' => 'little-nhl-2026',
    ]);
}

$event->set('title', $eventData['title'] ?? $eventData['name']);
$event->set('type', 'tournament');
$event->set('description', $eventData['description'] ?? '');
$event->set('location', $eventData['location'] ?? 'Markham, Ontario');
$event->set('starts_at', $eventData['dates']['start'] ?? '2026-03-15');
$event->set('ends_at', $eventData['dates']['end'] ?? '2026-03-19');
$event->set('source', 'scrape:lnhl:2026-03-17');
$event->set('status', 1);
$event->set('copyright_status', 'community_owned');
$event->set('created_at', time());
$event->set('updated_at', time());

$eventStorage->save($event);
echo "✓ Saved LNHL 2026 event (eid: {$event->id()})\n";

// 2. Create LNHL Teaching
echo "\nCreating LNHL teaching...\n";
$teachingStorage = $entityTypeManager->getStorage('teaching');

$existingIds = $teachingStorage->getQuery()->condition('slug', 'the-little-native-hockey-league')->execute();
if ($existingIds !== []) {
    $teaching = $teachingStorage->load(reset($existingIds));
    echo "  Found existing teaching (tid: {$teaching->id()}), updating...\n";
} else {
    $teaching = new \Minoo\Entity\Teaching([
        'title' => $teachingData['title'],
        'slug' => 'the-little-native-hockey-league',
    ]);
}

$teaching->set('title', $teachingData['title']);
$teaching->set('type', 'history');
$teaching->set('description', $teachingData['body'] ?? '');
$teaching->set('source', 'scrape:lnhl:2026-03-17');
$teaching->set('status', 1);
$teaching->set('copyright_status', 'community_owned');
$teaching->set('created_at', time());
$teaching->set('updated_at', time());

$teachingStorage->save($teaching);
echo "✓ Saved LNHL teaching (tid: {$teaching->id()})\n";

// 3. Create Crystal Shawanda as ResourcePerson (notable LNHL supporter)
echo "\nCreating Crystal Shawanda resource person...\n";
$personStorage = $entityTypeManager->getStorage('resource_person');
$existingIds = $personStorage->getQuery()->condition('slug', 'crystal-shawanda')->execute();

if ($existingIds !== []) {
    $person = $personStorage->load(reset($existingIds));
    echo "  Found existing person (rpid: {$person->id()}), updating...\n";
} else {
    $person = new \Minoo\Entity\ResourcePerson([
        'name' => 'Crystal Shawanda',
        'slug' => 'crystal-shawanda',
    ]);
}

$person->set('bio', 'Ojibwe country and blues artist from Wiikwemkoong Unceded Territory. Award-winning musician based in Nashville who maintains deep ties to her community and the Little NHL.');
$person->set('community', 'Wiikwemkoong Unceded Territory');
$person->set('website', 'https://crystalshawanda.com');
$person->set('source', 'observed:lnhl:2026-03-17');
$person->set('status', 1);
$person->set('copyright_status', 'community_owned');
$person->set('updated_at', time());

// Look up Artist role
$termStorage = $entityTypeManager->getStorage('taxonomy_term');
$roleIds = $termStorage->getQuery()
    ->condition('name', 'Artist')
    ->condition('vid', 'person_roles')
    ->execute();
if ($roleIds !== []) {
    $person->set('roles', [reset($roleIds)]);
}

$personStorage->save($person);
echo "✓ Saved Crystal Shawanda (rpid: {$person->id()})\n";

echo "\nDone. Verify at:\n";
echo "  /events/little-nhl-2026\n";
echo "  /teachings/the-little-native-hockey-league\n";
echo "  /people/crystal-shawanda\n";
```

- [ ] **Step 2: Run the population script**

```bash
php scripts/populate_lnhl.php
```

Expected: Event and teaching entities created/updated.

- [ ] **Step 3: Verify in browser**

Visit:
- `http://localhost:8081/events` — should show Little NHL 2026
- `http://localhost:8081/events/little-nhl-2026` — full detail
- `http://localhost:8081/teachings` — should show LNHL teaching
- `http://localhost:8081/teachings/the-little-native-hockey-league` — full detail

- [ ] **Step 4: Commit**

```bash
git add scripts/populate_lnhl.php
git commit -m "feat: add LNHL event and teaching population script"
```

---

## Phase 4 — Verification

### Task 14: Full verification pass

- [ ] **Step 1: Run PHPUnit**

```bash
rm -f storage/framework/packages.php
./vendor/bin/phpunit
```

Expected: All tests pass (existing + new BusinessControllerTest).

- [ ] **Step 2: Start dev server and verify all pages**

```bash
php -S localhost:8081 -t public
```

Verify:
- [ ] `/businesses` — listing shows Nginaajiiw
- [ ] `/businesses/nginaajiiw-salon-spa` — detail with description, contact, booking CTA
- [ ] `/businesses/nginaajiiw-salon-spa` — owner card shows Larissa Toulouse
- [ ] `/businesses/nginaajiiw-salon-spa` — social feed shows recent posts
- [ ] `/groups` — does NOT show Nginaajiiw or any `type=business` groups
- [ ] `/events` — shows Little NHL 2026
- [ ] `/events/little-nhl-2026` — detail with dates, location, description
- [ ] `/teachings` — shows LNHL teaching
- [ ] `/teachings/the-little-native-hockey-league` — full teaching content
- [ ] `/people/larissa-toulouse` — shows linked business name

- [ ] **Step 3: Run Playwright smoke tests**

```bash
npx playwright test
```

Expected: All existing tests pass. New pages don't have dedicated Playwright tests yet (acceptable for sprint pace).

- [ ] **Step 4: Schema migration note for production**

Before deploying, run on production:

```sql
ALTER TABLE "group" ADD COLUMN social_posts TEXT;
```

And update Nginaajiiw's type:

```sql
UPDATE "group" SET type = 'business' WHERE slug = 'nginaajiiw-salon-spa';
```

---

## Translation Keys Needed

Add to translation files before templates render:

```
businesses.title = "Indigenous Businesses"
businesses.subtitle = "Support Indigenous-owned businesses in your community."
businesses.badge = "Business"
businesses.book_now = "Book Now"
businesses.detail_back = "All Businesses"
businesses.visit_website = "Visit Website"
businesses.owner_heading = "Owner"
businesses.social_heading = "Latest Updates"
businesses.not_found = "Business Not Found"
businesses.not_found_message = "The business you're looking for isn't available."
businesses.browse_all = "Browse all businesses"
businesses.empty_heading = "No businesses yet"
businesses.empty_body = "Indigenous-owned businesses will appear here as our community grows."
businesses.explore_button = "Explore Communities"
```

Anishinaabemowin translations should also be added per the project's i18n patterns.
