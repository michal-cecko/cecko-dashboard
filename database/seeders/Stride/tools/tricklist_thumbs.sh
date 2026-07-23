#!/usr/bin/env bash
#
# One-shot: extract a poster frame for every freestyle-tricklist video already
# hosted on S3 (data/freestyle_tricklist.json video_url entries), writing
# tricklist_thumbs/<shortcode>.jpg locally. ffmpeg reads the remote mp4 directly.
# Resumable — existing thumbnails are skipped.
# Upload afterwards:
#   aws s3 sync tricklist_thumbs/ s3://public-synapps-dashboard/stride/exercises/thumbs/ \
#     --endpoint-url https://nbg1.your-objectstorage.com \
#     --content-type image/jpeg --acl public-read
# Then run add_tricklist_thumbs.py to stamp thumbnail_url into the JSON.
# Requires: brew install ffmpeg jq

set -uo pipefail
cd "$(dirname "$0")"
mkdir -p tricklist_thumbs

MADE=0
FAILED=0
while read -r URL; do
    SLUG=$(basename "$URL" .mp4)
    OUT="tricklist_thumbs/${SLUG}.jpg"
    [[ -s "$OUT" ]] && continue

    if ffmpeg -hide_banner -loglevel error -ss 1 -i "$URL" \
        -frames:v 1 -vf "scale='min(640,iw)':-2" -q:v 3 "$OUT" -y \
        && [[ -s "$OUT" ]]; then
        MADE=$((MADE + 1))
    else
        ffmpeg -hide_banner -loglevel error -i "$URL" \
            -frames:v 1 -vf "scale='min(640,iw)':-2" -q:v 3 "$OUT" -y \
            && [[ -s "$OUT" ]] && MADE=$((MADE + 1)) || { rm -f "$OUT"; FAILED=$((FAILED + 1)); echo "!! failed: $SLUG"; }
    fi
done < <(jq -r '.[] | .video_url // empty' ../data/freestyle_tricklist.json)

echo "Made $MADE new thumbnails, $FAILED failed. $(ls tricklist_thumbs/*.jpg 2>/dev/null | wc -l | tr -d ' ') total."
