#!/usr/bin/env python3
"""
Scrape the muscleandstrength.com exercise directory into scraped.json.

Stage 1 of the MuscleAndStrengthSeeder pipeline (see ../../MuscleAndStrengthSeeder.php).
Fetches every muscle-group listing page (with pagination), collects unique
/exercises/<slug>.html detail URLs, downloads each detail page (cached to
cache/ so reruns are free), and parses the Exercise Profile block, overview
text, and YouTube embed ID.

Run on the host Mac (stdlib only): python3 scrape.py
Resumable: HTML responses are cached; delete cache/ to force a refetch.
"""

from __future__ import annotations

import json
import pathlib
import re
import sys
import time
import urllib.request

BASE = "https://www.muscleandstrength.com"
HERE = pathlib.Path(__file__).parent
CACHE = HERE / "cache"
OUT = HERE / "scraped.json"

UA = (
    "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) "
    "AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36"
)

# Muscle-group listing pages from /exercises (a few are single .html pages).
GROUP_PAGES = [
    "/exercises/abductors.html",
    "/exercises/abs",
    "/exercises/adductors.html",
    "/exercises/biceps",
    "/exercises/calves",
    "/exercises/chest",
    "/exercises/forearms",
    "/exercises/glutes",
    "/exercises/hamstrings",
    "/exercises/hip-flexors",
    "/exercises/it-band",
    "/exercises/lats",
    "/exercises/lower-back",
    "/exercises/middle-back",
    "/exercises/neck.html",
    "/exercises/obliques",
    "/exercises/palmar-fascia",
    "/exercises/plantar-fascia",
    "/exercises/quads",
    "/exercises/shoulders",
    "/exercises/traps",
    "/exercises/triceps",
]

# Listing pages that themselves end in .html and must not be treated as exercises.
NON_EXERCISE_HTML = {p.rsplit("/", 1)[-1] for p in GROUP_PAGES if p.endswith(".html")}

# Exercises reachable only from the equipment/mechanics listings, not any muscle page.
EXTRA_EXERCISES = [
    "/exercises/incline-two-arm-dumbbell-extension.html",
    "/exercises/narrow-stance-45-degree-leg-press.html",
]

DELAY_SECONDS = 0.8


def fetch(path: str) -> str | None:
    """Fetch a site path with an on-disk cache; returns None on HTTP failure."""
    key = re.sub(r"[^a-z0-9_.-]+", "_", path.strip("/").replace("/", "__"))
    cached = CACHE / f"{key}.html"
    if cached.exists():
        return cached.read_text(errors="replace")

    request = urllib.request.Request(BASE + path, headers={"User-Agent": UA})
    try:
        with urllib.request.urlopen(request, timeout=30) as response:
            html = response.read().decode("utf-8", errors="replace")
    except Exception as error:  # noqa: BLE001 - log and move on, scrape is resumable
        print(f"  !! fetch failed {path}: {error}", file=sys.stderr)
        return None

    CACHE.mkdir(exist_ok=True)
    cached.write_text(html)
    time.sleep(DELAY_SECONDS)
    return html


def exercise_links(html: str) -> set[str]:
    links = set(re.findall(r'href="(/exercises/[a-z0-9-]+\.html)"', html))
    return {l for l in links if l.rsplit("/", 1)[-1] not in NON_EXERCISE_HTML}


def collect_exercise_urls() -> list[str]:
    urls: set[str] = set()
    for group in GROUP_PAGES:
        page = 0
        while True:
            path = group if page == 0 else f"{group}?page={page}"
            html = fetch(path)
            if html is None:
                break
            found = exercise_links(html)
            before = len(urls)
            urls |= found
            print(f"{path}: +{len(urls) - before} (total {len(urls)})")
            if f"page={page + 1}" not in html:
                break
            page += 1
    urls.update(EXTRA_EXERCISES)
    return sorted(urls)


def text_of(fragment: str) -> str:
    text = re.sub(r"<[^>]+>", " ", fragment)
    return re.sub(r"\s+", " ", text).replace("&amp;", "&").replace("&#039;", "'").strip()


def parse_exercise(path: str, html: str) -> dict | None:
    stats_match = re.search(r'(?s)<div class="node-stats-block">(.*?)</ul>', html)
    if not stats_match:
        return None

    profile: dict[str, str] = {}
    for item in re.findall(r"(?s)<li[^>]*>(.*?)</li>", stats_match.group(1)):
        label_match = re.search(r'(?s)<span class="row-label">(.*?)</span>(.*)', item)
        if label_match:
            profile[text_of(label_match.group(1))] = text_of(label_match.group(2))

    title_match = re.search(r"(?s)<h1>(.*?)</h1>", html)
    name = text_of(title_match.group(1)) if title_match else ""
    name = re.sub(r"\s*Video (Exercise|Stretch) Guide\s*$", "", name, flags=re.I)

    overview_match = re.search(
        r'(?s)<div class="field field-name-field-exercise-overview[^"]*">(.*?)</div></div></div>', html
    )
    overview = text_of(overview_match.group(1)) if overview_match else ""

    video_match = re.search(r'youtube(?:-nocookie)?\.com/embed/([A-Za-z0-9_-]{6,})', html)
    vimeo_match = re.search(r'player\.vimeo\.com/video/(\d+)', html)

    return {
        "slug": path.rsplit("/", 1)[-1].removesuffix(".html"),
        "source_url": BASE + path,
        "name": name,
        "target_muscle": profile.get("Target Muscle Group", ""),
        "exercise_type": profile.get("Exercise Type", ""),
        "equipment": profile.get("Equipment Required", ""),
        "mechanics": profile.get("Mechanics", ""),
        "force_type": profile.get("Force Type", ""),
        "experience_level": profile.get("Experience Level", ""),
        "secondary_muscles": [
            m.strip() for m in profile.get("Secondary Muscles", "").split(",") if m.strip() and m.strip().lower() != "none"
        ],
        "overview": overview,
        "youtube_id": video_match.group(1) if video_match else None,
        "vimeo_id": vimeo_match.group(1) if vimeo_match else None,
    }


def main() -> None:
    urls = collect_exercise_urls()
    print(f"\n{len(urls)} unique exercise pages to fetch\n")

    rows = []
    for index, path in enumerate(urls, start=1):
        html = fetch(path)
        if html is None:
            continue
        row = parse_exercise(path, html)
        if row is None:
            print(f"  -- no profile block, skipping {path}")
            continue
        rows.append(row)
        if index % 50 == 0:
            print(f"  parsed {index}/{len(urls)}")
            OUT.write_text(json.dumps(rows, indent=1))

    OUT.write_text(json.dumps(rows, indent=1))
    with_video = sum(1 for r in rows if r["youtube_id"] or r["vimeo_id"])
    print(f"\nDone: {len(rows)} exercises ({with_video} with video) -> {OUT}")


if __name__ == "__main__":
    main()
