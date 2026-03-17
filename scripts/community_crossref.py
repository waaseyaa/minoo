#!/usr/bin/env python3
"""Cross-reference LNHL teams with Minoo community database.

Reads team data from data/lnhl_teams.json and matches against communities
in the waaseyaa.sqlite database using normalized name matching with
partial match fallback.

Output: data/community_enrichment.json
"""

import json
import re
import sqlite3
import sys
from datetime import datetime, timezone
from pathlib import Path

OUTPUT_DIR = Path(__file__).parent.parent / "data"
PROJECT_ROOT = Path(__file__).parent.parent
DB_PATH = PROJECT_ROOT / "waaseyaa.sqlite"
TEAMS_PATH = OUTPUT_DIR / "lnhl_teams.json"


def normalize_name(name: str) -> str:
    """Normalize a community/team name for comparison.

    Strips common suffixes, whitespace, and punctuation to improve
    matching between LNHL team names and database community names.
    """
    name = name.lower().strip()
    # Remove common suffixes
    suffixes = [
        "first nation", "first nations", "fn",
        "indian band", "band",
        "hawks", "warriors", "bears", "eagles", "wolves",
        "thunder", "braves", "chiefs", "mustangs", "lightning",
        "coyotes", "wildcats", "storm",
    ]
    for suffix in suffixes:
        name = re.sub(rf"\b{re.escape(suffix)}\b", "", name)
    # Remove punctuation and extra whitespace
    name = re.sub(r"[^\w\s]", "", name)
    name = re.sub(r"\s+", " ", name).strip()
    return name


def get_communities(db_path: Path) -> list[dict]:
    """Load communities from the SQLite database."""
    if not db_path.exists():
        print(f"  WARNING: Database not found at {db_path}")
        return []

    try:
        conn = sqlite3.connect(str(db_path))
        conn.row_factory = sqlite3.Row
        cursor = conn.cursor()

        # Try to find the communities table
        cursor.execute("SELECT name FROM sqlite_master WHERE type='table'")
        tables = [row["name"] for row in cursor.fetchall()]

        community_table = None
        for candidate in ["cultural_group", "community", "communities", "cultural_groups"]:
            if candidate in tables:
                community_table = candidate
                break

        if not community_table:
            print(f"  WARNING: No community table found. Tables: {tables}")
            conn.close()
            return []

        print(f"  Using table: {community_table}")
        cursor.execute(f"SELECT * FROM {community_table}")
        columns = [desc[0] for desc in cursor.description]
        communities = []
        for row in cursor.fetchall():
            comm = dict(zip(columns, row))
            communities.append(comm)

        conn.close()
        return communities

    except sqlite3.Error as e:
        print(f"  WARNING: Database error: {e}")
        return []


def match_teams_to_communities(teams: list[dict], communities: list[dict]) -> dict:
    """Match LNHL teams to database communities."""
    # Build normalized lookup for communities
    comm_lookup = {}
    for comm in communities:
        # Try multiple name fields
        for field in ["name", "title", "label", "community_name"]:
            if field in comm and comm[field]:
                norm = normalize_name(str(comm[field]))
                if norm:
                    comm_lookup[norm] = comm
                break

    matched = []
    unmatched = []

    for team in teams:
        team_name = team.get("team_name", "") or team.get("community", "")
        if not team_name:
            continue

        norm_team = normalize_name(team_name)
        match_info = {
            "team_name": team_name,
            "division": team.get("division", ""),
            "normalized": norm_team,
        }

        # Exact normalized match
        if norm_team in comm_lookup:
            comm = comm_lookup[norm_team]
            match_info["match_type"] = "exact"
            match_info["community"] = _safe_serialize(comm)
            matched.append(match_info)
            continue

        # Partial match — check if any community name contains or is contained by team name
        partial_match = None
        best_score = 0
        for norm_comm, comm in comm_lookup.items():
            if not norm_comm or not norm_team:
                continue
            # Check containment in both directions
            if norm_team in norm_comm or norm_comm in norm_team:
                # Score by how much overlap there is
                score = len(set(norm_team.split()) & set(norm_comm.split()))
                if score > best_score:
                    best_score = score
                    partial_match = comm

        if partial_match:
            match_info["match_type"] = "partial"
            match_info["match_score"] = best_score
            match_info["community"] = _safe_serialize(partial_match)
            matched.append(match_info)
        else:
            match_info["match_type"] = "none"
            unmatched.append(match_info)

    return {
        "matched": matched,
        "unmatched": unmatched,
        "summary": {
            "total_teams": len(teams),
            "matched": len(matched),
            "unmatched": len(unmatched),
            "match_rate": f"{len(matched) / max(len(teams), 1) * 100:.1f}%",
        },
    }


def _safe_serialize(obj: dict) -> dict:
    """Make a dict JSON-serializable by converting non-standard types."""
    result = {}
    for k, v in obj.items():
        if isinstance(v, (str, int, float, bool, type(None))):
            result[k] = v
        elif isinstance(v, bytes):
            result[k] = v.decode("utf-8", errors="replace")
        else:
            result[k] = str(v)
    return result


def main():
    print("=== Community Cross-Reference ===")
    OUTPUT_DIR.mkdir(parents=True, exist_ok=True)

    # Load teams
    if not TEAMS_PATH.exists():
        print(f"  ERROR: Teams file not found at {TEAMS_PATH}")
        print("  Run scrape_lnhl.py first to generate team data.")
        sys.exit(1)

    teams_data = json.loads(TEAMS_PATH.read_text())
    teams = teams_data.get("teams", [])
    print(f"  Loaded {len(teams)} teams from {TEAMS_PATH}")

    if not teams:
        print("  WARNING: No teams to cross-reference.")
        result = {
            "scraped_at": datetime.now(timezone.utc).isoformat(),
            "note": "No teams available for cross-referencing. Run scrape_lnhl.py first.",
            "matched": [],
            "unmatched": [],
            "summary": {"total_teams": 0, "matched": 0, "unmatched": 0, "match_rate": "0%"},
        }
    else:
        # Load communities from database
        print(f"  Loading communities from {DB_PATH} ...")
        communities = get_communities(DB_PATH)
        print(f"  Loaded {len(communities)} communities")

        if not communities:
            print("  WARNING: No communities found in database.")
            result = {
                "scraped_at": datetime.now(timezone.utc).isoformat(),
                "note": "Database not available or empty. Teams listed but unmatched.",
                "matched": [],
                "unmatched": [
                    {"team_name": t.get("team_name", ""), "division": t.get("division", ""), "match_type": "none"}
                    for t in teams
                ],
                "summary": {
                    "total_teams": len(teams),
                    "matched": 0,
                    "unmatched": len(teams),
                    "match_rate": "0%",
                },
            }
        else:
            # Cross-reference
            print("  Matching teams to communities ...")
            result = match_teams_to_communities(teams, communities)
            result["scraped_at"] = datetime.now(timezone.utc).isoformat()
            result["database"] = str(DB_PATH)

    output_path = OUTPUT_DIR / "community_enrichment.json"
    output_path.write_text(json.dumps(result, indent=2, ensure_ascii=False))
    print(f"\n  Wrote {output_path}")
    print(f"  Summary: {result['summary']}")
    print("Done.")


if __name__ == "__main__":
    main()
