#!/usr/bin/env python3
"""
Map scraped.json (raw muscleandstrength.com fields) to the stride_exercises
shape consumed by MuscleAndStrengthSeeder, writing ../../data/mns_gym_exercises.json.

Stage 2 of the pipeline. video_url is stamped for every slug that has a local
videos/<slug>.mp4 (i.e. was downloaded and will be / has been uploaded to S3);
rows without a downloaded video keep video_url = null and get filled on a rerun.
"""

from __future__ import annotations

import json
import pathlib

HERE = pathlib.Path(__file__).parent
SCRAPED = HERE / "scraped.json"
VIDEOS = HERE / "videos"
THUMBS = HERE / "thumbs"
OUT = HERE.parent.parent / "data" / "mns_gym_exercises.json"

S3_BASE = "https://public-synapps-dashboard.nbg1.your-objectstorage.com/stride/exercises/mns"

# M&S Target Muscle Group → Stride `group` (must match PlanGenerationService::namesForKind()).
GROUP_MAP = {
    "Chest": "Chest",
    "Shoulders": "Shoulders",
    "Triceps": "Triceps",
    "Biceps": "Biceps",
    "Forearms": "Forearms",
    "Lats": "Back",
    "Upper Back": "Back",
    "Middle Back": "Back",
    "Lower Back": "Back",
    "Traps": "Back",
    "Traps (mid-back)": "Back",
    "Upper Trapezius": "Back",
    "Quads": "Quads",
    "Hamstrings": "Hamstrings",
    "Glutes": "Glutes",
    "Calves": "Calves",
    "Abs": "Core",
    "Obliques": "Core",
    "Abductors": "Glutes",
    "Adductors": "Legs",
    "Hip Flexors": "Legs",
    "IT Band": "Legs",
    "Palmar Fascia": "Forearms",
    "Plantar Fascia": "Calves",
    "Neck": None,
}

CATEGORY_MAP = {
    "Strength": "strength",
    "Stretching": "mobility",
    "Cardio": "cardio",
    "Conditioning": "conditioning",
    "Plyometrics": "conditioning",
    "Olympic Weightlifting": "strength",
    "Powerlifting": "strength",
    "Strongman": "strength",
    "Warmup": "mobility",
    "Activation": "mobility",
    "SMR": "mobility",
}


def metric_type(category: str, equipment: str) -> str:
    if category == "cardio":
        return "run"
    if category == "conditioning":
        return "machine"
    if category == "mobility":
        return "reps"
    if equipment.lower() in {"bodyweight", "none"}:
        return "reps"
    return "load"


def main() -> None:
    rows = json.loads(SCRAPED.read_text())
    seen: set[str] = set()
    out = []
    unmapped_groups: dict[str, int] = {}
    unmapped_types: dict[str, int] = {}

    for row in rows:
        slug = row["slug"]
        if slug in seen or not row["name"]:
            continue
        seen.add(slug)

        category = CATEGORY_MAP.get(row["exercise_type"])
        if category is None:
            unmapped_types[row["exercise_type"]] = unmapped_types.get(row["exercise_type"], 0) + 1
            category = "strength"

        if row["target_muscle"] not in GROUP_MAP:
            unmapped_groups[row["target_muscle"]] = unmapped_groups.get(row["target_muscle"], 0) + 1
        group = GROUP_MAP.get(row["target_muscle"])

        tag = row["mechanics"] if row["mechanics"] in ("Compound", "Isolation") else "Isolation"
        difficulty = row["experience_level"] if row["experience_level"] in ("Beginner", "Intermediate", "Advanced") else "Beginner"
        equipment = row["equipment"] or "Bodyweight"

        description = row["overview"].strip()
        if len(description) > 600:
            description = description[:600].rsplit(" ", 1)[0] + "…"

        out.append({
            "slug": slug,
            "name": row["name"],
            "category": category,
            "group": group,
            "tag": tag,
            "metric_type": metric_type(category, equipment),
            "difficulty": difficulty,
            "equipment_label": equipment,
            "primary_muscles": [row["target_muscle"]] if row["target_muscle"] else [],
            "secondary_muscles": row["secondary_muscles"],
            "video_url": f"{S3_BASE}/{slug}.mp4" if (VIDEOS / f"{slug}.mp4").exists() else None,
            "thumbnail_url": f"{S3_BASE}/thumbs/{slug}.jpg" if (THUMBS / f"{slug}.jpg").exists() else None,
            "description": description or None,
            "source_url": row["source_url"],
            "youtube_id": row["youtube_id"],
        })

    out.sort(key=lambda r: r["slug"])
    OUT.write_text(json.dumps(out, indent=1, ensure_ascii=False) + "\n")

    with_video = sum(1 for r in out if r["video_url"])
    print(f"{len(out)} rows ({with_video} with video_url) -> {OUT}")
    if unmapped_types:
        print(f"Unmapped exercise types (defaulted to strength): {unmapped_types}")
    if unmapped_groups:
        print(f"Unmapped target muscles (group=null): {unmapped_groups}")


if __name__ == "__main__":
    main()
