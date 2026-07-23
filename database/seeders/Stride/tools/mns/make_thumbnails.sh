#!/usr/bin/env bash
#
# Stage 3b: extract a poster frame from every downloaded video as thumbs/<slug>.jpg.
# Frame at 1s in (falls back to first frame for ultra-short clips), max 640px wide.
# Resumable — existing thumbnails are skipped, so run it any time after (or during)
# download_videos.sh; rerun after new videos land.
# Requires: brew install ffmpeg

set -uo pipefail
cd "$(dirname "$0")"
mkdir -p thumbs

MADE=0
for VIDEO in videos/*.mp4; do
    [[ -e "$VIDEO" ]] || continue
    SLUG=$(basename "$VIDEO" .mp4)
    OUT="thumbs/${SLUG}.jpg"
    [[ -s "$OUT" ]] && continue

    if ffmpeg -hide_banner -loglevel error -ss 1 -i "$VIDEO" \
        -frames:v 1 -vf "scale='min(640,iw)':-2" -q:v 3 "$OUT" -y \
        && [[ -s "$OUT" ]]; then
        MADE=$((MADE + 1))
    else
        ffmpeg -hide_banner -loglevel error -i "$VIDEO" \
            -frames:v 1 -vf "scale='min(640,iw)':-2" -q:v 3 "$OUT" -y \
            && [[ -s "$OUT" ]] && MADE=$((MADE + 1)) || { rm -f "$OUT"; echo "!! failed: $SLUG"; }
    fi
done

echo "Made $MADE new thumbnails. $(ls thumbs/*.jpg 2>/dev/null | wc -l | tr -d ' ') total."
